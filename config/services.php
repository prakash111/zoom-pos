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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],

    'envato' => [
        'api_token' => env('ENVATO_API_TOKEN'),
        'item_id' => env('ENVATO_ITEM_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | License Server
    |--------------------------------------------------------------------------
    |
    | The license server location is fixed for this product — it is NOT taken
    | from the environment. Only the shared secret (which must stay private) and
    | the request timeout are configurable via .env.
    |
    */
    'license_server' => [
        'driver' => 'custom',
        'url' => 'https://license.zoomnearby.com',
        'store_url' => 'https://license.zoomnearby.com/buy.php',
        'secret' => env('LICENSE_SERVER_SECRET'),
        'timeout' => (int) env('LICENSE_SERVER_TIMEOUT', 10),
    ],

];
