<?php

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Webteractive\Passwordless\Facades\Passwordless;
use Webteractive\Passwordless\Support\TwoFactor;
use Webteractive\Passwordless\Support\TwoFactorChallengeUnavailableException;
use Webteractive\Passwordless\Support\TwoFactorGuardMismatchException;
use Workbench\App\Models\TwoFactorUser;
use Workbench\App\Models\User;

function enrolled(string $email): TwoFactorUser
{
    return TwoFactorUser::create([
        'email' => $email,
        'two_factor_secret' => encrypt('SECRET'),
        'two_factor_confirmed_at' => now(),
    ]);
}

function twoFactorRequest(array $server = []): Request
{
    $request = Request::create('/', 'POST', server: $server);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

it('is reachable from the manager', function () {
    expect(Passwordless::twoFactor())->toBeInstanceOf(TwoFactor::class);
});

it('does not require a challenge for a model without the trait', function () {
    $user = User::create(['email' => 'tf1@example.com']);

    expect(app(TwoFactor::class)->required($user))->toBeFalse();
});

it('does not require a challenge when no secret is set', function () {
    $user = TwoFactorUser::create(['email' => 'tf2@example.com']);

    expect(app(TwoFactor::class)->required($user))->toBeFalse();
});

it('does not require a challenge when the secret is unconfirmed', function () {
    $user = TwoFactorUser::create([
        'email' => 'tf3@example.com',
        'two_factor_secret' => encrypt('SECRET'),
    ]);

    expect(app(TwoFactor::class)->required($user))->toBeFalse();
});

it('requires a challenge for a confirmed enrolment', function () {
    expect(app(TwoFactor::class)->required(enrolled('tf4@example.com')))->toBeTrue();
});

it('treats a null user as not requiring a challenge', function () {
    expect(app(TwoFactor::class)->required(null))->toBeFalse();
});

it('stashes the login session keys and redirects to the challenge', function () {
    $user = enrolled('tf5@example.com');
    $request = twoFactorRequest();

    Event::fake([TwoFactorAuthenticationChallenged::class]);

    $response = app(TwoFactor::class)->challenge($user, $request, true);

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toBe(route('two-factor.login'));
    expect($request->session()->get('login.id'))->toBe($user->getKey());
    expect($request->session()->get('login.remember'))->toBeTrue();

    Event::assertDispatched(TwoFactorAuthenticationChallenged::class);
});

it('defaults login.remember to false', function () {
    $user = enrolled('tf5b@example.com');
    $request = twoFactorRequest();

    app(TwoFactor::class)->challenge($user, $request);

    expect($request->session()->get('login.remember'))->toBeFalse();
});

it('returns a json flag for json requests', function () {
    $user = enrolled('tf6@example.com');
    $request = twoFactorRequest(['HTTP_ACCEPT' => 'application/json']);

    $response = app(TwoFactor::class)->challenge($user, $request);

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toBe(['two_factor' => true]);
});

it('fails closed when the challenge route is missing', function () {
    // Strip Fortify's two-factor routes to simulate the feature being disabled.
    $routes = app('router')->getRoutes();
    $fresh = new RouteCollection;

    foreach ($routes as $route) {
        if (! str_starts_with((string) $route->getName(), 'two-factor.')) {
            $fresh->add($route);
        }
    }

    app('router')->setRoutes($fresh);

    expect(Route::has('two-factor.login'))->toBeFalse();

    app(TwoFactor::class)->challenge(enrolled('tf7@example.com'), twoFactorRequest());
})->throws(TwoFactorChallengeUnavailableException::class);

it('fails closed when the fortify and passwordless guards differ', function () {
    config()->set('passwordless.guard', 'other');

    app(TwoFactor::class)->challenge(enrolled('tf8@example.com'), twoFactorRequest());
})->throws(TwoFactorGuardMismatchException::class);

it('names both config keys in the guard mismatch message', function () {
    config()->set('passwordless.guard', 'sanctum');
    config()->set('fortify.guard', 'web');

    try {
        app(TwoFactor::class)->challenge(enrolled('tf9@example.com'), twoFactorRequest());
    } catch (TwoFactorGuardMismatchException $e) {
        expect($e->getMessage())->toContain('sanctum')->toContain('web');

        return;
    }

    $this->fail('Expected TwoFactorGuardMismatchException.');
});

it('does not write login session keys when it fails closed', function () {
    config()->set('passwordless.guard', 'other');
    $request = twoFactorRequest();

    try {
        app(TwoFactor::class)->challenge(enrolled('tf10@example.com'), $request);
    } catch (TwoFactorGuardMismatchException) {
        // expected
    }

    expect($request->session()->has('login.id'))->toBeFalse();
});
