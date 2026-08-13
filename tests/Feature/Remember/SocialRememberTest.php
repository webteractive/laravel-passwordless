<?php

use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Workbench\App\Models\User;

/**
 * Same canned-Socialite approach as tests/Feature/Social/SocialLoginTest.php,
 * duplicated here under its own name because Pest helper functions are global.
 */
function fakeSocialiteForRemember(array $attributes = []): void
{
    $oauth = new SocialiteUser;
    $oauth->map(array_merge([
        'id' => 'rem-123',
        'email' => 'social-remember@example.com',
        'name' => 'Rem Ember',
        'nickname' => 'rem',
        'avatar' => 'https://img.test/rem.png',
    ], $attributes));
    $oauth->setRaw([]);
    $oauth->token = 'tok-abc';
    $oauth->refreshToken = 'ref-xyz';
    $oauth->expiresIn = 3600;

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andReturn($oauth);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://provider.test/oauth'));
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('with')->andReturnSelf();

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')->andReturn($provider);

    app()->instance(SocialiteFactory::class, $factory);
}

beforeEach(function () {
    config()->set('passwordless.social.providers', ['google']);
    config()->set('passwordless.social.auto_register', true);
});

it('stores the remember flag in the session on redirect', function () {
    fakeSocialiteForRemember();

    $this->get('/auth/social/google/redirect?remember=1')->assertRedirect();

    expect(session('passwordless.remember'))->toBeTrue();
});

it('stores false when the flag is absent', function () {
    fakeSocialiteForRemember();

    $this->get('/auth/social/google/redirect')->assertRedirect();

    expect(session('passwordless.remember'))->toBeFalse();
});

it('issues a remember token on callback when the session flag was set', function () {
    fakeSocialiteForRemember();

    $this->withSession(['passwordless.remember' => true])
        ->get('/auth/social/google/callback')
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
    expect(User::where('email', 'social-remember@example.com')->first()->remember_token)->not->toBeNull();
});

it('does not issue a remember token when the session flag is absent', function () {
    fakeSocialiteForRemember();

    $this->get('/auth/social/google/callback')->assertRedirect();

    expect(User::where('email', 'social-remember@example.com')->first()->remember_token)->toBeNull();
});

it('clears the session flag after use', function () {
    fakeSocialiteForRemember();

    $this->withSession(['passwordless.remember' => true])
        ->get('/auth/social/google/callback')
        ->assertRedirect();

    expect(session()->has('passwordless.remember'))->toBeFalse();
});

it('ignores the session flag when the capability is disabled', function () {
    config()->set('passwordless.remember.enabled', false);
    fakeSocialiteForRemember();

    $this->withSession(['passwordless.remember' => true])
        ->get('/auth/social/google/callback')
        ->assertRedirect();

    expect(User::where('email', 'social-remember@example.com')->first()->remember_token)->toBeNull();
});
