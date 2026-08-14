<?php

it('offers a dev user picker in every login stub', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain('Dev sign-in')
        ->toContain('Sign in as selected user');
})->with([
    'livewire/login.blade.php',
    'react/Login.tsx',
    'vue/Login.vue',
    'livewire-embed/passwordless.blade.php',
    'react-embed/passwordless.tsx',
    'vue-embed/Passwordless.vue',
]);

/**
 * The fetch-driven standalone stubs hit the endpoint directly; a 404 outside
 * local dev leaves the list empty and nothing renders.
 */
it('probes the dev-login endpoint from every standalone stub', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain('/auth/dev-login');
})->with([
    'livewire/login.blade.php',
    'react/Login.tsx',
    'vue/Login.vue',
]);

/**
 * The picker renders names and emails straight from the database. Building that
 * markup with innerHTML would execute anything they contain, in a developer's
 * own browser — so the DOM-API path is the only acceptable one.
 */
it('never builds picker markup with innerHTML', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->not->toContain('.innerHTML =');
})->with([
    'livewire/login.blade.php',
    'react/Login.tsx',
    'vue/Login.vue',
    'livewire-embed/passwordless.blade.php',
    'react-embed/passwordless.tsx',
    'vue-embed/Passwordless.vue',
]);

/**
 * The Inertia embed pages receive devUsers/devLoginRoute as props rather than
 * knowing route names — the controller resolves those (asserted below).
 */
it('drives the embed pickers from controller-supplied props', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain('devUsers')
        ->toContain('devLoginRoute');
})->with([
    'react-embed/passwordless.tsx',
    'vue-embed/Passwordless.vue',
]);

/**
 * The picker must be inert wherever the dev-login guard did not pass. Server
 * -rendered stubs gate on Route::has; fetch-driven stubs simply render nothing
 * when the endpoint 404s.
 */
it('gates the server-rendered pickers on the route existing', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain('passwordless.dev-login.index');
})->with([
    'livewire-embed/passwordless.blade.php',
    'react-embed/PasswordlessLoginController.php',
    'vue-embed/PasswordlessLoginController.php',
]);
