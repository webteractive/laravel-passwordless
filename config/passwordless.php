<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Resolution
    |--------------------------------------------------------------------------
    */
    'user_model' => 'App\\Models\\User',
    'user_email_column' => 'email',
    'auto_create_users' => false,

    /*
    |--------------------------------------------------------------------------
    | Auth Guard & Routing
    |--------------------------------------------------------------------------
    */
    'guard' => 'web',
    'route_prefix' => 'auth',
    'redirect' => '/',
    'api_mode' => false,

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    // Enumeration protection is always-on by design — request endpoints emit
    // identical responses regardless of whether the email is registered, and
    // both code paths perform equivalent work to avoid a timing oracle. There
    // is no toggle.
    'resend_cooldown' => 30,

    'lockout' => [
        'max_attempts' => 5,
        'window' => 15 * 60,
    ],

    'throttle' => [
        'request' => [
            'per_email' => ['max' => 5, 'window' => 10 * 60],
            'per_ip' => ['max' => 20, 'window' => 10 * 60],
        ],
        'verify' => [
            'per_email' => ['max' => 10, 'window' => 10 * 60],
            'per_ip' => ['max' => 30, 'window' => 10 * 60],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Same-Browser Cookie (magic links)
    |--------------------------------------------------------------------------
    */
    'browser_cookie' => [
        'name' => 'passwordless_browser',
        'same_site' => 'lax',
        'http_only' => true,
        'secure' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    */
    'branding' => [
        'app_name' => null,
        'support_email' => null,
        'primary_color' => '#000000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strategies
    |--------------------------------------------------------------------------
    */
    'strategies' => [

        'magic_link' => [
            'enabled' => true,
            'ttl' => 15 * 60,
            'same_browser' => true,
            'route_name' => 'passwordless.magic-link.consume',
        ],

        'login_code' => [
            'enabled' => true,
            'length' => 6,
            'ttl' => 10 * 60,
            'channel' => 'mail',
        ],

    ],

];
