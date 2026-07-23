<?php

beforeEach(function () {
    config()->set('passwordless.strategies.magic_code.enabled', true);
    config()->set('passwordless.strategies.magic_code.same_browser', false);
    config()->set('passwordless.api_mode', true);
});

it('returns a token payload on link consume in api_mode', function () {
    $url = captureMagicCode('api-link@example.com')['url'];

    $res = $this->getJson($url)->assertOk();
    expect($res->json('user.email'))->toBe('api-link@example.com');
    expect(auth()->check())->toBeFalse(); // api mode opens no session
});

it('returns a token payload on code verify in api_mode', function () {
    $code = captureMagicCode('api-code@example.com')['code'];

    $res = $this->postJson('/auth/magic-code/verify', ['email' => 'api-code@example.com', 'code' => $code])
        ->assertOk();
    expect($res->json('user.email'))->toBe('api-code@example.com');
    expect(auth()->check())->toBeFalse();
});
