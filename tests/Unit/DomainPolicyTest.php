<?php

use Webteractive\Passwordless\Support\DomainNotAllowedException;
use Webteractive\Passwordless\Support\DomainPolicy;

beforeEach(function () {
    config()->set('passwordless.domains.allowed', ['acme.com']);
    config()->set('passwordless.domains.enforce', [
        'passwordless' => ['login' => false, 'register' => true],
        'social' => ['login' => true, 'register' => true],
    ]);
});

it('allows any domain when the allowed list is empty', function () {
    config()->set('passwordless.domains.allowed', []);

    expect(DomainPolicy::allows('social', 'register', 'x@other.com'))->toBeTrue();
});

it('allows when enforcement is off for that type and action', function () {
    expect(DomainPolicy::allows('passwordless', 'login', 'x@other.com'))->toBeTrue();
});

it('blocks a non-allowed domain when enforced', function () {
    expect(DomainPolicy::allows('social', 'register', 'x@other.com'))->toBeFalse();
    expect(DomainPolicy::allows('social', 'register', 'x@acme.com'))->toBeTrue();
});

it('is case-insensitive on the domain', function () {
    expect(DomainPolicy::allows('social', 'login', 'X@ACME.COM'))->toBeTrue();
});

it('check() throws for a disallowed domain and is silent otherwise', function () {
    DomainPolicy::check('social', 'login', 'a@acme.com');

    expect(fn () => DomainPolicy::check('social', 'login', 'a@other.com'))
        ->toThrow(DomainNotAllowedException::class);
});
