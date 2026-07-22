<?php

it('publishes config with expected defaults', function () {
    expect(config('passwordless.user_email_column'))->toBe('email');
    expect(config('passwordless.auto_create_users'))->toBeFalse();
    expect(config('passwordless.guard'))->toBe('web');
    expect(config('passwordless.route_prefix'))->toBe('auth');
    expect(config('passwordless.resend_cooldown'))->toBe(30);
    expect(config('passwordless.lockout.max_attempts'))->toBe(5);
    expect(config('passwordless.strategies.magic_link.enabled'))->toBeTrue();
    expect(config('passwordless.strategies.magic_link.ttl'))->toBe(15 * 60);
    expect(config('passwordless.strategies.login_code.length'))->toBe(6);
    expect(config('passwordless.strategies.login_code.channel'))->toBe('mail');
});
