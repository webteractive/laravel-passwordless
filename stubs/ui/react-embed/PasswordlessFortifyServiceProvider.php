<?php

/*
 * Passwordless — Fortify wiring (INTEGRATED with the React starter kit)
 * --------------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-react-embed
 * Target path:   app/Providers/PasswordlessFortifyServiceProvider.php
 *
 * Register this provider in bootstrap/providers.php.
 *
 * Why this exists: the starter kits gate enabling 2FA behind Laravel's
 * `password.confirm` middleware, which a password-less user can never satisfy —
 * they have no password. This swaps Fortify's password check for an emailed
 * confirmation code, leaving Fortify's routes, middleware and responses intact.
 * Fortify still does the `auth.password_confirmed_at` stamping on success, so
 * every gated action (two-factor.enable/confirm/disable, recovery codes) works
 * with no route overrides.
 *
 * NOTE: Fortify::confirmPasswordsUsing() is global. The callback below already
 * composes both paths — a real password still works, and an emailed code works
 * for accounts that have none. If your app registers its own callback elsewhere,
 * merge them here rather than registering twice; the last one wins.
 */

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\ConfirmPasswordViewResponse;
use Laravel\Fortify\Fortify;
use Webteractive\Passwordless\Facades\Passwordless;

class PasswordlessFortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::confirmPasswordsUsing(function ($user, $password) {
            // Users who DO have a password keep the normal path.
            if ($password && $user->getAuthPassword() && Auth::guard(config('fortify.guard'))->validate([
                Fortify::username() => $user->{Fortify::username()},
                'password' => $password,
            ])) {
                return true;
            }

            // Otherwise treat the submitted value as the emailed confirmation code.
            return Passwordless::confirmation()->verify($user, (string) $password);
        });

        $this->app->singleton(ConfirmPasswordViewResponse::class, fn () => new class implements ConfirmPasswordViewResponse
        {
            public function toResponse($request)
            {
                return Inertia::render('auth/confirm-identity', [
                    'routes' => [
                        // Package route: emails the confirmation code.
                        'send' => route('passwordless.confirm.send'),
                        // Fortify's own route: runs confirmPasswordsUsing and
                        // stamps auth.password_confirmed_at on success.
                        'confirm' => route('password.confirm.store'),
                    ],
                    'status' => session('status'),
                ]);
            }
        });
    }
}
