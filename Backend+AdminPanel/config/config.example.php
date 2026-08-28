<?php
/**
 * config/config.example.php
 *
 * Copy this file to config/config.php on the server and fill in real
 * values. config/config.php should NEVER be committed to git (it's in
 * .gitignore) since it holds the admin secret.
 *
 * IMPORTANT — if you are reading this after cloning a repo where
 * config/config.php was ever committed with a real admin_api_key filled
 * in, treat that key as compromised and regenerate it immediately with:
 *   php -r "echo bin2hex(random_bytes(24));"
 *
 * Refactor note (Postgres -> SQLite): the old 'db' block held
 * host/port/dbname/user/password for a Postgres server. SQLite is an
 * embedded, file-based database, so that whole block collapses to a
 * single file path.
 */

return [
    'db' => [
        // Path to the SQLite database file. Created automatically on first
        // run by config/db.php if it does not exist yet.
        'path' => __DIR__ . '/../database/mfano_bora.sqlite',
    ],

    // Shared secret the admin dashboard must send as the `X-Api-Key` header
    // on every request to /api/admin/*.php. Generate one with:
    //   php -r "echo bin2hex(random_bytes(24));"
    'admin_api_key' => 'replace-with-a-long-random-string',

    // Set to your main site's origin if the resources portal will ever be
    // called from a different subdomain. Use '*' only for local testing.
    'allowed_origin' => '*',

    // Where uploaded PDFs are written to disk and the public URL prefix
    // used to serve/link them back out.
    'uploads' => [
        'dir'        => __DIR__ . '/../uploads/resources',
        'url_prefix' => '/uploads/resources',
        'max_bytes'  => 25 * 1024 * 1024, // 25MB per PDF
    ],

    // 'development' relaxes nothing by itself today, but is read by the
    // setup scripts/README checklist and is a hook for future env-specific
    // behaviour (e.g. verbose error output only outside production).
    'environment' => 'development',

    'security' => [
        // How many failed X-Api-Key attempts from the same IP within
        // lockout_minutes before that IP is temporarily blocked from the
        // admin API. See includes/auth.php.
        'max_failed_attempts' => 8,
        'lockout_minutes'     => 15,

        // The "Delete everything" kill switch (api/admin/kill_switch.php)
        // is destructive and irreversible. Keep this FALSE in production;
        // only flip to true in disposable dev/demo environments.
        'kill_switch_enabled' => false,
    ],
];