<?php

namespace Webteractive\Passwordless\Strategies\LoginCode;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\LoginCodeChannel;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Events\AuthenticationDenied;
use Webteractive\Passwordless\Events\LoginCodeFailed;
use Webteractive\Passwordless\Events\LoginCodeRequested;
use Webteractive\Passwordless\Events\LoginCodeVerified;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Support\DomainPolicy;
use Webteractive\Passwordless\Support\Lockout;
use Webteractive\Passwordless\Support\ResendCooldown;
use Webteractive\Passwordless\Support\TokenHasher;
use Webteractive\Passwordless\Support\UserResolver;

class DefaultLoginCodeStrategy implements LoginCodeStrategy
{
    public function __construct(
        protected Container $container,
        protected CodeGenerator $generator,
        protected TokenHasher $hasher,
        protected ResendCooldown $cooldown,
        protected Lockout $lockout,
        protected UserResolver $users,
    ) {}

    public function send(string $email, array $context = []): void
    {
        if ($this->cooldown->remaining('login_code', $email) > 0) {
            throw new LoginCodeResendCooldownException(
                $this->cooldown->remaining('login_code', $email)
            );
        }

        // Locked accounts can't request new codes either. Without this gate, an
        // attacker could keep cycling fresh codes after the lockout window
        // would otherwise be enforced on verify.
        if (($lockedFor = $this->lockout->lockedFor('login_code', $email)) > 0) {
            throw new LoginCodeLockedException($lockedFor);
        }

        $length = (int) config('passwordless.strategies.login_code.length', 6);
        $ttl = (int) config('passwordless.strategies.login_code.ttl', 10 * 60);

        // Generate the code and hash unconditionally so the timing of the
        // unknown-user path matches the known-user path.
        $code = $this->generator->generate($length);
        $hash = $this->hasher->hash($code);

        $user = $this->users->findOrCreate($email);

        if ($user) {
            // Invalidate any prior unconsumed code challenges for this user so
            // a single user only ever has one valid code at a time. Without
            // this, repeated send() calls leave a growing pool of valid codes.
            Challenge::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'code')
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            Challenge::query()->create([
                'user_id' => $user->getKey(),
                'type' => 'code',
                'hash' => $hash,
                'metadata' => [
                    'ip' => $context['ip'] ?? null,
                    'user_agent' => $context['user_agent'] ?? null,
                ],
                'expires_at' => now()->addSeconds($ttl),
            ]);

            $channelName = (string) config('passwordless.strategies.login_code.channel', 'mail');
            /** @var LoginCodeChannel $channel */
            $channel = $this->container->make("passwordless.login_code_channels.{$channelName}");
            $channel->send($user, $email, $code, $context);
        }

        event(new LoginCodeRequested($email, $user, $context));
        $this->cooldown->start('login_code', $email);
    }

    public function verify(string $email, string $code, Request $request): mixed
    {
        if (($lockedFor = $this->lockout->lockedFor('login_code', $email)) > 0) {
            throw new LoginCodeLockedException($lockedFor);
        }

        $user = $this->users->findByEmail($email);

        $normalized = $this->generator->normalize($code);
        $hash = $this->hasher->hash($normalized);

        // Look the challenge up unconditionally so the timing of the
        // unknown-user path matches the user-exists-but-bad-code path.
        $challenge = $user
            ? Challenge::query()
                ->where('user_id', $user->getKey())
                ->where('type', 'code')
                ->where('hash', $hash)
                ->first()
            : null;

        if (! $user || ! $challenge || ! $challenge->isActive()) {
            $this->lockout->recordFailure('login_code', $email);
            event(new LoginCodeFailed($email, 'invalid_or_expired'));
            throw new LoginCodeInvalidException;
        }

        if (! DomainPolicy::allows('passwordless', 'login', $email)) {
            event(new AuthenticationDenied('login_code', $user, 'domain_not_allowed'));
            throw new LoginCodeGateDeniedException('domain_not_allowed');
        }

        /** @var Passwordless $manager */
        $manager = $this->container->make(Passwordless::class);
        $decision = $manager->runGate($user, ['strategy' => 'login_code']);

        if (! $decision->allowed) {
            event(new AuthenticationDenied('login_code', $user, $decision->reason ?? 'denied'));
            throw new LoginCodeGateDeniedException($decision->reason ?? 'denied');
        }

        // Atomic single-use consumption — see DefaultMagicLinkStrategy::consume
        // for rationale.
        $claimed = Challenge::query()
            ->whereKey($challenge->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($claimed !== 1) {
            $this->lockout->recordFailure('login_code', $email);
            event(new LoginCodeFailed($email, 'invalid_or_expired'));
            throw new LoginCodeInvalidException;
        }

        $this->lockout->clear('login_code', $email);

        event(new LoginCodeVerified($user));
        event(new UserAuthenticated('login_code', $user));

        return $user;
    }
}
