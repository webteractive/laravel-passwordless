<?php

use Illuminate\Support\ServiceProvider;
use Webteractive\Passwordless\PasswordlessServiceProvider;

it('publishes a confirm-identity page and Fortify wiring for every embed tag', function (string $tag, string $page) {
    $targets = array_values(ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, $tag));

    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, $page)))
        ->toBeTrue("confirm-identity page is not mapped for {$tag}");

    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'PasswordlessFortifyServiceProvider.php')))
        ->toBeTrue("Fortify wiring provider is not mapped for {$tag}");
})->with([
    ['passwordless-ui-livewire-embed', 'views/pages/auth/confirm-identity.blade.php'],
    ['passwordless-ui-react-embed', 'js/pages/auth/confirm-identity.tsx'],
    ['passwordless-ui-vue-embed', 'js/pages/auth/ConfirmIdentity.vue'],
]);

it('wires confirmPasswordsUsing to the package confirmation unit', function (string $stub) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$stub))
        ->toContain('confirmPasswordsUsing')
        ->toContain('Passwordless::confirmation()')
        ->toContain('ConfirmPasswordViewResponse');
})->with([
    'livewire-embed/PasswordlessFortifyServiceProvider.php',
    'react-embed/PasswordlessFortifyServiceProvider.php',
    'vue-embed/PasswordlessFortifyServiceProvider.php',
]);

it('warns that confirmPasswordsUsing is global', function (string $stub) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$stub))
        ->toContain('global');
})->with([
    'livewire-embed/PasswordlessFortifyServiceProvider.php',
    'react-embed/PasswordlessFortifyServiceProvider.php',
    'vue-embed/PasswordlessFortifyServiceProvider.php',
]);

it('handles the two-factor challenge in every published login controller', function (string $stub) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$stub))
        ->toContain('twoFactor()');
})->with([
    'livewire-embed/PasswordlessLoginController.php',
    'react-embed/PasswordlessLoginController.php',
    'vue-embed/PasswordlessLoginController.php',
]);

/**
 * Blade builds the URLs in the page; Inertia pages receive them as props from
 * the provider. So the pair, not the page alone, is what must reference both
 * routes.
 *
 * The send target must be the app-owned `passwordless.confirm.request` action,
 * NOT the package's `passwordless.confirm.send` — that one is the headless JSON
 * endpoint, and a browser form posting to it lands the user on a raw
 * `{"status":"sent"}` page instead of back on the confirm form.
 */
it('wires every confirm-identity flow to the app send action and Fortify store route', function (string $page, string $provider) {
    $combined = file_get_contents(__DIR__.'/../../../stubs/ui/'.$page)
        .file_get_contents(__DIR__.'/../../../stubs/ui/'.$provider);

    expect($combined)
        ->toContain('passwordless.confirm.request')
        ->toContain('password.confirm.store');
})->with([
    ['livewire-embed/confirm-identity.blade.php', 'livewire-embed/PasswordlessFortifyServiceProvider.php'],
    ['react-embed/confirm-identity.tsx', 'react-embed/PasswordlessFortifyServiceProvider.php'],
    ['vue-embed/ConfirmIdentity.vue', 'vue-embed/PasswordlessFortifyServiceProvider.php'],
]);

it('never posts a browser form straight at the headless JSON send route', function (string $stub) {
    $contents = file_get_contents(__DIR__.'/../../../stubs/ui/'.$stub);

    expect($contents)->not->toMatch('/route\(\s*[\'"]passwordless\.confirm\.send[\'"]\s*\)/');
})->with([
    'livewire-embed/confirm-identity.blade.php',
    'react-embed/PasswordlessFortifyServiceProvider.php',
    'vue-embed/PasswordlessFortifyServiceProvider.php',
]);

it('defines the app-owned send action and route in every embed variant', function (string $controller, string $routes) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$controller))
        ->toContain('function sendConfirmation')
        ->toContain('Passwordless::confirmation()->send');

    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$routes))
        ->toContain('passwordless.confirm.request')
        // The user is signed in at this point — the guest group would 302 them away.
        ->toContain("'auth'");
})->with([
    ['livewire-embed/PasswordlessLoginController.php', 'livewire-embed/routes.php'],
    ['react-embed/PasswordlessLoginController.php', 'react-embed/routes.php'],
    ['vue-embed/PasswordlessLoginController.php', 'vue-embed/routes.php'],
]);

/**
 * Laravel resolves a Responsable exactly one level. Inertia::render() returns
 * another Responsable, so handing it back raw from ConfirmPasswordViewResponse
 * makes Laravel try to use it as a response body and blows up with
 * "setContent(): Argument #1 ($content) must be of type ?string". Caught by
 * browser-testing the React kit; the Livewire stub returns a View and is fine.
 */
it('converts the Inertia response before returning it to Fortify', function (string $provider) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$provider))
        ->toContain('->toResponse($request)');
})->with([
    'react-embed/PasswordlessFortifyServiceProvider.php',
    'vue-embed/PasswordlessFortifyServiceProvider.php',
]);

it('passes the route props Inertia confirm pages declare', function (string $provider) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$provider))
        ->toContain("'routes' =>")
        ->toContain("'send' =>")
        ->toContain("'confirm' =>");
})->with([
    'react-embed/PasswordlessFortifyServiceProvider.php',
    'vue-embed/PasswordlessFortifyServiceProvider.php',
]);
