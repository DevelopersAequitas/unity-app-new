<?php

declare(strict_types=1);

return [
    'meta' => [
        'api_version' => env('META_WHATSAPP_API_VERSION', env('WHATSAPP_META_API_VERSION', 'v21.0')),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', env('WHATSAPP_META_ACCESS_TOKEN')),
        'business_account_id' => env('META_WHATSAPP_WABA_ID', env('WHATSAPP_BUSINESS_ACCOUNT_ID', '1475250733815899')),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', env('WHATSAPP_PHONE_NUMBER_ID', '1148211571702139')),
        'business_id' => env('WHATSAPP_BUSINESS_ID', '5418283234857525'),
        'app_id' => env('META_WHATSAPP_APP_ID', env('WHATSAPP_META_APP_ID', '1460871381832780')),
        'webhook_verify_token' => env('META_WHATSAPP_VERIFY_TOKEN', env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'peers_unity_meta_verify_token_2026')),
        'app_secret' => env('WHATSAPP_META_APP_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'base_url' => 'https://graph.facebook.com',
        'timeout' => (int) env('WHATSAPP_API_TIMEOUT', 20),
        'retry_times' => (int) env('WHATSAPP_API_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('WHATSAPP_API_RETRY_SLEEP_MS', 500),
    ],

    'sender' => [
        'display_phone' => env('WHATSAPP_SENDER_NUMBER', '+919664620704'),
        'verified_name' => 'Peers Global Business Media Pvt Ltd',
    ],

    'testing' => [
        'test_mode' => (bool) env('WHATSAPP_TEST_MODE', env('APP_ENV') === 'local' || env('APP_ENV') === 'development'),
        'test_phone' => env('WHATSAPP_TEST_PHONE', env('WHATSAPP_TEST_RECIPIENT', '+916353025164')),
        'dry_run' => (bool) env('WHATSAPP_DRY_RUN', false),
        'recipient' => env('WHATSAPP_TEST_RECIPIENT', '+916353025164'),
        'allowed_recipients' => array_filter(array_map('trim', explode(',', env('WHATSAPP_TEST_RECIPIENTS', '+916353025164,+917041151714')))),
        'restrict_to_test_recipient' => (bool) env('WHATSAPP_RESTRICT_TO_TEST_RECIPIENT', false),
    ],
];
