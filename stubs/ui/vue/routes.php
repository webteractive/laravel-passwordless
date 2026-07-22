<?php

/*
|--------------------------------------------------------------------------
| Passwordless UI — example login route (Inertia + Vue, published stub)
|--------------------------------------------------------------------------
|
| Published by:  php artisan vendor:publish --tag=passwordless-ui-vue
| Target path:   routes/passwordless-ui.php
|
| The package registers its JSON endpoints (login-code / magic-link) but does
| NOT register any page route. Wire the published Inertia page below, then
| require this file from bootstrap/app.php (see the Blade stub's routes file for
| the withRouting example) or paste the route into routes/web.php.
|
| The Login component submits to the package endpoints with fetch(), so no
| Inertia-returning controller is needed here.
|
*/

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('login', fn () => Inertia::render('passwordless/Login', [
    'appName' => config('passwordless.branding.app_name') ?? config('app.name', 'Laravel'),
    'codeEnabled' => (bool) config('passwordless.strategies.login_code.enabled', true),
    'linkEnabled' => (bool) config('passwordless.strategies.magic_link.enabled', true),
    'codeLength' => (int) config('passwordless.strategies.login_code.length', 6),
    'redirect' => config('passwordless.redirect', '/'),
    'endpoints' => [
        'sendCode' => config('passwordless.strategies.login_code.enabled', true) ? route('passwordless.login-code.send') : null,
        'verifyCode' => config('passwordless.strategies.login_code.enabled', true) ? route('passwordless.login-code.verify') : null,
        'sendLink' => config('passwordless.strategies.magic_link.enabled', true) ? route('passwordless.magic-link.send') : null,
    ],
]))->middleware('web')->name('login');
