<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),
    'logo_url' => env('PEERS_GLOBAL_LOGO_URL', 'https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png'),
    'cc_email' => env('MEMBERSHIP_WELCOME_CC_EMAIL', 'pravin@preesglobal.com'),
    'membership_welcome_cc_email' => env('MEMBERSHIP_WELCOME_CC_EMAIL', 'pravin@preesglobal.com'),

    'membership_welcome_attachment_path_1' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_1', env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH', storage_path('app/public/membership/welcome.pdf'))),
    'membership_welcome_attachment_name_1' => env('MEMBERSHIP_WELCOME_ATTACHMENT_NAME_1', env('MEMBERSHIP_WELCOME_ATTACHMENT_NAME', 'welcome.pdf')),

    'membership_welcome_attachment_path_2' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_2', env('MEMBERSHIP_WELCOME_ATTACHMENT_2_PATH', storage_path('app/private/membership-welcome/membership-benefits.pdf'))),
    'membership_welcome_attachment_name_2' => env('MEMBERSHIP_WELCOME_ATTACHMENT_NAME_2', env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'membership-benefits.pdf')),

    'attachment_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH', storage_path('app/public/membership/welcome.pdf')),
    'attachment_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_NAME', 'welcome.pdf'),

    'attachment_1_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_PATH', storage_path('app/private/membership-welcome/welcome-kit.pdf')),
    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-kit.pdf'),

    'attachment_2_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_PATH', storage_path('app/private/membership-welcome/membership-benefits.pdf')),
    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'membership-benefits.pdf'),
];
