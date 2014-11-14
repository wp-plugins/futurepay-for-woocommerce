<?php

define('USE_FUTUREPAY_SANDBOX', false);

$expectedFields = array(
    'ajax',
    'contact_email',
    'first_name',
    'last_name',
    'main_phone',
    'name',
    'website',
    'country_code',
    'region_code',
    'address',
    'city',
    'zip',
);
$requestArray = array();
foreach ($expectedFields as $fieldName) {
    if (!isset($_POST[$fieldName])) {
        // the request doesn't meet the criteria, so we'll pretend like
        // there isn't a script here.
        header("HTTP/1.1 404 Not Found");
        exit;
    } else {
        // populate an array containing the request
        $requestArray[$fieldName] = $_POST[$fieldName];
    }
}


if (count($expectedFields) === count($requestArray)) {
    
    set_time_limit(0);
    ini_set('max_execution_time', 300); // 5 minutes, which is overkill.
    
    if (USE_FUTUREPAY_SANDBOX) {
        $requestHost = 'demo.futurepay.com';
    } else {
        $requestHost = 'api.futurepay.com';
    }
    
    unset($requestArray['ajax']);
    $requestArray['type'] = 'signup';
    $queryString = http_build_query($requestArray);
    $requestUrl = "https://{$requestHost}/remote/merchant-request-key?{$queryString}";

    if (filter_var($requestUrl, FILTER_VALIDATE_URL)) {
        
        $ch = curl_init($requestUrl);
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
            'message' => "An unknown error occured. Please try again later.",
        ));
    }
} else {
    header("HTTP/1.1 404 Not Found");
    exit;
}
