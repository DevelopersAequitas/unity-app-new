<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),

    'attachment_1_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_PATH', storage_path('app/private/membership-welcome/welcome-kit.pdf')),
    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-kit.pdf'),

    'attachment_2_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_PATH', storage_path('app/private/membership-welcome/membership-benefits.pdf')),
    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'membership-benefits.pdf'),

    'banner_file_id' => env('MEMBERSHIP_WELCOME_BANNER_FILE_ID'),
    'banner_url' => env('MEMBERSHIP_WELCOME_BANNER_URL'),
    'support_email' => env('MEMBERSHIP_SUPPORT_EMAIL', 'pravin@peersunity.com'),
];
