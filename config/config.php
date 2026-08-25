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
    'admin_api_key' => '90692b792db62076a5b4a82d4fd5910743c121cf27e74320',

    'allowed_origin' => 'https://mfanobora.com',

    'uploads' => [
        'dir'        => __DIR__ . '/../uploads/resources',
        'url_prefix' => '/uploads/resources',
        'max_bytes'  => 25 * 1024 * 1024,
    ],
];