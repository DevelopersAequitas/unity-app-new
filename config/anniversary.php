<?php

declare(strict_types=1);

return [
    'canvas' => [
        'width' => 1080,
        'height' => 1080,
    ],
    'photo' => [
        'center_x' => 540,
        'center_y' => 555, // Adjusted downward by 18px as requested
        'radius' => 192,
    ],
    'avatar' => [
        'size' => 370, // Increased size by 9% (from 340 to 370) to sit naturally inside the rings
    ],
    'name' => [
        'y' => 820, // Position strictly unchanged
        'font_size' => 44,
        'color' => [18, 58, 112],
        'max_width' => 900,
    ],
    'business' => [
        'y' => 910, // Position strictly unchanged
        'font_size' => 26,
        'color' => [18, 58, 112],
        'color_red' => [197, 48, 48],
        'max_width' => 950,
    ],
];
