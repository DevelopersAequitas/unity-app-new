<?php

return [
    'register_url' => env('REFERRAL_REGISTER_URL', rtrim((string) env('APP_URL', 'https://peersunity.com'), '/').'/register'),
    'query_param' => env('REFERRAL_QUERY_PARAM', 'ref'),
    'campaign' => [
        'show_floating_badge' => (bool) env('REFERRAL_SHOW_FLOATING_BADGE', true),
        'auto_open_badge_screen' => (bool) env('REFERRAL_AUTO_OPEN_BADGE_SCREEN', true),
        'floating_badge_delay_seconds' => (int) env('REFERRAL_FLOATING_BADGE_DELAY_SECONDS', 10),
        'auto_open_delay_seconds' => (int) env('REFERRAL_AUTO_OPEN_DELAY_SECONDS', 30),
        'reward_title' => (string) env('REFERRAL_REWARD_TITLE', 'Refer 10 Friends & Win The Book'),
        'reward_subtitle' => (string) env('REFERRAL_REWARD_SUBTITLE', "Get 'The 5 Levels of Leadership' physical book for your next level of leadership!"),
        'reward_image_url' => env('REFERRAL_REWARD_IMAGE_URL', null),
        'show_send_invite_button' => (bool) env('REFERRAL_SHOW_SEND_INVITE_BUTTON', true),
    ],
];
