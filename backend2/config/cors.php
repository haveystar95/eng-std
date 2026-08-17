<?php

declare(strict_types=1);

// CORS is scoped to the admin panel only. The mobile client is native (no browser origin, no
// preflight), so the app's own /api/* is intentionally left out of these paths and keeps its
// prior behaviour. The admin SPA runs in a browser, so its origin must be allow-listed here.
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('ADMIN_ORIGIN', ''))
)));

return [
    'paths' => ['admin/api/*'],

    'allowed_methods' => ['*'],

    // No configured origin → no cross-origin browser access (deny by default). Set ADMIN_ORIGIN
    // (comma-separated for several) to the admin panel's URL(s).
    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
