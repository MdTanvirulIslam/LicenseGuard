<?php

// This is the ONLY file in this package allowed to call env(). Laravel strips
// env() access outside config files once `config:cache` has run, so every
// other class must read these values via config('license-guard.*').
return [

    'server_url' => env('LICENSE_SERVER_URL', ''),

    'license_key' => env('LICENSE_KEY', ''),

    'secret' => env('LICENSE_SECRET', ''),

    'check_interval_hours' => (int) env('LICENSE_CHECK_INTERVAL_HOURS', 6),

    'grace_period_hours' => (int) env('LICENSE_GRACE_PERIOD_HOURS', 24),

    // Vendor's own dev machine ONLY. Never set true in a customer .env.
    // When true, all license checks short-circuit to "valid" with zero HTTP calls.
    'bypass_local' => (bool) env('LICENSE_BYPASS_LOCAL', false),

    // Domains treated as local for the is_local payload flag (does NOT bypass API calls).
    // Entries starting with a leading dot are suffix-matched (e.g. '.test' matches
    // 'myapp.test'); all other entries are matched exactly (case-insensitive).
    'local_domains' => [
        'localhost',
        '127.0.0.1',
        '::1',
        '.test',
        '.local',
        '.dev',
    ],

    'http' => [
        'timeout' => (int) env('LICENSE_HTTP_TIMEOUT', 5),
        'connect_timeout' => (int) env('LICENSE_HTTP_CONNECT_TIMEOUT', 3),
        'retries' => (int) env('LICENSE_HTTP_RETRIES', 1),
    ],

];
