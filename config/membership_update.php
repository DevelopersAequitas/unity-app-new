<?php

return [
    'logo_url' => env('PEERS_GLOBAL_LOGO_URL', 'https://unity.peersglobal.com/wp-content/uploads/2025/08/peersglobal_white-removebg-preview.png'),
    'cc_email' => env('MEMBERSHIP_UPDATE_CC_EMAIL'),
    'attachment_path' => env('MEMBERSHIP_UPDATE_ATTACHMENT_PATH', storage_path('app/public/membership/membership-update.pdf')),
    'attachment_name' => env('MEMBERSHIP_UPDATE_ATTACHMENT_NAME', 'membership-update.pdf'),
];
