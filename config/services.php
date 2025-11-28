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

    'hrms' => [
        'secret' => env('HRMS_SECRET', 'DDThkqkxlOYQzpZUbqnfEGir5mWHV5mY'),
        'iv' => env('HRMS_IV', 'ykDWpfWyXXjTY0bg'),
        'hmac_secret' => env('HRMS_HMAC_SECRET', '1Po/Rx7oUnNzy9QZ7NZJjA=='),
        'hmac_secret_me' => env('HRMS_HMAC_SECRET_ME', '1Po/Pt8oRnNzy9QZ7NZJjA=='),
        'api_secret_token' => env('HRMS_API_SECRET_TOKEN', 'WBHOUSING12#$'),
        'uat_hrms_url' => env('HRMS_UAT_URL', 'https://uat.wbifms.gov.in'),
    ],

];
