<?php

return [
    'squadron_name' => env('PECK_SQUADRON_NAME', 'Order Of The Birb'),
    'thunderinsights_base_url' => env('PECK_THUNDERINSIGHTS_BASE_URL', 'https://api.thunderinsights.dk/v1'),
    'refresh_schedule' => env('PECK_REFRESH_SCHEDULE', '0:00'),
    'auto_refresh_enabled' => env('PECK_AUTO_REFRESH_ENABLED', env('APP_ENV') !== 'testing'),
    'auto_refresh' => [
        'lock_key' => 'peck:auto-refresh:lock',
        'lock_minutes' => (int) env('PECK_AUTO_REFRESH_LOCK_MINUTES', 10),
        'last_attempted_date_key' => 'peck:auto-refresh:last-attempted-date',
        'last_successful_date_key' => 'peck:auto-refresh:last-successful-date',
    ],
];
