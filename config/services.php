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

    'chatwoot' => [
        'url' => env('CHATWOOT_URL', 'https://chatwoot.servilutioncrm.cloud'),
        'account_id' => env('CHATWOOT_ACCOUNT_ID', '1'),
        'api_token' => env('CHATWOOT_API_TOKEN'),
        'inbox_id' => (int) env('CHATWOOT_INBOX_ID', 1),
        'webhook_token' => env('CHATWOOT_WEBHOOK_TOKEN'),
        'proxy_verify_tls' => env('CHATWOOT_PROXY_VERIFY_TLS', true),
    ],

    'chatbot' => [
        'webhook_url' => env('CHATBOT_WEBHOOK_URL', 'https://cobrocartera-n8n.hrymiz.easypanel.host/webhook/messages-customers'),
        'webhook_token' => env('CHATBOT_WEBHOOK_TOKEN'),
    ],

];
