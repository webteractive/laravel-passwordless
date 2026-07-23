<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Webteractive\Passwordless\PasswordlessServiceProvider;

it('registers the passwordless-ui-livewire publish group mapping the login stub and example route', function () {
    $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, 'passwordless-ui-livewire');

    expect($paths)->not->toBeEmpty();

    $targets = array_values($paths);

    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'views/passwordless/login.blade.php')))
        ->toBeTrue('login stub is not mapped to resources/views/passwordless/login.blade.php');

    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'routes/passwordless-ui.php')))
        ->toBeTrue('example route is not mapped to routes/passwordless-ui.php');
});

it('registers the livewire-embed publish group mapping the kit page, controller and routes', function () {
    $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, 'passwordless-ui-livewire-embed');
    $targets = array_values($paths);

    expect($paths)->not->toBeEmpty();
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'views/pages/auth/passwordless.blade.php')))
        ->toBeTrue('embed page is not mapped to resources/views/pages/auth/passwordless.blade.php');
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'Http/Controllers/Auth/PasswordlessLoginController.php')))
        ->toBeTrue('embed controller is not mapped');
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'routes/passwordless-ui.php')))
        ->toBeTrue('embed routes are not mapped');
});

it('registers the react publish group mapping the Inertia page and example route', function () {
    $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, 'passwordless-ui-react');
    $targets = array_values($paths);

    expect($paths)->not->toBeEmpty();
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'js/pages/passwordless/login.tsx')))
        ->toBeTrue('react page is not mapped to resources/js/pages/passwordless/login.tsx');
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'routes/passwordless-ui.php')))
        ->toBeTrue('react example route is not mapped');
});

it('registers the vue publish group mapping the Inertia page and example route', function () {
    $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, 'passwordless-ui-vue');
    $targets = array_values($paths);

    expect($paths)->not->toBeEmpty();
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'js/pages/passwordless/Login.vue')))
        ->toBeTrue('vue page is not mapped to resources/js/pages/passwordless/Login.vue');
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'routes/passwordless-ui.php')))
        ->toBeTrue('vue example route is not mapped');
});

it('ships every declared stub source on disk', function () {
    foreach ([
        'passwordless-ui-livewire', 'passwordless-ui-livewire-embed',
        'passwordless-ui-react', 'passwordless-ui-react-embed',
        'passwordless-ui-vue', 'passwordless-ui-vue-embed',
    ] as $tag) {
        $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, $tag);

        foreach (array_keys($paths) as $source) {
            expect(file_exists($source))->toBeTrue("missing stub source for {$tag}: {$source}");
        }
    }
});

it('actually copies the Blade stub to the app when published', function () {
    $target = resource_path('views/passwordless/login.blade.php');

    // Clean slate, then publish for real through the booted (provider-loaded) app.
    if (file_exists($target)) {
        unlink($target);
    }

    Artisan::call('vendor:publish', ['--tag' => 'passwordless-ui-livewire', '--force' => true]);

    try {
        expect(file_exists($target))->toBeTrue('vendor:publish did not copy the Blade login stub');
        expect(file_get_contents($target))->toContain('pwl-config');
    } finally {
        @unlink($target);
        @unlink(base_path('routes/passwordless-ui.php'));
    }
});
