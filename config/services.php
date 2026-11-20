<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'endpoint' => env('STARSENDER_ENDPOINT') ?: (env('WHATSAPP_ENDPOINT') ?: 'https://api.starsender.online/api/send'),
        'token' => env('STARSENDER_API_KEY') ?: env('WHATSAPP_TOKEN', ''),
        'auth_header' => env('STARSENDER_AUTH_HEADER', 'Authorization'),
        'auth_prefix' => env('STARSENDER_AUTH_PREFIX', 'Bearer'),
        'timeout' => (int) env('STARSENDER_TIMEOUT', 20),
    ],

];
