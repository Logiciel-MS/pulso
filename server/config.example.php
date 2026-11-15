<?php

// Copy to config.php (same folder) and fill it in. config.php is git-ignored.
return [
    'db' => [
        // Hostinger: hPanel -> Databases -> MySQL Databases
        'dsn' => 'mysql:host=localhost;dbname=u123456789_pulso;charset=utf8mb4',
        'user' => 'u123456789_pulso',
        'password' => 'change-me',
    ],

    // Lets you list all sites and read any site's stats (your agency dashboard).
    // Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'admin_token' => '',

    // true only if the server sits behind Cloudflare or another proxy you control.
    // Then the visitor IP comes from CF-Connecting-IP / X-Forwarded-For and the
    // country from CF-IPCountry. Leave false otherwise: those headers can be faked.
    'trust_proxy' => false,
];
