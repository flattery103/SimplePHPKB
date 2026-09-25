<?php
return [
    'site_name' => 'SimplePHPKB',
    // Empty for the site root, or e.g. '/kb'. No trailing slash.
    'base_path' => '',
    // Keep true with HTTPS, including when HTTPS terminates at your reverse proxy.
    'secure_cookies' => true,
    'timezone' => 'America/Chicago',
    'storage' => dirname(__DIR__).'/storage',
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=kb;charset=utf8mb4',
        'username' => 'kb',
        'password' => 'CHANGE_ME',
    ],
];
