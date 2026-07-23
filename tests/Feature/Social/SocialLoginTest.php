<?php

use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Models\SocialAccount;
use Workbench\App\Models\User;

/**
 * Bind a mocked Socialite factory whose driver returns a canned OAuth user.
 * No real HTTP handshake.
 */
function fakeSocialite(array $attributes = [], array $raw = []): void
{
    $oauth = new SocialiteUser;
    $oauth->map(array_merge([
        'id' => 'prov-123',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'nickname' => 'ada',
        'avatar' => 'https://img.test/ada.png',
    ], $attributes));
    $oauth->setRaw($raw);
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

beforeEach(fn () => config()->set('passwordless.social.providers', ['google']));

it('redirects to the provider', function () {
    fakeSocialite();

    $this->get('/auth/social/google/redirect')->assertRedirect('https://provider.test/oauth');
});

it('404s for a disabled provider', function () {
    $this->get('/auth/social/github/redirect')->assertNotFound();
});

it('auto-registers a new verified social email and logs in', function () {
    config()->set('passwordless.social.auto_register', true);
    fakeSocialite(['email' => 'new@example.com', 'id' => 'g-1']);

    $this->get('/auth/social/google/callback')->assertRedirect();

    expect(auth()->check())->toBeTrue();
    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
    $this->assertDatabaseHas('passwordless_social_accounts', [
        'provider' => 'google', 'provider_id' => 'g-1',
    ]);
});

it('links to an existing user by email', function () {
    $user = User::create(['email' => 'ada@example.com', 'name' => 'Ada', 'password' => bcrypt('x')]);
    fakeSocialite(['email' => 'ada@example.com', 'id' => 'g-2']);

    $this->get('/auth/social/google/callback');

    expect(auth()->id())->toBe($user->id);
    $this->assertDatabaseHas('passwordless_social_accounts', [
        'user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-2',
    ]);
    expect(User::count())->toBe(1); // no new user created
});

it('logs in a returning identity and refreshes tokens', function () {
    $user = User::create(['email' => 'ada@example.com', 'name' => 'Ada', 'password' => bcrypt('x')]);
    SocialAccount::create([
        'user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-3',
        'email' => 'ada@example.com', 'token' => 'old-token',
    ]);

    fakeSocialite(['email' => 'ada@example.com', 'id' => 'g-3']);
    $this->get('/auth/social/google/callback');

    expect(auth()->id())->toBe($user->id);
    expect(SocialAccount::where('provider_id', 'g-3')->first()->token)->toBe('tok-abc'); // refreshed
    expect(SocialAccount::count())->toBe(1);
});

it('denies auto-register when disabled', function () {
    config()->set('passwordless.social.auto_register', false);
    fakeSocialite(['email' => 'nobody@example.com', 'id' => 'g-4']);

    $this->get('/auth/social/google/callback')->assertStatus(403);

    expect(auth()->check())->toBeFalse();
    expect(User::where('email', 'nobody@example.com')->exists())->toBeFalse();
});

it('enforces the domain matrix on social register', function () {
    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce.social.register', true);
    fakeSocialite(['email' => 'x@other.com', 'id' => 'g-5']);

    $this->get('/auth/social/google/callback')->assertStatus(403);
    expect(User::where('email', 'x@other.com')->exists())->toBeFalse();
});

it('enforces the domain matrix on social login for an existing user', function () {
    User::create(['email' => 'bob@other.com', 'name' => 'Bob', 'password' => bcrypt('x')]);
    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce.social.login', true);
    fakeSocialite(['email' => 'bob@other.com', 'id' => 'g-6']);

    $this->get('/auth/social/google/callback')->assertStatus(403);
    expect(auth()->check())->toBeFalse();
});

it('stores tokens encrypted at rest', function () {
    fakeSocialite(['id' => 'g-7']);
    $this->get('/auth/social/google/callback');

    $raw = DB::table('passwordless_social_accounts')->where('provider_id', 'g-7')->value('token');
    expect($raw)->not->toBe('tok-abc');                                  // encrypted in the column
    expect(SocialAccount::where('provider_id', 'g-7')->first()->token)->toBe('tok-abc'); // decrypts via cast
});

it('refuses to link an unverified email from an untrusted provider', function () {
    config()->set('passwordless.social.providers', ['twitter']); // not in trusted_providers
    User::create(['email' => 'victim@example.com', 'name' => 'Victim', 'password' => bcrypt('x')]);
    fakeSocialite(['email' => 'victim@example.com', 'id' => 't-1']); // no email_verified claim

    $this->get('/auth/social/twitter/callback')->assertStatus(403);

    expect(auth()->check())->toBeFalse();
    $this->assertDatabaseMissing('passwordless_social_accounts', ['provider' => 'twitter']);
});

it('refuses when the provider explicitly reports the email unverified', function () {
    config()->set('passwordless.social.providers', ['google']); // trusted, but claim says false
    User::create(['email' => 'victim@example.com', 'name' => 'Victim', 'password' => bcrypt('x')]);
    fakeSocialite(['email' => 'victim@example.com', 'id' => 'g-9'], ['email_verified' => false]);

    $this->get('/auth/social/google/callback')->assertStatus(403);
    expect(auth()->check())->toBeFalse();
});

it('links when an untrusted provider explicitly reports the email verified', function () {
    config()->set('passwordless.social.providers', ['twitter']);
    $user = User::create(['email' => 'ada@example.com', 'name' => 'Ada', 'password' => bcrypt('x')]);
    fakeSocialite(['email' => 'ada@example.com', 'id' => 't-2'], ['email_verified' => true]);

    $this->get('/auth/social/twitter/callback');

    expect(auth()->id())->toBe($user->id);
});

it('fails closed with 401 on an invalid OAuth state', function () {
    config()->set('passwordless.social.providers', ['google']);

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')->andReturn($provider);
    app()->instance(SocialiteFactory::class, $factory);

    $this->get('/auth/social/google/callback')->assertStatus(401);
    expect(auth()->check())->toBeFalse();
});

it('applies the passwordless throttle to the social routes', function () {
    $routes = app('router')->getRoutes();

    foreach (['passwordless.social.redirect', 'passwordless.social.callback'] as $name) {
        $middleware = collect($routes->getByName($name)->gatherMiddleware());
        expect($middleware->contains(fn ($m) => is_string($m) && str_contains($m, 'PasswordlessThrottle')))
            ->toBeTrue("{$name} is not throttled");
    }
});

it('redirects to the middleware-set intended URL after callback', function () {
    fakeSocialite(['email' => 'ada@example.com', 'id' => 'g-int']);

    $this->withSession(['url.intended' => 'http://localhost/deep/link'])
        ->get('/auth/social/google/callback')
        ->assertRedirect('http://localhost/deep/link');
});

it('redirects to the redirectUsing closure when no intended URL is set', function () {
    User::create(['email' => 'ada@example.com', 'name' => 'Ada', 'password' => bcrypt('x')]);
    Passwordless::redirectUsing(fn ($user, $request) => '/admin');
    fakeSocialite(['email' => 'ada@example.com', 'id' => 'g-clo']);

    $this->get('/auth/social/google/callback')->assertRedirect('/admin');
});

it('redirects to the config fallback when no intended URL and no closure', function () {
    config()->set('passwordless.redirect', '/welcome');
    User::create(['email' => 'ada@example.com', 'name' => 'Ada', 'password' => bcrypt('x')]);
    fakeSocialite(['email' => 'ada@example.com', 'id' => 'g-cfg']);

    $this->get('/auth/social/google/callback')->assertRedirect('/welcome');
});

it('returns a token payload in api_mode', function () {
    config()->set('passwordless.api_mode', true);
    fakeSocialite(['email' => 'api@example.com', 'id' => 'g-8']);

    $res = $this->getJson('/auth/social/google/callback')->assertOk();
    expect($res->json('user.email'))->toBe('api@example.com');
    expect(auth()->check())->toBeFalse(); // api mode does not open a session
});
