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
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/google/callback'),
    ],

    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
        'instagram_access_token' => env('META_INSTAGRAM_ACCESS_TOKEN'),
        'instagram_token_expires_at' => env('META_INSTAGRAM_ACCESS_TOKEN_EXPIRES_AT'),
    ],

    'x' => [
        // OAuth 1.0a user context: API Key / API Key Secret from X Developer Portal
        // "Keys and tokens". X_CLIENT_ID / X_CLIENT_SECRET remain aliases only.
        'consumer_key' => env('X_API_KEY', env('X_CLIENT_ID')),
        'consumer_secret' => env('X_API_KEY_SECRET', env('X_CLIENT_SECRET')),
        'access_token' => env('X_ACCESS_TOKEN'),
        'access_token_secret' => env('X_ACCESS_TOKEN_SECRET'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],
];
