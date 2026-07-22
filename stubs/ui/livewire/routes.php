<?php

/*
|--------------------------------------------------------------------------
| Passwordless UI — example login route (published stub)
|--------------------------------------------------------------------------
|
| Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire
| Target path:   routes/passwordless-ui.php
|
| The package registers its JSON endpoints (login-code / magic-link) but does
| NOT register any page route — that's your call. Wire the published Blade view
| to a GET route here, then require this file from your bootstrap/app.php or a
| route service provider, e.g.:
|
|     ->withRouting(
|         web: __DIR__.'/../routes/web.php',
|         then: fn () => require __DIR__.'/../routes/passwordless-ui.php',
|     )
|
| Or simply paste the route below into your routes/web.php.
|
*/

use Illuminate\Support\Facades\Route;

Route::get('login', fn () => view('passwordless.login'))
    ->middleware('web')
    ->name('login');
