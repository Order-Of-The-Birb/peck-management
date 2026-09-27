<?php

return [
    'squadron_name' => env('SQUADRON_NAME', 'Order Of The Birb'),
    'squadron_id' => env('SQUADRON_ID'),
    'thunderapi_base_url' => env('THUNDERAPI_BASE_URL'),
    'refresh_schedule' => env('REFRESH_SCHEDULE', '0:00'),
    'auto_refresh_enabled' => env('AUTO_REFRESH_ENABLED', env('APP_ENV') !== 'testing'),
    'auto_refresh' => [
        'lock_key' => 'peck:auto-refresh:lock',
        'lock_minutes' => (int) env('AUTO_REFRESH_LOCK_MINUTES', 10),
        'last_attempted_date_key' => 'peck:auto-refresh:last-attempted-date',
        'last_successful_date_key' => 'peck:auto-refresh:last-successful-date',
    ],
    'thunderapi_refresh' => [
        'batch_size' => (int) env('THUNDERAPI_REFRESH_BATCH_SIZE', 4),
        'refresh_after_hours' => (int) env('THUNDERAPI_REFRESH_AFTER_HOURS', 23),
    ],
];
