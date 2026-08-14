<?php

/*
|--------------------------------------------------------------------------
| Passwordless UI — routes (INTEGRATED with the Vue starter kit)
|--------------------------------------------------------------------------
|
| Published by:  php artisan vendor:publish --tag=passwordless-ui-vue-embed
| Target path:   routes/passwordless-ui.php
|
| Route names are `passwordless.*` so they don't collide with the kit's Fortify
| `login`. Require this file from bootstrap/app.php (see below) or paste into
| routes/web.php:
|
|     ->withRouting(
|         web: __DIR__.'/../routes/web.php',
|         then: fn () => require __DIR__.'/../routes/passwordless-ui.php',
|     )
|
*/

use App\Http\Controllers\Auth\PasswordlessLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('passwordless', [PasswordlessLoginController::class, 'create'])->name('passwordless.login');
    Route::get('passwordless/start-over', [PasswordlessLoginController::class, 'startOver'])->name('passwordless.start-over');
    Route::post('passwordless/code', [PasswordlessLoginController::class, 'requestCode'])->name('passwordless.request');
    Route::post('passwordless/verify', [PasswordlessLoginController::class, 'verify'])->name('passwordless.verify');
    Route::post('passwordless/link', [PasswordlessLoginController::class, 'requestLink'])->name('passwordless.link');
});

// Identity confirmation — emails a code so a password-less account can satisfy
// Fortify's `password.confirm` gate (the guard in front of the 2FA settings).
// The user is already signed in here, hence `auth` rather than `guest`.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('passwordless/confirm', [PasswordlessLoginController::class, 'sendConfirmation'])
        ->name('passwordless.confirm.request');
});
