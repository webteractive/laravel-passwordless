<?php

use Illuminate\Support\Facades\Route;
use Webteractive\Passwordless\Http\Controllers\LoginCode\SendController as LoginCodeSendController;
use Webteractive\Passwordless\Http\Controllers\LoginCode\VerifyController as LoginCodeVerifyController;
use Webteractive\Passwordless\Http\Controllers\MagicCode\ConsumeController as MagicCodeConsumeController;
use Webteractive\Passwordless\Http\Controllers\MagicCode\SendController as MagicCodeSendController;
use Webteractive\Passwordless\Http\Controllers\MagicCode\VerifyController as MagicCodeVerifyController;
use Webteractive\Passwordless\Http\Controllers\MagicLink\ConsumeController;
use Webteractive\Passwordless\Http\Controllers\MagicLink\SendController;
use Webteractive\Passwordless\Http\Controllers\Social\CallbackController as SocialCallbackController;
use Webteractive\Passwordless\Http\Controllers\Social\RedirectController as SocialRedirectController;
use Webteractive\Passwordless\Http\Middleware\PasswordlessThrottle;

// Session-mode routes: the whole group runs through the `web` middleware stack
// (StartSession, cookies, CSRF) so session-guard login persists across requests
// and the magic-link same-browser cookie is actually written. API-token mode
// (api_mode) should register these endpoints via routes/api.php instead.
Route::group([
    'prefix' => config('passwordless.route_prefix', 'auth'),
    'middleware' => ['web'],
], function () {
    Route::post('magic-link', SendController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.magic-link.send');

    Route::get('magic-link/{token}', ConsumeController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.magic-link.consume');

    Route::post('login-code', LoginCodeSendController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.login-code.send');

    Route::post('login-code/verify', LoginCodeVerifyController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.login-code.verify');

    // magicCode: one email with both a magic link and a login code. Send is one
    // request; the user consumes EITHER path and the sibling is invalidated.
    // Gated by strategies.magic_code.enabled inside the controllers (404 when off).
    Route::post('magic-code', MagicCodeSendController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.magic-code.send');

    Route::get('magic-code/{token}', MagicCodeConsumeController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.magic-code.consume');

    Route::post('magic-code/verify', MagicCodeVerifyController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.magic-code.verify');

    // Social (OAuth via Socialite). Only providers listed in config get a
    // working driver; unknown/disabled providers 404 in the controllers.
    Route::get('social/{provider}/redirect', SocialRedirectController::class)
        ->middleware(PasswordlessThrottle::class.':request')
        ->name('passwordless.social.redirect');

    Route::get('social/{provider}/callback', SocialCallbackController::class)
        ->middleware(PasswordlessThrottle::class.':verify')
        ->name('passwordless.social.callback');
});
