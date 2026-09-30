<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', 'mock-client-id'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', 'mock-secret'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway (order tracking messages, phone OTP, phone password reset)
    |--------------------------------------------------------------------------
    |
    | 'log' (default) just writes the message to the log so this works out of
    | the box in local/dev without a real gateway. Switch SMS_DRIVER to
    | 'sparrow' and fill in the credentials below to send real SMS in Nepal.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'sparrow' => [
            'token' => env('SPARROW_SMS_TOKEN'),
            'from' => env('SPARROW_SMS_FROM', 'DarkStore'),
            'url' => env('SPARROW_SMS_URL', 'https://api.sparrowsms.com/v2/sms/'),
        ],
    ],

];
