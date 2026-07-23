<?php

use Illuminate\Support\Facades\Route;

/**
 * Render the published Blade stub through a real `web` route so session + CSRF
 * behave as they would in a host app. Vite is stubbed (no manifest in tests).
 */
function renderLoginStub(): string
{
    test()->withoutVite();

    $stub = __DIR__.'/../../../stubs/ui/livewire/login.blade.php';

    Route::get('/__pwl_login', fn () => view()->file($stub))->middleware('web');

    $response = test()->get('/__pwl_login');
    $response->assertOk();

    return $response->getContent();
}

it('renders both strategy affordances and wires the JSON endpoints', function () {
    config()->set('passwordless.strategies.login_code.enabled', true);
    config()->set('passwordless.strategies.magic_link.enabled', true);

    $html = renderLoginStub();

    // The config JSON uses JSON_UNESCAPED_SLASHES, so route URLs appear verbatim.
    expect($html)
        ->toContain('Send me a code')
        ->toContain('Email me a magic link')
        ->toContain(route('passwordless.login-code.send'))
        ->toContain(route('passwordless.login-code.verify'))
        ->toContain(route('passwordless.magic-link.send'))
        ->toContain('name="csrf-token"')
        ->toContain('one-time-code')          // OTP inputs
        ->toContain('"codeLength":6');
});

it('hides the magic-link affordance when magic_link is disabled', function () {
    config()->set('passwordless.strategies.login_code.enabled', true);
    config()->set('passwordless.strategies.magic_link.enabled', false);

    $html = renderLoginStub();

    expect($html)
        ->toContain('Send me a code')
        ->not->toContain('Email me a magic link');
});

it('hides the login-code form when login_code is disabled', function () {
    config()->set('passwordless.strategies.login_code.enabled', false);
    config()->set('passwordless.strategies.magic_link.enabled', true);

    $html = renderLoginStub();

    expect($html)
        ->not->toContain('Send me a code')
        ->not->toContain('one-time-code')
        ->toContain('Email me a magic link');
});

it('shows a guidance message when no strategy is enabled', function () {
    config()->set('passwordless.strategies.login_code.enabled', false);
    config()->set('passwordless.strategies.magic_link.enabled', false);

    $html = renderLoginStub();

    expect($html)->toContain('No passwordless strategies are enabled');
});

it('reflects a custom login-code length in the OTP config', function () {
    config()->set('passwordless.strategies.login_code.enabled', true);
    config()->set('passwordless.strategies.login_code.length', 8);

    $html = renderLoginStub();

    expect($html)->toContain('"codeLength":8');
});
