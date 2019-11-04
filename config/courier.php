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
];