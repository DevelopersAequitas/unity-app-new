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

    'zoho' => [
        'webhook_token' => env('ZOHO_WEBHOOK_TOKEN'),
    ],

    'zoho_payments' => [
        'base_url' => env('ZOHO_PAYMENTS_BASE_URL', 'https://payments.zoho.in'),
        'api_base_url' => env('ZOHO_PAYMENTS_API_BASE_URL', 'https://payments.zoho.in/api/v1'),
        'payment_link_endpoint' => env('ZOHO_PAYMENTS_PAYMENT_LINK_ENDPOINT', '/api/v1/payment_links'),
        'payment_session_endpoint' => env('ZOHO_PAYMENTS_PAYMENT_SESSION_ENDPOINT', '/api/v1/payment_sessions'),
        'accounts_base_url' => env('ZOHO_ACCOUNTS_BASE_URL', 'https://accounts.zoho.in'),
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
        'webhook_secret' => env('ZOHO_PAYMENTS_WEBHOOK_SECRET'),
        'success_url' => env('ZOHO_PAYMENTS_SUCCESS_URL'),
        'failure_url' => env('ZOHO_PAYMENTS_FAILURE_URL'),
        'currency' => env('ZOHO_PAYMENTS_CURRENCY', 'INR'),
    ],

    'zoho_books' => [
        'base_url' => env('ZOHO_BOOKS_BASE_URL', 'https://www.zohoapis.in/books/v3'),
        'organization_id' => env('ZOHO_BOOKS_ORGANIZATION_ID'),
    ],

    'members_with_circles' => [
        // Fixed token for GET /api/v1/members-with-circles and /api/v1/members-with-circles/{identifier}
        'fixed_token' => env('MEMBERS_WITH_CIRCLES_FIXED_TOKEN', env('MEMBERS_LIST_FIXED_TOKEN', '302|cO0VMR2dmr9j8c3JtIU9dfkuZfSfvzaCCF1GVxJAdc6fdd2d')),
    ],

];
