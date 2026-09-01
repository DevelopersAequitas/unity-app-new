<?php

return [
    'keep_original' => env('MEDIA_KEEP_ORIGINAL', false),

    'processing' => [
        'mode' => env('MEDIA_PROCESSING_MODE', 'sync'), // sync or queue
    ],

    'image' => [
        'max_width' => env('MEDIA_IMAGE_MAX_WIDTH', 1600),
        'max_height' => env('MEDIA_IMAGE_MAX_HEIGHT', 1600),
        'quality' => env('MEDIA_IMAGE_QUALITY', 80),
        'format_preference' => env('MEDIA_IMAGE_FORMAT_PREFERENCE', 'webp'),
        'thumbnail_max_width' => env('MEDIA_THUMB_MAX_WIDTH', 400),
    ],

    'video' => [
        'max_width' => env('MEDIA_VIDEO_MAX_WIDTH', 1280),
        'crf' => env('MEDIA_VIDEO_CRF', 28),
        'preset' => env('MEDIA_VIDEO_PRESET', 'ultrafast'),
        'audio_bitrate' => env('MEDIA_VIDEO_AUDIO_BITRATE', '128k'),
        'poster_max_width' => env('MEDIA_VIDEO_POSTER_WIDTH', 800),
        'poster_second' => env('MEDIA_VIDEO_POSTER_SECOND', 1),
        'timeout' => env('MEDIA_VIDEO_TIMEOUT', 600),
    ],
];
