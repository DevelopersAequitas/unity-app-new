<?php

return [
    'enabled' => env('MEMBERSHIP_WELCOME_EMAIL_ENABLED', true),

    'attachment_1_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_1'),
    'attachment_1_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_1_NAME', 'welcome-file-1.pdf'),

    'attachment_2_path' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_2'),
    'attachment_2_name' => env('MEMBERSHIP_WELCOME_ATTACHMENT_2_NAME', 'welcome-file-2.pdf'),
];
