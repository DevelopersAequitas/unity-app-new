<?php

return [
    'statuses' => [
        'free_trial_peer',
        'free_peer',
        'Only Unity Peer',
        'Circle Peer',
        'Multi Circle Peer',
        'Charter Peer',
        'Industry Advisor',
        'Charter Investor',
        'Circle Founder',
        'Circle Director',
        'Board Advisor',
    ],
    'trial_days' => [
        'default' => (int) env('MEMBERSHIP_TRIAL_DAYS', 3),
        'referral' => (int) env('MEMBERSHIP_REFERRAL_TRIAL_DAYS', 7),
    ],
    'pro_required_messages' => [
        'testimonial' => 'Upgrade to Pro to give testimonials to your peers.',
    ],
];
