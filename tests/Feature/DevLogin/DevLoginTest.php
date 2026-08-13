<?php

use Illuminate\Support\Facades\Event;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Workbench\App\Models\TwoFactorUser;
use Workbench\App\Models\User;

beforeEach(function () {
    config()->set('passwordless.dev_login.enabled', true);
    config()->set('passwordless.dev_login.environments', ['testing']);

    $this->reloadPasswordlessRoutes();
});

it('lists users when enabled', function () {
    User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    User::create(['name' => 'Alan', 'email' => 'alan@example.com']);

    $response = $this->getJson('/auth/dev-login')->assertOk();

    expect($response->json('users'))->toHaveCount(2);
    expect($response->json('users.0'))->toHaveKeys(['id', 'name', 'email']);
});

it('never exposes sensitive columns', function () {
    User::create(['email' => 'secret@example.com', 'password' => bcrypt('x'), 'remember_token' => 'tok']);

    $response = $this->getJson('/auth/dev-login')->assertOk();

    expect($response->json('users.0'))->not->toHaveKey('password');
    expect($response->json('users.0'))->not->toHaveKey('remember_token');
    expect($response->json('users.0'))->not->toHaveKey('two_factor_secret');
});

it('caps the list at the configured limit', function () {
    config()->set('passwordless.dev_login.limit', 2);

    foreach (range(1, 5) as $i) {
        User::create(['email' => "cap{$i}@example.com"]);
    }

    expect($this->getJson('/auth/dev-login')->json('users'))->toHaveCount(2);
});

it('filters the list by email', function () {
    User::create(['email' => 'match@example.com']);
    User::create(['email' => 'other@example.com']);

    $users = $this->getJson('/auth/dev-login?q=match')->json('users');

    expect($users)->toHaveCount(1);
    expect($users[0]['email'])->toBe('match@example.com');
});

it('logs in the selected user', function () {
    $user = User::create(['email' => 'pick@example.com']);

    $this->postJson('/auth/dev-login', ['user' => $user->getKey()])->assertNoContent();

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->getKey());
});

it('honours remember on dev login', function () {
    $user = User::create(['email' => 'pickr@example.com']);

    $this->postJson('/auth/dev-login', ['user' => $user->getKey(), 'remember' => true])->assertNoContent();

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('fires UserAuthenticated with the dev_login strategy', function () {
    Event::fake([UserAuthenticated::class]);
    $user = User::create(['email' => 'evt@example.com']);

    $this->postJson('/auth/dev-login', ['user' => $user->getKey()])->assertNoContent();

    Event::assertDispatched(UserAuthenticated::class, fn ($e) => $e->strategy === 'dev_login');
});

it('404s for an unknown user', function () {
    $this->postJson('/auth/dev-login', ['user' => 99999])->assertStatus(404);
});

it('requires a user id', function () {
    $this->postJson('/auth/dev-login', [])->assertStatus(422);
});

it('bypasses the two-factor challenge by default', function () {
    config()->set('passwordless.user_model', TwoFactorUser::class);
    config()->set('auth.providers.users.model', TwoFactorUser::class);

    $user = TwoFactorUser::create([
        'email' => 'devtf@example.com',
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->postJson('/auth/dev-login', ['user' => $user->getKey()])->assertNoContent();

    expect(auth()->check())->toBeTrue();
});

it('routes through the challenge when dev_login.two_factor is on', function () {
    config()->set('passwordless.user_model', TwoFactorUser::class);
    config()->set('auth.providers.users.model', TwoFactorUser::class);
    config()->set('passwordless.dev_login.two_factor', true);

    $user = TwoFactorUser::create([
        'email' => 'devtf2@example.com',
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->postJson('/auth/dev-login', ['user' => $user->getKey()])
        ->assertOk()
        ->assertExactJson(['two_factor' => true]);

    expect(auth()->check())->toBeFalse();
});
