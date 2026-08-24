<?php

declare(strict_types=1);

return [
    'latest' => env('APP_LATEST_VERSION', '2.0.0'),
    'min_required' => env('APP_MIN_VERSION', '1.9.0'),
    'update_type' => env('APP_UPDATE_TYPE', 'force'),
];
