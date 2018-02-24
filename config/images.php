<?php

return [
    'disk' => env('IMAGES_DISK', 'local'),
    'default_thumbnail_format' => '320x320',
    'allowed_extensions' => ['jpg','jpeg','png'],
    'allowed_ratios' => [],
    'allowed_formats' => ['800x600','320x320'],
    'watermark_width' => '65%',
    'watermark_height' => '40%',
    'watermark_path' => resource_path('assets/watermark.png'),
    'cache_path' => 'cache/',
    'cache_extension' => 'jpg',
    'cache_age' => 30 * 86400, // in seconds
    // Check http://image.intervention.io/api/insert for position options
    'watermark_position' => 'center',
    'image_quality' => 90,
];
