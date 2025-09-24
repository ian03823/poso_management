<?php

return [
    'driver' => env('OTP_DRIVER', 'log'), // log | semaphore | twilio

    'semaphore' => [
        'key'    => env('SEMAPHORE_API_KEY'),
        'sender' => env('SEMAPHORE_SENDER', 'POSO'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],
    'gmail_webapp' => [
        'url'    => env('GMAIL_WEBAPP_URL'),
        'secret' => env('GMAIL_WEBAPP_SECRET'),
    ],
];