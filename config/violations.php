<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Violation Detection Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how attendance violations are detected and
    | classified. Grace periods and thresholds can be customized per tenant.
    |
    */

    'late_arrival_grace_minutes' => env('VIOLATION_LATE_ARRIVAL_GRACE', 15),
    'early_departure_grace_minutes' => env('VIOLATION_EARLY_DEPARTURE_GRACE', 15),
    'max_break_minutes' => env('VIOLATION_MAX_BREAK_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Severity Thresholds
    |--------------------------------------------------------------------------
    |
    | Define thresholds for violation severity levels based on deviation.
    |
    */

    'severity_thresholds' => [
        'late_arrival' => [
            'minor' => 30,    // <= 30 minutes
            'moderate' => 60, // 31-60 minutes
            // > 60 minutes = major
        ],
        'early_departure' => [
            'minor' => 30,
            'moderate' => 60,
        ],
        'extended_break' => [
            'minor' => 15,    // <= 15 minutes over max
            'moderate' => 30, // 16-30 minutes over max
            // > 30 minutes over max = major
        ],
    ],
];
