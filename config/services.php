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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'secureivs' => [
        'client_id' => env('SECUREIVS_CLIENT_ID', '9b3tvZGZStRrcTsfTZifsjgkaUTER3aOKaXjshkO'),
        'api_key' => env('SECUREIVS_API_KEY', 'GFUEGLzTzF6UHByZ1'),
        'auth_url' => env('SECUREIVS_AUTH_URL', 'https://customer-api.secureivs.eu'),
        'api_url' => env('SECUREIVS_API_URL', 'https://api.secureivs.eu/api/v2'),
        'redirect_uri' => env('SECUREIVS_REDIRECT_URI'),
    ],

];
