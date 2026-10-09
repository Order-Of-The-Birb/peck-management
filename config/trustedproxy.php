<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | This value defines which proxy IP addresses are trusted to forward
    | headers such as X-Forwarded-Proto and X-Forwarded-Host. Use "*" to
    | trust all proxies (suitable behind a reverse proxy or tunnel), a
    | comma-separated list of IPs, or null to trust none.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
