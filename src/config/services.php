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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'azure_devops' => [
        'organization_url' => env('ORGANIZATION_URL', ''),
        'pat' => env('PERSONAL_ACCESS_TOKEN', ''),
        'project' => env('ADO_PROJECT'),
        'api_version' => env('ADO_API_VERSION', '7.0'),
        // Optional CSV strings; the service will parse to arrays
        'area_paths' => env('ADO_AREA_PATHS'),
        'work_item_types' => env('ADO_WORK_ITEM_TYPES'),
        'status_blacklist' => env('ADO_STATUS_BLACKLIST'),
    ],

];
