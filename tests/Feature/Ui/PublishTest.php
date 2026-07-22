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

it('registers the flux publish group mapping the Volt component and example route', function () {
    $paths = ServiceProvider::pathsToPublish(PasswordlessServiceProvider::class, 'passwordless-ui-flux');
    $targets = array_values($paths);

    expect($paths)->not->toBeEmpty();
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'views/livewire/passwordless/login.blade.php')))
        ->toBeTrue('flux Volt component is not mapped to resources/views/livewire/passwordless/login.blade.php');
    expect(collect($targets)->contains(fn ($t) => str_ends_with($t, 'routes/passwordless-ui.php')))
        ->toBeTrue('flux example route is not mapped');
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
    foreach (['passwordless-ui-livewire', 'passwordless-ui-flux', 'passwordless-ui-react', 'passwordless-ui-vue'] as $tag) {
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
        expect(file_get_contents($target))->toContain('passwordlessLogin(');
    } finally {
        @unlink($target);
        @unlink(base_path('routes/passwordless-ui.php'));
    }
});
