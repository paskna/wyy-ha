<?php

return [
    'matching' => [
        'exact_threshold' => (float) env('WINE_MATCH_EXACT_THRESHOLD', 0.88),
        'candidate_threshold' => (float) env('WINE_MATCH_CANDIDATE_THRESHOLD', 0.72),
        'weights' => [
            'producer' => 0.30,
            'wine_name' => 0.30,
            'vintage' => 0.15,
            'region' => 0.10,
            'visual' => 0.10,
            'other' => 0.05,
        ],
    ],
    'taste_profile' => [
        'first_signal_at' => 3,
        'recommendation_at' => 5,
        'mature_profile_at' => 10,
    ],
    'cache_days' => (int) env('WINE_ENRICHMENT_CACHE_DAYS', 90),
];
