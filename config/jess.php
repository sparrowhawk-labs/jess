<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Jess registers no routes and is fully inert unless explicitly opted
    | in. Default to false so it can never be reachable in production by
    | accident — flip to true only in local/staging/demo environments.
    |
    */
    'enabled' => env('JESS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a jess state-switch link remains valid after issuance.
    |
    */
    'signed_url_ttl' => env('JESS_SIGNED_URL_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Demo DB storage path
    |--------------------------------------------------------------------------
    |
    | Where per-session SQLite file copies are written. Relative to
    | storage_path() unless an absolute path is given.
    |
    */
    'demo_db_storage_path' => env('JESS_DEMO_DB_STORAGE_PATH', 'app/jess/demo-sessions'),

    /*
    |--------------------------------------------------------------------------
    | Template DB path
    |--------------------------------------------------------------------------
    |
    | Absolute path to a migrated, empty-data SQLite file that the host app
    | prepares and keeps in sync with its schema. Jess copies this file
    | per demo session rather than owning migrations for the demo data.
    |
    */
    'template_db_path' => env('JESS_TEMPLATE_DB_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Demo connection name
    |--------------------------------------------------------------------------
    |
    | The database connection name jess registers and repoints at each
    | demo session's SQLite copy. Kept out of the host app's own connection
    | names so fragments/Seeders never need to reference it explicitly.
    |
    */
    'connection' => env('JESS_CONNECTION', 'jess_demo'),

    /*
    |--------------------------------------------------------------------------
    | Demo banner
    |--------------------------------------------------------------------------
    |
    | While a demo session is active, jess injects a small corner banner
    | into full-page HTML responses: it names the active recipe and offers
    | a one-click exit back to the real DB. On non-demo pages (still only
    | while jess.enabled) a tiny script records the current URL + scroll
    | position so that exit can land you exactly where you were before
    | entering the demo. Set false to disable both.
    |
    */
    'banner' => env('JESS_BANNER', true),

    /*
    |--------------------------------------------------------------------------
    | Exit redirect
    |--------------------------------------------------------------------------
    |
    | Where the banner's exit lands when no pre-demo location was recorded
    | (e.g. the demo link was opened from outside the app).
    |
    */
    'exit_redirect' => env('JESS_EXIT_REDIRECT', '/'),

    /*
    |--------------------------------------------------------------------------
    | Cleanup after (hours)
    |--------------------------------------------------------------------------
    |
    | Per-session SQLite copies older than this are eligible for cleanup by
    | the jess cleanup command/schedule.
    |
    */
    'cleanup_after_hours' => env('JESS_CLEANUP_AFTER_HOURS', 24),
];
