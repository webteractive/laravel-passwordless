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
 * routes — the package send route and Fortify's own confirm-password store.
 */
it('wires every confirm-identity flow to the send route and Fortify store route', function (string $page, string $provider) {
    $combined = file_get_contents(__DIR__.'/../../../stubs/ui/'.$page)
        .file_get_contents(__DIR__.'/../../../stubs/ui/'.$provider);

    expect($combined)
        ->toContain('passwordless.confirm.send')
        ->toContain('password.confirm.store');
})->with([
    ['livewire-embed/confirm-identity.blade.php', 'livewire-embed/PasswordlessFortifyServiceProvider.php'],
    ['react-embed/confirm-identity.tsx', 'react-embed/PasswordlessFortifyServiceProvider.php'],
    ['vue-embed/ConfirmIdentity.vue', 'vue-embed/PasswordlessFortifyServiceProvider.php'],
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
