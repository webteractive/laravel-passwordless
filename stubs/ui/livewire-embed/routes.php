<?php

/*
|--------------------------------------------------------------------------
| Passwordless UI — routes (INTEGRATED with the Livewire starter kit)
|--------------------------------------------------------------------------
|
| Published by:  php artisan vendor:publish --tag=passwordless-ui-livewire-embed
| Target path:   routes/passwordless-ui.php
|
| Route names are prefixed `passwordless.*` so they DON'T collide with the kit's
| Fortify `login` route — the two auth flows coexist. To make passwordless the
| primary login instead, point your nav/`login` redirect at `passwordless.login`.
|
| Require this file from bootstrap/app.php:
|
|     ->withRouting(
|         web: __DIR__.'/../routes/web.php',
|         then: fn () => require __DIR__.'/../routes/passwordless-ui.php',
|     )
|
| or paste these routes into routes/web.php.
|
*/

use App\Http\Controllers\Auth\PasswordlessLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('passwordless', [PasswordlessLoginController::class, 'create'])->name('passwordless.login');
    Route::post('passwordless/code', [PasswordlessLoginController::class, 'requestCode'])->name('passwordless.request');
    Route::post('passwordless/verify', [PasswordlessLoginController::class, 'verify'])->name('passwordless.verify');
    Route::post('passwordless/link', [PasswordlessLoginController::class, 'requestLink'])->name('passwordless.link');
});
