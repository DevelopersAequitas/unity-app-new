<?php

declare(strict_types=1);

return [
    'latest' => env('APP_LATEST_VERSION', '1.8.0'),
    'min_required' => env('APP_MIN_VERSION', '1.2.0'),
    'update_type' => env('APP_UPDATE_TYPE', 'optional'),
    'is_active' => env('APP_VERSION_ACTIVE', true),
];
