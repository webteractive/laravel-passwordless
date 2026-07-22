<?php

use Illuminate\Support\Facades\Route;
use Webteractive\Passwordless\Http\Controllers\LoginCode\SendController as LoginCodeSendController;
use Webteractive\Passwordless\Http\Controllers\LoginCode\VerifyController as LoginCodeVerifyController;
use Webteractive\Passwordless\Http\Controllers\MagicLink\ConsumeController;
use Webteractive\Passwordless\Http\Controllers\MagicLink\SendController;
use Webteractive\Passwordless\Http\Middleware\PasswordlessThrottle;

Route::group([
    'prefix' => config('passwordless.route_prefix', 'auth'),
], function () {
    Route::post('magic-link', SendController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.magic-link.send');

    // Magic link consume needs the session/cookie stack so the same-browser
    // cookie set during send() is available here.
    Route::get('magic-link/{token}', ConsumeController::class)
        ->middleware(['web', PasswordlessThrottle::class.':verify'])
        ->name('passwordless.magic-link.consume');

    Route::post('login-code', LoginCodeSendController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.login-code.send');

    Route::post('login-code/verify', LoginCodeVerifyController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.login-code.verify');
});
