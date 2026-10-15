<?php
declare(strict_types=1);

// Copy to config.local.php, or provide a private path through AFEC_CONFIG_FILE.
// Environment variables override these values (including an intentionally empty password).
return [
    'app' => [
        'environment' => 'production',
        'base_path' => '', // e.g. '/public' only when the site is served below that path.
        'display_timezone' => 'Europe/Rome',
        'public_read' => true, // Approved: public systems, installations, materials and progress.
    ],
    'database' => [
        'host' => '', // Obtain the actual host from Hostinger; do not infer it from the username.
        'port' => 3306,
        'name' => '',
        'username' => '',
        'password' => '',
    ],
    'session' => [
        'name' => 'afec_session',
        'idle_seconds' => 1800,
        'absolute_seconds' => 28800,
        'remember_seconds' => 2592000, // 30 days from login when remember_me is selected.
        'cookie_secure' => true,
    ],
    'markets' => ['stale_days' => 7], // Observations older than this many calendar days are stale.
    'storage_path' => dirname(__DIR__) . '/storage',
];
