<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The default Courier Driver
    |--------------------------------------------------------------------------
    |
    | The default sms driver to use as a fallback when no driver is specified
    | while using the Courier component.
    |
    */
    'default' => env('COURIER_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Nexmo Driver Configuration
    |--------------------------------------------------------------------------
    |
    | We specify key, secret, and the number messages will be sent from.
    |
    */
//    'nexmo' => [
//        'key' => env('NEXMO_KEY', ''),
//        'secret' => env('NEXMO_SECRET', ''),
//        'from' => env('NEXMO_COURIER_FROM', '')
//    ],

    /*
    |--------------------------------------------------------------------------
    | GDex Driver Configuration
    |--------------------------------------------------------------------------
    |
    | Specify GDex account number and API token
    |
    */
    'gdex' => [
        'test_mode' => env('GDEX_TEST_MODE', false),
        'api_token' => env('GDEX_API_TOKEN', ''),
        'subscription_key' => env('GDEX_SUBSCRIPTION_KEY', ''),
        'account_no' => env('GDEX_ACCOUNT_NO', ''),
    ],
    'best-express' => [
        'test_mode' => env('BESTEXPRESS_TEST_MODE', false),
        'partner_id' => env('BESTEXPRESS_PARTNER_ID', ''),
        'partner_key' => env('BESTEXPRESS_PARTNER_KEY', ''),
    ],
    'dhl-ecommerce' => [
        'test_mode' => env('DHL_ECOMMERCE_TEST_MODE', false),
        'client_id' => env('DHL_ECOMMERCE_CLIENT_ID', ''),
        'password' => env('DHL_ECOMMERCE_PASSWORD', ''),
        'account_soldto' => env('DHL_ECOMMERCE_SOLDTO_ACCOUNT', ''),
        'account_pickup' => env('DHL_ECOMMERCE_PICKUP_ACCOUNT', ''),
        'shipment_prefix' => env('DHL_ECOMMERCE_SHIPMENT_PREFIX', ''),
    ],
];
