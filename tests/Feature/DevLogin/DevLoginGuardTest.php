<?php

use Illuminate\Support\Facades\Route;

/**
 * The guard IS the feature. Each case asserts the routes are not merely
 * forbidden but absent — a dev user-switcher that 403s still exists, and an
 * endpoint that exists is an endpoint that can be reached by mistake.
 */
it('does not register the routes by default', function () {
    expect(Route::has('passwordless.dev-login.index'))->toBeFalse();
    expect(Route::has('passwordless.dev-login.store'))->toBeFalse();

    $this->getJson('/auth/dev-login')->assertStatus(404);
    $this->postJson('/auth/dev-login', ['user' => 1])->assertStatus(404);
});

it('does not register the routes when enabled but the environment is not allowed', function () {
    // The suite runs with APP_ENV=testing, so a local-only allow-list must not match.
    $this->app['config']->set('passwordless.dev_login.enabled', true);
    $this->app['config']->set('passwordless.dev_login.environments', ['local']);

    $this->reloadPasswordlessRoutes();

    expect(Route::has('passwordless.dev-login.index'))->toBeFalse();
    $this->getJson('/auth/dev-login')->assertStatus(404);
});

it('refuses production even when the environment is explicitly allowed', function () {
    $this->app['env'] = 'production';
    $this->app['config']->set('app.env', 'production');
    $this->app['config']->set('passwordless.dev_login.enabled', true);
    $this->app['config']->set('passwordless.dev_login.environments', ['production']);

    try {
        $this->reloadPasswordlessRoutes();

        expect($this->app->isProduction())->toBeTrue();
        expect(Route::has('passwordless.dev-login.index'))->toBeFalse();
        $this->getJson('/auth/dev-login')->assertStatus(404);
    } finally {
        // Restore before teardown: migration rollback prompts for confirmation
        // while the app reports itself as production.
        $this->app['env'] = 'testing';
        $this->app['config']->set('app.env', 'testing');
    }
});

it('does not register the routes when the environment matches but it is disabled', function () {
    $this->app['config']->set('passwordless.dev_login.enabled', false);
    $this->app['config']->set('passwordless.dev_login.environments', ['testing']);

    $this->reloadPasswordlessRoutes();

    expect(Route::has('passwordless.dev-login.index'))->toBeFalse();
});

it('registers the routes only when all three conditions hold', function () {
    $this->app['config']->set('passwordless.dev_login.enabled', true);
    $this->app['config']->set('passwordless.dev_login.environments', ['testing']);

    $this->reloadPasswordlessRoutes();

    expect(Route::has('passwordless.dev-login.index'))->toBeTrue();
    expect(Route::has('passwordless.dev-login.store'))->toBeTrue();
});

it('treats a truthy-but-not-true enabled value as disabled', function () {
    // Guard uses a strict === true comparison, so "1" from a stray env var
    // does not silently switch it on.
    $this->app['config']->set('passwordless.dev_login.enabled', '1');
    $this->app['config']->set('passwordless.dev_login.environments', ['testing']);

    $this->reloadPasswordlessRoutes();

    expect(Route::has('passwordless.dev-login.index'))->toBeFalse();
});
