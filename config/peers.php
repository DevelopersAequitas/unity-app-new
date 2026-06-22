<?php

return [
    'membership_update_from_email' => env('MEMBERSHIP_UPDATE_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
    'membership_update_from_name' => env('MEMBERSHIP_UPDATE_FROM_NAME', 'Peers Global Unity'),
    'membership_update_reply_to_email' => env('MEMBERSHIP_UPDATE_REPLY_TO_EMAIL', 'pravin@peersunity.com'),
    'membership_update_cc_email' => env('MEMBERSHIP_UPDATE_CC_EMAIL', 'pravin@peersunity.com'),
    'membership_update_attachment_path' => env('MEMBERSHIP_UPDATE_ATTACHMENT_PATH'),

    'membership_welcome_from_email' => env('MEMBERSHIP_WELCOME_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
    'membership_welcome_from_name' => env('MEMBERSHIP_WELCOME_FROM_NAME', 'Peers Global Unity'),
    'membership_welcome_reply_to_email' => env('MEMBERSHIP_WELCOME_REPLY_TO_EMAIL', 'pravin@peersunity.com'),
    'membership_welcome_cc_email' => env('MEMBERSHIP_WELCOME_CC_EMAIL', 'pravin@peersunity.com'),
    'membership_welcome_attachment_path_1' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_1'),
    'membership_welcome_attachment_path_2' => env('MEMBERSHIP_WELCOME_ATTACHMENT_PATH_2'),
];
