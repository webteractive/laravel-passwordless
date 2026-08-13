<?php

use Illuminate\Http\Request;
use Webteractive\Passwordless\Support\AuthCompletion;
use Workbench\App\Models\User;

function completion(): AuthCompletion
{
    return app(AuthCompletion::class);
}

function sessionBackedRequest(): Request
{
    $request = Request::create('/', 'POST');
    $request->setLaravelSession(app('session.store'));

    return $request;
}

it('logs the user in and returns null in session mode', function () {
    $user = User::create(['email' => 'ac1@example.com']);

    $response = completion()->complete($user, sessionBackedRequest());

    expect($response)->toBeNull();
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->getKey());
});

it('does not set a remember token when remember is false', function () {
    $user = User::create(['email' => 'ac2@example.com']);

    completion()->complete($user, sessionBackedRequest(), false);

    expect($user->fresh()->remember_token)->toBeNull();
});

it('sets a remember token when remember is true', function () {
    $user = User::create(['email' => 'ac3@example.com']);

    completion()->complete($user, sessionBackedRequest(), true);

    expect($user->fresh()->remember_token)->not->toBeNull();
});

it('returns a token payload in api_mode without touching the session guard', function () {
    config()->set('passwordless.api_mode', true);

    $user = User::create(['email' => 'ac4@example.com']);

    $response = completion()->complete($user, Request::create('/', 'POST'));

    expect($response)->not->toBeNull();
    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toHaveKeys(['token', 'user']);
    expect(auth()->check())->toBeFalse();
});

it('returns a null token in api_mode when the model cannot create tokens', function () {
    config()->set('passwordless.api_mode', true);

    $user = User::create(['email' => 'ac5@example.com']);

    $response = completion()->complete($user, Request::create('/', 'POST'));

    expect(json_decode($response->getContent(), true)['token'])->toBeNull();
});
