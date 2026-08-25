<?php
/**
 * config/config.php
 *
 * Local/demo configuration for the SQLite-backed build. In a real
 * deployment this file stays out of git (see .gitignore) and each
 * environment generates its own admin_api_key.
 */

return [
    'db' => [
        'path' => __DIR__ . '/../database/mfano_bora.sqlite',
    ],

    // Generated with: php -r "echo bin2hex(random_bytes(24));"
    // Replace before deploying anywhere other people can reach.
    'admin_api_key' => '46fa877528d8ffc67aef17cb27143802acc08ec0615f763e',

    'allowed_origin' => 'https://mfanobora.com',

    'uploads' => [
        'dir'        => __DIR__ . '/../uploads/resources',
        'url_prefix' => '/uploads/resources',
        'max_bytes'  => 25 * 1024 * 1024,
    ],
];