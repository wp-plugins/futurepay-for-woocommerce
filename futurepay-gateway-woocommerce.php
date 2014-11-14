<?php
/**
 * Plugin Name: FuturePay for WooCommerce
 * Plugin URI: https://www.futurepay.com
 * Description: Allow payment processing via FuturePay Gateway
 * Version: 1.0.7
 * Author: FuturePay
 * Depends: WooCommerce
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// this is a hack to make sure the woocommerce plugin loads before this one
// the Depends: definition above does not work!!
$woo_plugin_path = plugin_dir_path(dirname(__FILE__)) . '/woocommerce/woocommerce.php';
if (file_exists($woo_plugin_path)) {
    include_once $woo_plugin_path;
}

add_action('plugins_loaded', 'init_futurepay_woo_gateway');

add_action("wp_ajax_get_country_regions", 'futurepay_woo_get_country_regions');
function futurepay_woo_get_country_regions()
{
    $states = WC()->countries->states[$_GET['country']];
    if (!is_null($states)) {
        $count = count($states);
        echo json_encode(array_merge(array('cnt' => $count), $states));
    } else {
        echo json_encode(array());
    }
    exit;
}


function init_futurepay_woo_gateway() {
   
    class WC_Gateway_FuturePay extends WC_Payment_Gateway {

        const ICON_PATH = 'assets/images/icons/futurepay.png';
        const FUTUREPAY_LIVE_URL = 'https://api.futurepay.com/remote/';
        const FUTUREPAY_SANDBOX_URL = 'https://demo.futurepay.com/remote/';
        const FUTUREPAY_SANDBOX_GMID = '882bb39add08c23107753012b2917bfd1bb28bf8FPM975056';
        
        private $request_url = null;
        private $gmid = null;
        private $allowed_countries = array('US');
        private $allowed_currencies = array('USD');
        //private static $credit_limit = '500.00'; // to set a dollar amount
        private static $credit_limit = false;
        private static $futurepay_errorcodes = array(
            'FP_EXISTING_INVALID_CUSTOMER_STATUS'
            => 'Invalid Customer Status, the customer exists in FuturePay and is in an Active or Accepted Status', 'woocommerce',
            'FP_INVALID_ID_REQUEST'
            => 'Error: The GMID could not be validated – either missing or not valid format – Contact FuturePay', 'woocommerce',
            'FP_INVALID_SERVER_REQUEST'
            => 'Error: Either the Merchant Server is not on our IP Whitelist or the Order Reference was Missing', 'woocommerce',
            'FP_PRE_ORDER_EXCEEDS_MAXIMUM'
            => 'The Maximum Amount for a FuturePay order has been exceeded: Currently $500.00', 'woocommerce',
            'FP_MISSING_REFERENCE' =>
            'Reference was not detected in the Query String', 'woocommerce',
            'FP_INVALID_REFERENCE'
            => 'Reference was invalid', 'woocommerce',
            'FP_ORDER_EXISTS'
            => 'The reference exists with an order that has completed sales attached', 'woocommerce',
            'FP_MISSING_REQUIRED_FIRST_NAME'
            => 'First Name was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_LAST_NAME'
            => 'Last Name was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_PHONE'
            => 'Phone Name was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_CITY'
            => 'City was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_STATE'
            => 'State was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_ADDRESS'
            => 'Address was not detected in the Query String', 'woocommerce',
            'FP_MISSING_REQUIRED_COUNTRY'
            => 'Country was not detected in the Query String', 'woocommerce',
            'FP_COUNTRY_US_ONLY'
            => 'The Country was not USA', 'woocommerce',
            'FP_MISSING_EMAIL'
            => 'Email was not detected in the Query String', 'woocommerce',
            'FP_INVALID_EMAIL_SIZE'
            => 'Email Size was greater than 85', 'woocommerce',
            'FP_INVALID_EMAIL_FORMAT'
            => 'Email Format was not valid', 'woocommerce',
            'FP_MISSING_REQUIRED_ZIP'
            => 'Zip was not detected in the Query String', 'woocommerce',
            'FP_NO_ZIP_FOUND'
            => 'The Zip Code could not be found in the FuturePay lookup, may be a PO Box or Military Address which are not Accepted', 'woocommerce',
            'FP_FAILED_ZIP_LOOKUP'
            => 'FuturePay failed to lookup the Zip Code – FuturePay needs to investigate the cause', 'woocommerce',
            'FP_MISSING_ORDER_ITEM_FIELDS'
            => 'At least one order item must exist and for each order item all of the fields must exist for price, quantity, sku, description, tax_amount', 'woocommerce',
            'FP_INVALID_PRICE'
            => 'Price must be a non-negative float value', 'woocommerce',
            'FP_INVALID_TAX'
            => 'Tax must be a non-negative float value', 'woocommerce',
            'FP_INVALID_QUANTITY'
            => 'Quantity must be an integer', 'woocommerce',
            'FP_INVALID_SHIPPING_DATE'
            => 'The Shipping date could not be parsed', 'woocommerce',
            'FP_SHIPPING_IN_PAST'
            => 'The Shipping date must be today or in the Future', 'woocommerce',
            'FP_PRE_ORDER_FAILED'
            => 'An Error occurred in trying to save the Order – FuturePay needs to investigate the cause', 'woocommerce'
        );

        
        public function __construct() {
            
            $this->id = 'futurepay';            
            $this->icon = apply_filters('woocommerce_futurepay_icon', plugins_url('/', __FILE__) . WC_Gateway_FuturePay::ICON_PATH);
            $this->has_fields = true;
            $this->method_title = __('FuturePay', 'woocommerce');
            
            
            
            $this->method_description = __('This module allows you to accept online payments via <a href="https://www.futurepay.com/">FuturePay</a> allowing customers to buy now and pay later without a credit card. FuturePay is a safe, convenient and secure way for US customers to buy online in one-step.', 'woocommerce');

            // @hack! html for merchant signup - we want it to display above
            // the rest of the fields

            $this->method_description .= $this->get_signup_login();
            $this->method_description .= $this->get_merchant_signup_html();
            $this->method_description .= $this->get_merchant_login_html();
            
            $this->request_url = ($this->get_option('sandbox') == 'yes') ?
                    WC_Gateway_FuturePay::FUTUREPAY_SANDBOX_URL :
                    WC_Gateway_FuturePay::FUTUREPAY_LIVE_URL;

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);

            if ($this->get_option('sandbox') == 'yes') {
                $this->gmid = WC_Gateway_FuturePay::FUTUREPAY_SANDBOX_GMID;
            } else {
                $this->gmid = $this->get_option('futurepay_gmid');
            }

            // form has fields?
            $this->has_fields = true;

            // Actions
            add_action('futurepay-init', array($this, 'check_response'));
            if (isset($_GET['futurepay'])) {
                do_action('futurepay-init');
            }
                        
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action('futurepay_woo_valid_request', array($this, 'successful_request'), 10, 2);
            add_action( 'woocommerce_receipt_futurepay', array( $this, 'receipt_page' ) );
            add_action( 'admin_notices', array( $this, 'futurepay_notices' ) );
            
            // enqueue stylesheet for signup/login forms
            wp_enqueue_style( 'futurepay-woo-styles', plugins_url('/assets/css/style.css', __FILE__) );
            
            
            // Customer Emails
            //add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        

        // initialize gateway settings form fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable FuturePay', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Buy Now and Pay Later with FuturePay', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Buy Now and Pay Later without a credit card using FuturePay.  It’s secure, convenient and easy to use. Plus, there is no high APR interest or financing charges. Don’t have an account yet? You can signup and checkout in seconds.', 'woocommerce'),
                    'desc_tip' => true,
                ),
                // the rest of the settings
                'futurepay_gmid' => array(
                    'title' => __('Your FuturePay API Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Your unique FuturePay Merchant Identifier is provided to you when you create a Merchant Account with FuturePay.', 'woocommerce'),
                    'default' => __('', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'sandbox' => array(
                    'title' => __('Enable Sandbox', 'woocommerce'),
                    'label' => __(' ', 'woocommerce'),
                    'type' => 'checkbox',
                    'description' => __('Turn on to enable FuturePay sandbox for testing.', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
            );

        }

        
        public function process_admin_options()
        {
            return parent::process_admin_options();
        }
        
        
        public function is_available() {
            
            if ($this->get_option('enabled') != 'yes') {
                return false;
            }
            if (!in_array(get_woocommerce_currency(), $this->allowed_currencies)) {
                return false;
            }
            if (!in_array(WC()->countries->get_base_country(), $this->allowed_countries)) {
                return false;
            }
            if (WC_Gateway_FuturePay::$credit_limit !== false
                    && WC()->cart->cart_contents_total > WC_Gateway_FuturePay::$credit_limit) {
                return false;
            }
            if (strlen($this->gmid) < 1 && $this->get_option('sandbox') == 'no') {
                return false;
            }
            return parent::is_available();
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id) {
            if ( $this->instructions )
        	echo wpautop( wptexturize( $this->instructions ) );
        }

        public function receipt_page($order_id)
        {
            $order = new WC_Order($order_id);
            $order->update_status('on-hold', __( 'Waiting for customer to sign in to FuturePay', 'woocommerce' ));
            echo '<p>'.__('Thank you for your order, please fill out the form below to pay with FuturePay.', 'woocommerce').'</p>';
            echo $this->call_futurepay( $order_id );
        }
        
	/**
	 *  Admin Notices for conditions under which FuturePay is available on a Shop
	 */
	public function futurepay_notices() {
		if ( $this->get_option('enabled') != 'yes' ) return;

		if ( ! in_array(get_woocommerce_currency(), $this->allowed_currencies )) {
                    echo '<div class="error"><p>'.sprintf(__('The FuturePay gateway accepts payments in currencies of %s.  Your current currency is %s.  FuturePay won\'t work until you change the WooCommerce currency to an accepted one.','woocommerce'), implode( ', ', $this->allowed_currencies ), get_woocommerce_currency() ).'</p></div>';
		}
                
		if (!in_array(WC()->countries->get_base_country(), $this->allowed_countries)) {
                    $country_list = array();
                    foreach ( $this->allowed_countries as $this_country ) {
                        $country_list[] = WC()->countries->countries[$this_country];
                    }
                    echo '<div class="error"><p>'.sprintf(__('The FuturePay gateway is available to merchants from: %s.  Your country is: %s.  FuturePay won\'t work until you change the WooCommerce Shop Base country to an accepted one.  FuturePay is currently disabled on the Payment Gateways settings tab.','woocommerce'), implode( ', ', $country_list ), WC()->countries->get_base_country() ).'</p></div>';
		}

	}
        
        
	public function futurepay_script() {
		if ( ! is_page(wc_get_page_id( 'checkout' )) ) return;
    	?>
		<script type="text/javascript">
			/*<![CDATA[*/
				jQuery(document).ready( function($) {
                                    <?php if (self::$credit_limit !== false) { ?>
					var credit_limit = '<?php echo self::$credit_limit; ?>';
                                    <?php } else { ?>
                                        var credit_limit = false;
                                    <?php } ?>
					var currency_symbol = '<?php echo get_woocommerce_currency_symbol(); ?>';
					var fp_label = $('#payment_method_futurepay').next().html();
					var totalstr = $('.shop_table tfoot td:last()').find('strong').html().split(currency_symbol);
					var total = parseFloat(totalstr[1]);
					if ( credit_limit !== false && 
                                                total > credit_limit ) {
						$('#payment_method_futurepay').attr('disabled', 'disabled');
						$('#payment_method_futurepay').next().html('FuturePay -- unavailable for Orders over '+currency_symbol+credit_limit);
						$('#payment input[name=payment_method]:not(:disabled):first').attr('checked',true).trigger('click');
					} else {
						$('#payment_method_futurepay').removeAttr('disabled');
						$('#payment_method_futurepay').next().html(fp_label);
					}
					$(document).ajaxStop( function(event,request,settings) {
						if ( event.isTrigger ) {
							totalstr = $('.shop_table tfoot td:last()').find('strong').html().split(currency_symbol);
							total = parseFloat(totalstr[1]);
							if ( credit_limit !== false
                                                            && total > credit_limit ) {
								$('#payment_method_futurepay').next().html('FuturePay -- unavailable for Orders over ' + currency_symbol + credit_limit);
								$('#payment_method_futurepay').attr('disabled', 'disabled');
								$('#payment input[name=payment_method]:not(:disabled):first').attr('checked',true).trigger('click');
							} else {
								$('#payment_method_futurepay').next().html(fp_label);
								$('#payment_method_futurepay').removeAttr('disabled');
							}
						}
					});
				});
			/*]]>*/
		</script>
    	<?php
	}
        
        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {

            if (!$this->is_available()) {
                wc_add_notice("Payment error: FuturePay is currently not available. Try again later.", 'error');
                return;
            }
           
            $order = new WC_Order($order_id);
            
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink(wc_get_page_id('pay')) ))
            );
        }



        public function call_futurepay($order_id) {
            
            $order = new WC_Order($order_id);

            $data = array(
                'gmid' => $this->gmid,
                'reference' => $order_id . '-' . uniqid(),
                'email' => $order->billing_email,
                'first_name' => $order->billing_first_name,
                'last_name' => $order->billing_last_name,
                'company' => $order->billing_company,
                'address_line_1' => $order->billing_address_1,
                'address_line_2' => $order->billing_address_2,
                'city' => $order->billing_city,
                'state' => $order->billing_state,
                'country' => $order->billing_country,
                'zip' => $order->billing_postcode,
                'phone' => $order->billing_phone,
                'shipping_address_line_1' => $order->shipping_address_1,
                'shipping_address_line_2' => $order->shipping_address_2,
                'shipping_city' => $order->shipping_city,
                'shipping_state' => $order->shipping_state,
                'shipping_country' => $order->shipping_country,
                'shipping_zip' => $order->shipping_postcode,
                'shipping_date' => date('Y/m/d g:i:s') // Current date & time
            );

            // FuturePay doesn't allow negative prices (or 0.00 ) which affects discounts
            // with FuturePay doing the calcs, so we will bundle all products into ONE line item with
            // a quantity of ONE and send it that way using the final order total after shipping
            // and discounts are applied
            // all product titles will be comma delimited with their quantities
            $item_names = array();

            if ($order->get_item_count() > 0)
                foreach ($order->get_items() as $item) {

                    $_product = $order->get_product_from_item($item);
                    $title = $_product->get_title();

                    // if variation, insert variation details into product title
                    if ($_product instanceof WC_Product_Variation) {
                        $title .= ' (' . wc_get_formatted_variation($item['variation'], true) . ')';
                    }

                    $item_names[] = $item['qty'] . ' x ' . $title;
                }

            // now add the one line item to the necessary product field arrays
            $data['sku'][] = "Products";
            $data['price'][] = $order->order_total; // futurepay only needs final order amount
            $data['tax_amount'][] = 0;
            $data['description'][] = sprintf(__('Order %s', 'woocommerce'), $order->get_order_number()) . ' = ' . implode(', ', $item_names);
            $data['quantity'][] = 1;

            try {
                $response = wp_remote_post($this->request_url . 'merchant-request-order-token', array(
                    'body' => http_build_query($data),
                    'sslverify' => false,
                        )
                );

                // Convert error to exception
                if (is_wp_error($response)) {
                    if (class_exists('WP_Exception') && $response instanceof WP_Exception) {
                        throw $response;
                    } else {
                        if (!isset($wc_logger) || !$wc_logger instanceof WC_Logger) {
                            $wc_logger = new WC_Logger();
                        }
                        $wc_logger->add('futurepay_response', $response->get_error_message());
                        throw new Exception($response->get_error_message());
                    }
                }

                // Fetch the body from the result, any errors should be caught before proceeding
                $response = trim(wp_remote_retrieve_body($response));

                // we need something to validate the response.  Valid transactions begin with 'FPTK'
                if (!strstr($response, 'FPTK')) {
                    $error_message = (isset(WC_Gateway_FuturePay::$futurepay_errorcodes[$response])) ? WC_Gateway_FuturePay::$futurepay_errorcodes[$response] : __('An unknown error has occured with code = ', 'woocommerce') . $response;
                    $order->add_order_note(sprintf(__('FUTUREPAY: %s', 'woocommerce'), $error_message));
                    wc_add_notice(sprintf(__('FUTUREPAY: %s.  Please try again or select another gateway for your Order.', 'woocommerce'), $error_message), 'error');
                    wp_safe_redirect(get_permalink(wc_get_page_id('checkout')));
                    exit;
                }

                /**
                 *  If we're good to go, haul in FuturePay's javascript and display the payment form
                 *  so that the customer can enter his ID and password
                 */
                echo '<div id="futurepay"></div>';

                echo '<script src="' . $this->request_url . 'cart-integration/' . $response . '"></script>';

                echo '<script type="text/javascript">
				/*<![CDATA[*/
				jQuery(window).load( function() {
					FP.CartIntegration();

					// Need to replace form html
					jQuery("#futurepay").html(FP.CartIntegration.getFormContent());
					FP.CartIntegration.displayFuturePay();
				});

				function FuturePayResponseHandler(response) {
					if (response.error) {
						// TODO: we need something better than this
						alert(response.code + " " + response.message);
					}
					else {
                                                window.location = window.location + "&futurepay="+response.transaction_id;
					}

				}
				/*]]>*/
			</script>';

                echo '<input type="button" class="button alt" name="place_order" id="place_order" value="Place Order" onclick="FP.CartIntegration.placeOrder();" />';
            } catch (Exception $e) {

                echo '<div class="wc_error">' . $e->getMessage() . '</div>';
                if (!isset($wc_logger) || !$wc_logger instanceof WC_Logger) {
                    $wc_logger = new WC_Logger();
                }
                $wc_logger->add('futurepay_error', "FUTUREPAY ERROR: {$e->getMessage()}");
            }
        }

        /**
         *  Check for Futurepay Response
         */
        public function check_response() {

            // Only run the following code if theres a response from futurepay
            if (isset($_GET['futurepay'])) {

                $data = array(
                    'gmid' => $this->gmid,
                    'otxnid' => $_GET['futurepay']
                );

                $response = wp_remote_post($this->request_url . 'merchant-order-verification?', array(
                    'body' => http_build_query($data),
                    'sslverify' => false
                        ));

                $response = json_decode(wp_remote_retrieve_body($response), true);

                if (!empty($response['OrderReference'])) {

                    // Get the order
                    $order_id = substr($response['OrderReference'], 0, strpos($response['OrderReference'], '-'));
                    $order = new WC_Order($order_id);

                    
                    switch (strtoupper($response['OrderStatusCode'])) {
                        case 'ACCEPTED':
                            $order->add_order_note(__('Payment Authorized', 'woocommerce'));
                            $wc_logger = new WC_Logger();
                            $wc_logger->add('futurepay_request', "FuturePay: payment authorized for Order ID: " . $order->id);
                            $order->payment_complete();
                            
                            // was: pay
                            wp_safe_redirect(add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, remove_query_arg('futurepay', get_permalink(wc_get_page_id('thankyou'))))));
                            break;

                        case 'DECLINED':
                            // Hold order
                            $order->update_status('on-hold', sprintf(__('Payment %s via FuturePay.', 'woocommerce'), strtolower($response['OrderStatusCode'])));
                            $wc_logger = new WC_Logger();
                            $wc_logger->add('futurepay_request', "FUTUREPAY: declined order for Order ID: " . $order->id);
                            wc_add_notice("Payment Declined", "errors");
                            break;

                        default:
                            // Hold order
                            $order->update_status('on-hold', sprintf(__('Payment %s via FuturePay.', 'woocommerce'), strtolower($response['OrderStatusCode'])));
                            $wc_logger = new WC_Logger();
                            $wc_logger->add('futurepay_request', "FUTUREPAY: failed order for Order ID: " . $order->id);
                            wc_add_notice("Error in response from FuturePay. Try again later.");
                            break;
                    }
                }
            }
        }
        
        
        public function get_signup_login()
        {
            $html = <<<HTML
<div id="futurepay-merchant-signup-login">
	<div class="futurepay-logo"></div>
	In order to use FuturePay, you must either <a id="do-merchant-signup" href="#">sign-up</a> or <a id="do-merchant-login" href="#">log in</a> to retrieve your API key.
</div>
HTML;
            $js = <<<JS
$('#do-merchant-signup').click(function (e) {
    e.preventDefault();
    if ($('#futurepay-merchant-login').is(':hidden')) {
        $('#futurepay-merchant-signup').fadeIn();
    } else {
        $('#futurepay-merchant-login').fadeOut(function () {
            $('#futurepay-merchant-signup').show();
        });
    }
    return false;
});
$('#do-merchant-login').click(function (e) {
    e.preventDefault();
    if ($('#futurepay-merchant-signup').is(':hidden')) {
        $('#futurepay-merchant-login').fadeIn();
    } else {
        $('#futurepay-merchant-signup').fadeOut(function () {
            $('#futurepay-merchant-login').show();
        });
    }
    return false;
});
JS;
            wc_enqueue_js($js);
            return $html;
        }
        
        public function get_merchant_signup_html($hide=true)
        {
            if ($hide) {
                $style = "display:none;";
            } else {
                $style = "";
            }
            
            $cart_base_country = WC()->countries->get_base_country();
            $cart_base_region = WC()->countries->get_base_state();
            
            // get the initial country list
            $country_options = '';
            foreach (WC()->countries->countries as $code => $name) {
                if ($cart_base_country == $code) {
                    $selected = ' selected="selected"';
                } else {
                    $selected = '';
                }
                
                $country_options .= '<option value="' . $code . '"' . $selected . '>' . $name . '</option>';
            }
            
            // get the initial region list
            $region_options = '';
            foreach (WC()->countries->get_states($cart_base_country) as $code => $name) {
                if ($cart_base_region == $code) {
                    $selected = ' selected="selected"';
                } else {
                    $selected = '';
                }
                $region_options .= '<option value="' . $code . '"' . $selected . '>' . $name . '</option>';
            }
            $js = plugins_url('/', __FILE__) . 'assets/js/merchant-signup.js';
            $api_endpoint = plugins_url('/', __FILE__) . '_ajax/merchant-signup.php';
                                    
            return <<<HTML
<div id="futurepay-merchant-signup" style="{$style}">
    <h3>FuturePay Merchant Signup</h3>
    <div class="fm-group">
        <label class="col-lt">Email Address</label>
        <div class="col-rt"><input class="fm-input" type="text" name="contact_email" value=""/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">First Name</label>
        <div class="col-rt"><input class="fm-input" type="text" name="first_name" value=""/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Last Name</label>
        <div class="col-rt"><input class="fm-input" type="text" name="last_name" value=""/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Phone Number</label>
        <div class="col-rt"><input class="fm-input" type="text" name="main_phone"/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Company Name</label>
        <div class="col-rt"><input class="fm-input" type="text" name="name" value=""/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Website</label>
        <div class="col-rt"><input class="fm-input" type="text" name="website" value=""/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Country</label>
        <div class="col-rt">
            <select name="country_code">{$country_options}</select>
        </div>
    </div>
    <div class="fm-group">
        <label class="col-lt">State</label>
        <div class="col-rt">
            <select name="region_code">{$region_options}</select>
        </div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Address</label>
        <div class="col-rt"><input class="fm-input" type="text" name="address"/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">City</label>
        <div class="col-rt"><input class="fm-input" type="text" name="city"/></div>
    </div>
    <div class="fm-group">
        <label class="col-lt">Zip Code</label>
        <div class="col-rt"><input class="fm-input" type="text" name="zip"/></div>
    </div>
    <div class="fm-group">
        <div class="col-rt"><input class="fm-btn" type="button" name="save" value="Sign up"/></div>
    </div>
<script type="text/javascript">var signup_api_endpoint = '{$api_endpoint}';</script>
<script type="text/javascript" src="{$js}"></script>
</div>
HTML;
        }
        
        public function get_merchant_login_html($hide=true)
        {
            if ($hide) {
                $style = "display:none;";
            } else {
                $style = "";
            }
            $api_endpoint = plugins_url('/', __FILE__) . '_ajax/merchant-login.php';
            $js = plugins_url('/', __FILE__) . 'assets/js/merchant-login.js';
            return <<<HTML
<div id="futurepay-merchant-login" style="{$style}">
    <h3>FuturePay Merchant Login</h3>
    <div class="fm-group">
        <input class="fm-input" type="text" name="user_name" placeholder="Username">
    </div>
    <div class="fm-group">
       <input class="fm-input" type="password" name="password" placeholder="Password">
    </div>
    <div class="fm-group last">
        <input class="fm-btn" type="button" name="save" value="Login">
    </div>
<script type="text/javascript">var login_api_endpoint = '{$api_endpoint}';</script>
<script type="text/javascript" src="{$js}"></script>
</div>
HTML;
        }
        
    }

}
    
function add_futurepay_gateway_class($methods) {
    $methods[] = 'WC_Gateway_FuturePay';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_futurepay_gateway_class');
