<?php
define(USE_FUTUREPAY_SANDBOX, false);


if (isset($_POST['ajax'])
        && $_POST['ajax'] == '1'
        && isset($_POST['user_name'])
        && isset($_POST['password'])) {
       
    // we don't want this script to timeout. some webhosts are very slow.
    set_time_limit(0);
    ini_set('max_execution_time', 300); // 5 minutes, which is overkill.
    
    if (USE_FUTUREPAY_SANDBOX) {
        $request_host = 'demo.futurepay.com';
    } else {
        $request_host = 'api.futurepay.com';
    }
    
    $_POST['user_name'] = urlencode($_POST['user_name']);
    $_POST['password'] = urlencode($_POST['password']);
    
    $request_url = "https://{$request_host}/remote/merchant-request-key?type=retrieve"
            . "&user_name={$_POST['user_name']}"
            . "&password={$_POST['password']}";
    
    if (filter_var($request_url, FILTER_VALIDATE_URL)) {
        
        $ch = curl_init($request_url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WooCommerce/FuturePay Plugin v1.0');
        $result = curl_exec($ch);
        // if the connection failed, report it back to the browser, otherwise
        // pass the json object back.
        if ($result !== false) {
            echo $result;
        } else {
            echo json_encode(array(
                'error' => 1,
                'message' => curl_error($ch),
            ));
        }
        curl_close($ch);
    } else {
        echo json_encode(array(
            'error' => 1,
            'message' => "API endpoint URL didn't validate properly.",
        ));
    }
    
} else {
    // shhhh.. this isn't here!
    header("HTTP/1.1 404 Not Found");
    exit;
}