<?php

use Webteractive\Passwordless\Support\TokenHasher;

it('generates a base64url token with no padding', function () {
    $hasher = new TokenHasher;
    $token = $hasher->generate();

    expect($token)->toMatch('/^[A-Za-z0-9_-]+$/');
    expect(strlen($token))->toBeGreaterThanOrEqual(40);
});

it('hashes via sha256', function () {
    $hasher = new TokenHasher;
    expect($hasher->hash('abc'))->toBe(hash('sha256', 'abc'));
});
