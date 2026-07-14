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

    // When non-empty, exposes a web-based /license-setup/{token} page for
    // configuring LICENSE_KEY/LICENSE_SECRET without terminal access (e.g. on
    // shared hosting). Unset (default) means the page does not exist at all.
    // Set this to a random string via your host's file manager to enable it,
    // then use the page's own "disable" button to clear it again once done.
    'setup_token' => env('LICENSE_SETUP_TOKEN', ''),

    // Domains treated as local for the is_local payload flag (does NOT bypass API calls).
    // Entries starting with a leading dot are suffix-matched (e.g. '.test' matches
    // 'myapp.test'); all other entries are matched exactly (case-insensitive).
    // Mirrors the license server's own config/license.php local_domains/local_suffixes.
    'local_domains' => [
        'localhost',
        '127.0.0.1',
        '::1',
        '.test',
        '.local',
        '.dev',
        '.example',
    ],

    'http' => [
        'timeout' => (int) env('LICENSE_HTTP_TIMEOUT', 5),
        'connect_timeout' => (int) env('LICENSE_HTTP_CONNECT_TIMEOUT', 3),
        'retries' => (int) env('LICENSE_HTTP_RETRIES', 1),
    ],

];
