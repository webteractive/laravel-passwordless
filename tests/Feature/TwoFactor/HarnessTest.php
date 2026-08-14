<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Workbench\App\Models\TwoFactorUser;

it('provides a workbench user model using Fortify two-factor', function () {
    expect(class_uses_recursive(TwoFactorUser::class))
        ->toContain(TwoFactorAuthenticatable::class);
});

it('stores two factor columns on the users table', function () {
    $user = TwoFactorUser::create([
        'email' => 'harness@example.com',
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('reports two factor as enabled once a confirmed secret exists', function () {
    $user = TwoFactorUser::create([
        'email' => 'harness2@example.com',
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('reports two factor as disabled without a secret', function () {
    $user = TwoFactorUser::create(['email' => 'harness3@example.com']);

    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('registers Fortify two-factor challenge route', function () {
    expect(Route::has('two-factor.login'))->toBeTrue();
});
