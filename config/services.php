<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | M.A.P.S. Machine Learning API
    |--------------------------------------------------------------------------
    */

    'maps_ml' => [
        'base_url' => env(
            'MAPS_ML_API_URL',
            'http://127.0.0.1:8000'
        ),
    ],

  'sms_gateway' => [
    'url' => env('SMS_GATEWAY_URL'),
    'username' => env('SMS_GATEWAY_USERNAME'),
    'password' => env('SMS_GATEWAY_PASSWORD'),
    'sim_number' => env('SMS_GATEWAY_SIM_NUMBER', 1),
    'timeout' => env('SMS_GATEWAY_TIMEOUT', 15),
],

];