<?php

it('offers a remember checkbox in every login stub', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain('remember');
})->with([
    'livewire/login.blade.php',
    'react/Login.tsx',
    'vue/Login.vue',
    'livewire-embed/passwordless.blade.php',
    'react-embed/passwordless.tsx',
    'vue-embed/Passwordless.vue',
]);

it('forwards remember from every published embed controller', function (string $path) {
    expect(file_get_contents(__DIR__.'/../../../stubs/ui/'.$path))
        ->toContain("'remember'")
        ->toContain('RememberFlag');
})->with([
    'livewire-embed/PasswordlessLoginController.php',
    'react-embed/PasswordlessLoginController.php',
    'vue-embed/PasswordlessLoginController.php',
]);
