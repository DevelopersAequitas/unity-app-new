<?php

return [
    // Must match a valid value from circle_member_status_enum in PostgreSQL.
    'member_joined_status' => env('CIRCLE_MEMBER_JOINED_STATUS', 'approved'),

    /*
    |--------------------------------------------------------------------------
    | Build Your Own Circle Configuration
    |--------------------------------------------------------------------------
    | Minimum nominees required for a peer to submit a "Build Your Own Circle"
    | request. This value is the recommended minimum. Admins can update this
    | via the admin API.
    */
    'build_your_own' => [
        'minimum_nominees' => (int) env('CIRCLE_BUILD_MINIMUM_NOMINEES', 40),
    ],
];
