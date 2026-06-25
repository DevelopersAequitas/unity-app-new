<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),

    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-kit.pdf'),
    'attachment_1_file_id' => env('WELCOME_EMAIL_ATTACHMENT_1_FILE_ID', env('MEMBERSHIP_WELCOME_ATTACHMENT_1_FILE_ID')),

    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'membership-benefits.pdf'),
    'attachment_2_file_id' => env('WELCOME_EMAIL_ATTACHMENT_2_FILE_ID', env('MEMBERSHIP_WELCOME_ATTACHMENT_2_FILE_ID')),

    'banner_file_id' => env('MEMBERSHIP_WELCOME_BANNER_FILE_ID'),
    'banner_url' => env('MEMBERSHIP_WELCOME_BANNER_URL'),
    'support_email' => env('MEMBERSHIP_SUPPORT_EMAIL', 'pravin@peersunity.com'),
];
