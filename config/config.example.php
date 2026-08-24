<?php
/**
 * config/config.example.php
 *
 * Copy this file to config/config.php on the server and fill in real
 * values. config/config.php should NEVER be committed to git (it's in
 * .gitignore) since it holds database credentials and the admin secret.
 */

return [
    // Database connection (same Postgres database used by the Node backend —
    // this refactor only changes the API layer, not the data layer).
    'db' => [
        'host'     => 'localhost',
        'port'     => '5432',
        'dbname'   => 'mfano_bora_db',
        'user'     => 'postgres',
        'password' => 'postgres',
    ],

    // Shared secret the admin dashboard must send as the `X-Api-Key` header
    // on every request to /api/admin/*.php. Generate one with:
    //   php -r "echo bin2hex(random_bytes(24));"
    'admin_api_key' => 'replace-with-a-long-random-string',

    // Set to your main site's origin if the resources portal will ever be
    // called from a different subdomain. Use '*' only for local testing.
    'allowed_origin' => '*',
];
