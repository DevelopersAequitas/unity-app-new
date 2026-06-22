<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),

    'from_email' => env('MEMBERSHIP_WELCOME_FROM_EMAIL', 'Pravin@peersglobal.com'),
    'from_name' => env('MEMBERSHIP_WELCOME_FROM_NAME', 'Peers Global Unity'),
    'cc_email' => env('MEMBERSHIP_WELCOME_CC_EMAIL'),

    'attachment_1_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_1'),
    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-file-1.pdf'),

    'attachment_2_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_2'),
    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'welcome-file-2.pdf'),
];
