<?php

return [

    'jakim_calendar' => [
        'url' => env('JAKIM_CALENDAR_URL', 'https://www.e-solat.gov.my/index.php'),
    ],

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

    'billplz' => [
        'enabled' => env('BILLPLZ_ENABLED', false),
        'secret_key' => env('BILLPLZ_SECRET_KEY'),
        'base_url' => env('BILLPLZ_BASE_URL', 'https://www.billplz-sandbox.com/api'),
        'x_signature_key' => env('BILLPLZ_X_SIGNATURE_KEY'),
        'collection_id' => env('BILLPLZ_COLLECTION_ID'),
        'duitnow_qr_enabled' => env('BILLPLZ_DUITNOW_QR_ENABLED', false),
    ],

    'wabot' => [
        'token' => env('WABOT_API_TOKEN'),
        'inbound_api_key' => env('WABOT_INBOUND_API_KEY'),
        'royalicious_automation_probe_token' => env('WABOT_RYC_AUTOMATION_PROBE_TOKEN'),
        'base_url' => env('WABOT_BASE_URL', 'https://app.wabot.my'),
        'channels' => [
            'kingkadkahwin' => ['instance_id' => env('WABOT_KKK_INSTANCE_ID'), 'access_token' => env('WABOT_KKK_ACCESS_TOKEN')],
            'destinasi-raja-sehari' => ['instance_id' => env('WABOT_DRS_INSTANCE_ID'), 'access_token' => env('WABOT_DRS_ACCESS_TOKEN')],
            'cetaking' => ['instance_id' => env('WABOT_CK_INSTANCE_ID'), 'access_token' => env('WABOT_CK_ACCESS_TOKEN')],
            'royalicious' => ['instance_id' => env('WABOT_RYC_INSTANCE_ID'), 'access_token' => env('WABOT_RYC_ACCESS_TOKEN')],
        ],
    ],

];
