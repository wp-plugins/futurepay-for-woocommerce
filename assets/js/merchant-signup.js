var $ = jQuery.noConflict();


function validate_fields(data) {
    
    var valid = true;
    var messages = {};
    
    // check for blank fields
    var blanks = false;
    $.each(data, function (k, v) {
        if (v.length < 1) {
            blanks = true;
            return;
        }
    });
    if (blanks) {
        alert("Failed to create a FuturePay account. All fields are required!");
        return false;
    }
    
    // contact email
    if (!data.contact_email.match(/.+\@.+\..+/)) {
        messages.contact_email = "The supplied email address is not valid.";
        valid = false;
    }
    
    // phone number
    if (!data.main_phone.match(/[0-9]{3}-[0-9]{3}-[0-9]{4}/)
            && !data.main_phone.match(/[0-9]{10}/)) {
        console.log(data.main_phone);
        // try with ########## instead of ###-###-####
        messages.main_phone = "The supplied phone number is not a valid 10-digit phone number.";
        valid = false;
    }
    
    // zip/postal
    if (data.country_code == 'US' && !data.zip.match(/[0-9]{5}/)) {
        messages.zip = "The supplied ZIP code is not a valid ZIP code.";
        valid = false;
    } else if (data.country_code == 'CA' && !data.zip.match(/[A-Za-z0-9]{3}[- ]{0,1}[A-Za-z0-9]{3}/)) {
        messages.zip = "The supplied postal code is not a valid postal code.";
        valid = false;
    }
    
    // the form is valid!
    if (valid) {
        return true;
    } else {
        // no it's not! spit out error messages
        var msg_string = 'Could not submit the merchant sign-up form. One or more fields contain incorrect data:\n\n';
        $.each(messages, function (k, v) {
            msg_string += v + '\n';
        });
        alert(msg_string);
        return false;
    }
    
}

$(document).ready(function () {
    
    // when the country is changed, populate the region field with the regions
    // for that country
    $('#futurepay-merchant-signup').find(':input[name=country_code]').change(function () {
        $.get(ajaxurl, {
            action: 'get_country_regions', 
            country: $(this).val()
        }, function (data) {
            $('#futurepay-merchant-signup').find(':input[name=region_code]').find('option').remove();
            if (data.cnt > 0) {
                $('#futurepay-merchant-signup')
                        .find(':input[name=region_code]')
                        .replaceWith('<select name="region_code"></select>');
                delete data.cnt;
                $.each(data, function (country_code, country_name) {
                    $('<option></option>')
                            .attr('value', country_code)
                            .html(country_name)
                            .appendTo($('#futurepay-merchant-signup').find(':input[name=region_code]'));
                });
            } else {
                // switch to a text field because we don't have any regions
                // for this country
                $('#futurepay-merchant-signup')
                        .find(':input[name=region_code]')
                        .replaceWith('<input type="text" name="region_code" class="fm-input"/>');
            }
        }, 'json');
    });
    

    $('#futurepay-merchant-signup').find(':input[name=save]').click(function (e) {
        e.preventDefault();
        
        // @todo: let the user know the page is doing something so they don't
        // get frustrated.
        
        var data = { ajax: 1 };
        $('#futurepay-merchant-signup').find(':input[name!=save]').each(function () {
            data[$(this).attr('name')] = $(this).val();
        });

        // put the phone number together
        if (data.main_phone.match(/[0-9]{10}/)) {
            data['main_phone'] = data.main_phone.replace(/([0-9]{3})([0-9]{3})([0-9]{4})/, "$1-$2-$3");
        }

        if (validate_fields(data)) {
            $.post(signup_api_endpoint, data, function (r) {
                if (r.error == 0) {
                    $('#futurepay-merchant-signup').fadeOut(function () {
                        $('#woocommerce_futurepay_futurepay_gmid')
                                .val(r.key)
                                .focus();
                    });
                }
                alert(r.message);
            }, 'json');
        }
        
        return false;
    });
    
});