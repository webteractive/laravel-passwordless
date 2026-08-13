<?php

namespace Webteractive\Passwordless\Support;

use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\IdentityConfirmationNotification;
use Webteractive\Passwordless\Strategies\LoginCode\CodeGenerator;

/**
 * Re-confirms an already-authenticated user's identity with an emailed code, so
 * a password-less account can satisfy Laravel's `password.confirm` middleware
 * and reach Fortify's two-factor settings.
 *
 * Its cooldown and lockout live under a dedicated `confirm` key namespace: a
 * user mid-login-cooldown must still be able to reach their security settings,
 * and failing confirmation attempts must never lock them out of logging in.
 */
class IdentityConfirmation
{
    protected const KEY = 'confirm';

    protected const TYPE = 'confirm';

    public function __construct(
        protected CodeGenerator $generator,
        protected TokenHasher $hasher,
        protected ResendCooldown $cooldown,
        protected Lockout $lockout,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(mixed $user, array $context = []): void
    {
        $email = $this->emailFor($user);

        if (($remaining = $this->cooldown->remaining(self::KEY, $email)) > 0) {
            throw new ConfirmationResendCooldownException($remaining);
        }

        if (($lockedFor = $this->lockout->lockedFor(self::KEY, $email)) > 0) {
            throw new ConfirmationLockedException($lockedFor);
        }

        $length = (int) config('passwordless.confirmation.length', 6);
        $ttl = (int) config('passwordless.confirmation.ttl', 10 * 60);

        $code = $this->generator->generate($length);

        // One live confirmation code per user, same rule as login codes.
        Challenge::query()
            ->where('user_id', $user->getKey())
            ->where('type', self::TYPE)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        Challenge::query()->create([
            'user_id' => $user->getKey(),
            'type' => self::TYPE,
            'hash' => $this->hasher->hash($code),
            'metadata' => [
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
            ],
            'expires_at' => now()->addSeconds($ttl),
        ]);

        if (method_exists($user, 'notify')) {
            $user->notify(new IdentityConfirmationNotification($code, $ttl));
        }

        $this->cooldown->start(self::KEY, $email);
    }

    /**
     * Returns a plain bool because that is what Fortify's
     * `confirmPasswordsUsing` callback contract expects.
     */
    public function verify(mixed $user, string $code): bool
    {
        $email = $this->emailFor($user);

        if ($this->lockout->lockedFor(self::KEY, $email) > 0) {
            return false;
        }

        $hash = $this->hasher->hash($this->generator->normalize($code));

        /** @var Challenge|null $challenge */
        $challenge = Challenge::query()
            ->where('user_id', $user->getKey())
            ->where('type', self::TYPE)
            ->where('hash', $hash)
            ->first();

        if (! $challenge || ! $challenge->isActive()) {
            $this->lockout->recordFailure(self::KEY, $email);

            return false;
        }

        // Atomic single-use consumption, same rationale as the login-code path.
        $claimed = Challenge::query()
            ->whereKey($challenge->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($claimed !== 1) {
            $this->lockout->recordFailure(self::KEY, $email);

            return false;
        }

        $this->lockout->clear(self::KEY, $email);

        return true;
    }

    protected function emailFor(mixed $user): string
    {
        return (string) $user->{config('passwordless.user_email_column', 'email')};
    }
}
