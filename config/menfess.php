<?php

return [
    'max_characters' => (int) env('MENFESS_MAX_CHARACTERS', 1000),
    'instagram_free_posts_per_day' => (int) env('INSTAGRAM_FREE_POSTS_PER_DAY', 2),
    'instagram_free_cooldown_minutes' => (int) env('INSTAGRAM_FREE_COOLDOWN_MINUTES', 60),
    'global_free_posts_per_day' => (int) env('GLOBAL_FREE_POSTS_PER_DAY', 10),
    'publication_token_costs' => [
        'instagram' => (int) env('INSTAGRAM_PUBLICATION_TOKENS', 2),
        'x' => (int) env('X_PUBLICATION_TOKENS', 1),
    ],
    'publisher_mode' => env('PUBLISHER_MODE', 'simulation'),
    'brand_name' => env('MENFESS_BRAND_NAME', 'Ruang Kirim'),
    'social_username' => env('MENFESS_SOCIAL_USERNAME', '@ruangkirim'),
    'canvas_background' => env('MENFESS_CANVAS_BACKGROUND', '#F6DDE8'),
    'canvas_backgrounds' => array_values(array_filter(array_map('trim', explode(',', (string) env('MENFESS_CANVAS_BACKGROUNDS', '#F6DDE8,#DCEBFA,#DDF2E5'))))),
    'canvas_accent' => env('MENFESS_CANVAS_ACCENT', '#DCEBFA'),
    'node_binary' => env('NODE_BINARY', 'node'),
];
