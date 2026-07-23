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

    // Where server-driven logins (social callback, published embed controllers)
    // land after auth. This is the final fallback: a middleware-set intended URL
    // wins first, then a Passwordless::redirectUsing() closure, then this value.
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

        // magicCode — one email carrying BOTH a magic link and a numeric code.
        // The user authenticates with either; the first one used wins and the
        // other is invalidated. Opt-in (off by default). Email only.
        'magic_code' => [
            'enabled' => false,
            'ttl' => 15 * 60,        // shared TTL for both the link and the code
            'same_browser' => true,  // enforced on the LINK path only
            'code' => [
                'length' => 6,
            ],
            'route_name' => 'passwordless.magic-code.consume',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Social login (Socialite)
    |--------------------------------------------------------------------------
    |
    | Credentials live in config/services.php (Socialite's convention). Here you
    | only enable providers and (optionally) tune scopes / redirect params. Only
    | listed providers get routes; install the driver + add keys, then list it.
    */
    'social' => [
        'providers' => [
            // 'google',
            // 'github' => ['scopes' => ['read:user']],
        ],
        'auto_register' => true,

        // An email is linked/registered only when it is provably verified: the
        // provider sends `email_verified: true`, OR the provider is listed here
        // (known to return verified emails). An explicit `email_verified: false`
        // always wins. This prevents account takeover via an unverified email.
        'trusted_providers' => [
            'google', 'github', 'gitlab', 'bitbucket',
            'microsoft', 'azure', 'apple', 'linkedin', 'linkedin-openid', 'facebook',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain allow-list
    |--------------------------------------------------------------------------
    |
    | An empty `allowed` list disables all checks (no behavior change). When set,
    | enforcement is independent per type (passwordless = magic link + login
    | code, social) and per action (login of existing users, register/auto-create).
    */
    'domains' => [
        'allowed' => [],
        'enforce' => [
            'passwordless' => ['login' => false, 'register' => true],
            'social' => ['login' => false, 'register' => true],
        ],
    ],

];
