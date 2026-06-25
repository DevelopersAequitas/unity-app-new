<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),

    'welcome_email_attachment_1_file_id' => env('WELCOME_EMAIL_ATTACHMENT_1_FILE_ID'),
    'welcome_email_attachment_2_file_id' => env('WELCOME_EMAIL_ATTACHMENT_2_FILE_ID'),
    'attachment_1_file_id' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_FILE_ID'),
    'attachment_2_file_id' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_FILE_ID'),

    'attachment_1_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_PATH', storage_path('app/private/membership-welcome/welcome-kit.pdf')),
    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-kit.pdf'),

    'attachment_2_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_PATH', storage_path('app/private/membership-welcome/membership-benefits.pdf')),
    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'membership-benefits.pdf'),
];
