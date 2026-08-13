<?php

use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Webteractive\Passwordless\Notifications\LoginCodeNotification;
use Workbench\App\Models\TwoFactorUser;

/**
 * Drives Fortify's REAL challenge controller rather than asserting on our own
 * session keys. That is the point: if a future Fortify release renames
 * `login.id` or `two-factor.login`, this fails loudly instead of the package
 * silently handing out unchallenged logins.
 */
beforeEach(function () {
    config()->set('passwordless.user_model', TwoFactorUser::class);
    // Fortify resolves the challenged user through the AUTH provider's model,
    // not through passwordless.user_model. In a real starter kit both point at
    // the same App\Models\User; the harness has to mirror that or Fortify loads
    // a model without the TwoFactorAuthenticatable trait.
    config()->set('auth.providers.users.model', TwoFactorUser::class);
});

function passwordlessCodeForE2E(string $email): string
{
    Notification::fake();
    test()->postJson('/auth/login-code', ['email' => $email])->assertStatus(202);

    $code = null;
    Notification::assertSentTo(
        TwoFactorUser::where('email', $email)->first(),
        LoginCodeNotification::class,
        function ($n) use (&$code) {
            $code = $n->code;

            return true;
        }
    );

    return $code;
}

it('completes a real Fortify TOTP challenge after a passwordless verify', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    $user = TwoFactorUser::create([
        'email' => 'e2e@example.com',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb'])),
        'two_factor_confirmed_at' => now(),
    ]);

    // 1. The passwordless verify must challenge, not authenticate.
    $code = passwordlessCodeForE2E('e2e@example.com');

    $this->post('/auth/login-code/verify', ['email' => 'e2e@example.com', 'code' => $code])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse();

    // 2. Fortify's own challenge endpoint must finish the login.
    $this->post(route('two-factor.login'), ['code' => $google2fa->getCurrentOtp($secret)])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->getKey());
});

it('completes the challenge with a Fortify recovery code', function () {
    $user = TwoFactorUser::create([
        'email' => 'e2e-recovery@example.com',
        'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['cccc-dddd'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $code = passwordlessCodeForE2E('e2e-recovery@example.com');

    $this->post('/auth/login-code/verify', ['email' => 'e2e-recovery@example.com', 'code' => $code])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login'), ['recovery_code' => 'cccc-dddd'])
        ->assertRedirect();

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->getKey());
});

it('rejects a wrong TOTP code and leaves the user unauthenticated', function () {
    TwoFactorUser::create([
        'email' => 'e2e-bad@example.com',
        'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['eeee-ffff'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $code = passwordlessCodeForE2E('e2e-bad@example.com');

    $this->post('/auth/login-code/verify', ['email' => 'e2e-bad@example.com', 'code' => $code])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login'), ['code' => '000000']);

    expect(auth()->check())->toBeFalse();
});

it('honours the remember flag through the full challenge', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey();

    TwoFactorUser::create([
        'email' => 'e2e-remember@example.com',
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['gggg-hhhh'])),
        'two_factor_confirmed_at' => now(),
    ]);

    Notification::fake();
    $this->postJson('/auth/login-code', ['email' => 'e2e-remember@example.com', 'remember' => true])
        ->assertStatus(202);

    $code = null;
    Notification::assertSentTo(
        TwoFactorUser::where('email', 'e2e-remember@example.com')->first(),
        LoginCodeNotification::class,
        function ($n) use (&$code) {
            $code = $n->code;

            return true;
        }
    );

    $this->post('/auth/login-code/verify', ['email' => 'e2e-remember@example.com', 'code' => $code])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login'), ['code' => $google2fa->getCurrentOtp($secret)])
        ->assertRedirect();

    // Fortify read login.remember out of the session and logged in with it.
    expect(TwoFactorUser::where('email', 'e2e-remember@example.com')->first()->remember_token)
        ->not->toBeNull();
});
