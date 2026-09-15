<?php

return [
    'admin_path' => 'console',

    'display_timezone' => 'UTC',

    'schedule' => [
        'day_start' => 8 * 60,
        'day_end' => 22 * 60,
        'pixels_per_minute' => 1,
        'near_thresholds' => [15, 30, 45, 60],
    ],

    'settings_defaults' => [
        'site_name' => '课隙',
        'site_url' => null,
        'timezone' => 'Asia/Shanghai',
        'session_lifetime_minutes' => 120,
        'registration_enabled' => true,
        'turnstile_enabled' => false,
        'turnstile_login' => true,
        'turnstile_register' => true,
        'turnstile_site_key' => null,
        'turnstile_secret' => null,
        'passkeys_enabled' => false,
        'passkeys_name' => null,
        'sharing_enabled' => true,
        'mail_mailer' => 'log',
        'mail_host' => '127.0.0.1',
        'mail_port' => 587,
        'mail_scheme' => null,
        'mail_username' => null,
        'mail_password' => null,
        'mail_from_address' => 'noreply@example.com',
        'mail_from_name' => '课隙',
    ],
];
