<?php

namespace Webteractive\Passwordless\Strategies\MagicCode;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Events\AuthenticationDenied;
use Webteractive\Passwordless\Events\MagicCodeConsumed;
use Webteractive\Passwordless\Events\MagicCodeFailed;
use Webteractive\Passwordless\Events\MagicCodeRequested;
use Webteractive\Passwordless\Events\MagicCodeVerified;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicCodeNotification;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Strategies\LoginCode\CodeGenerator;
use Webteractive\Passwordless\Support\BrowserCookie;
use Webteractive\Passwordless\Support\DomainPolicy;
use Webteractive\Passwordless\Support\Lockout;
use Webteractive\Passwordless\Support\ResendCooldown;
use Webteractive\Passwordless\Support\TokenHasher;
use Webteractive\Passwordless\Support\UserResolver;

/**
 * One email, two ways in: a magic link (browser-bound) and a numeric code
 * (device-agnostic). Both are stored as sibling challenge rows sharing a
 * `magic_code_id`; consuming either logs the user in and invalidates the other.
 */
class DefaultMagicCodeStrategy implements MagicCodeStrategy
{
    protected const LINK = 'mc_link';

    protected const CODE = 'mc_code';

    public function __construct(
        protected Container $container,
        protected TokenHasher $hasher,
        protected CodeGenerator $generator,
        protected ResendCooldown $cooldown,
        protected Lockout $lockout,
        protected BrowserCookie $browserCookie,
        protected UserResolver $users,
    ) {}

    public function send(string $email, array $context = []): void
    {
        if ($this->cooldown->remaining('magic_code', $email) > 0) {
            throw new MagicCodeResendCooldownException($this->cooldown->remaining('magic_code', $email));
        }

        if (($lockedFor = $this->lockout->lockedFor('magic_code', $email)) > 0) {
            throw new MagicCodeLockedException($lockedFor);
        }

        $sameBrowser = (bool) config('passwordless.strategies.magic_code.same_browser', true);
        $ttl = (int) config('passwordless.strategies.magic_code.ttl', 15 * 60);
        $length = (int) config('passwordless.strategies.magic_code.code.length', 6);

        // Generate both secrets unconditionally so the timing of the unknown-user
        // path matches the known-user path.
        $token = $this->hasher->generate();
        $tokenHash = $this->hasher->hash($token);
        $code = $this->generator->generate($length);
        $codeHash = $this->hasher->hash($code);
        $browserToken = $sameBrowser ? $this->browserCookie->generate() : null;

        $user = $this->users->findOrCreate($email);

        if ($user) {
            $expiresAt = now()->addSeconds($ttl);
            $base = [
                'magic_code_id' => (string) Str::uuid(),
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'intended_url' => $context['intended_url'] ?? null,
            ];

            $linkMetadata = $base;
            if ($browserToken !== null) {
                $linkMetadata['browser_hash'] = $this->browserCookie->hash($browserToken);
            }

            // The pair is one logical challenge: retire any prior unconsumed
            // pair and insert the new link + code atomically (all or nothing).
            DB::transaction(function () use ($user, $tokenHash, $codeHash, $linkMetadata, $base, $expiresAt) {
                Challenge::query()
                    ->where('user_id', $user->getKey())
                    ->whereIn('type', [self::LINK, self::CODE])
                    ->whereNull('consumed_at')
                    ->update(['consumed_at' => now()]);

                Challenge::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => self::LINK,
                    'hash' => $tokenHash,
                    'metadata' => $linkMetadata,
                    'expires_at' => $expiresAt,
                ]);

                Challenge::query()->create([
                    'user_id' => $user->getKey(),
                    'type' => self::CODE,
                    'hash' => $codeHash,
                    'metadata' => $base,
                    'expires_at' => $expiresAt,
                ]);
            });

            $url = URL::temporarySignedRoute(
                config('passwordless.strategies.magic_code.route_name', 'passwordless.magic-code.consume'),
                $expiresAt,
                ['token' => $token]
            );

            if (method_exists($user, 'notify')) {
                $user->notify(new MagicCodeNotification($url, $code, $ttl));
            }

            if ($browserToken !== null) {
                Cookie::queue($this->browserCookie->make($browserToken, $ttl));
            }
        }

        event(new MagicCodeRequested($email, $user, $context));
        $this->cooldown->start('magic_code', $email);
    }

    public function consume(string $token, Request $request): mixed
    {
        $hash = $this->hasher->hash($token);

        /** @var Challenge|null $challenge */
        $challenge = Challenge::query()
            ->where('type', self::LINK)
            ->where('hash', $hash)
            ->first();

        if (! $challenge || ! $challenge->isActive()) {
            throw new MagicCodeInvalidException;
        }

        // Drive the same-browser check off the stored metadata, not the current
        // config, so toggling the config never changes an in-flight challenge.
        $expectedBrowserHash = $challenge->metadata['browser_hash'] ?? null;
        if ($expectedBrowserHash !== null) {
            $cookie = $this->browserCookie->fromRequest($request);

            if (! $cookie || ! hash_equals($expectedBrowserHash, $this->browserCookie->hash($cookie))) {
                throw new MagicCodeDifferentBrowserException;
            }
        }

        $userModel = config('passwordless.user_model');
        $user = $userModel::query()->find($challenge->user_id);

        if (! $user) {
            throw new MagicCodeInvalidException;
        }

        $email = $user->{config('passwordless.user_email_column', 'email')};
        $this->ensureAllowed($user, $email);

        // Atomic single-use consumption: only one request flips consumed_at.
        $claimed = Challenge::query()
            ->whereKey($challenge->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($claimed !== 1) {
            throw new MagicCodeInvalidException;
        }

        $this->invalidateSibling($challenge, self::CODE);

        event(new MagicCodeConsumed($user, ['intended_url' => $challenge->metadata['intended_url'] ?? null]));
        event(new UserAuthenticated('magic_code', $user));

        return $user;
    }

    public function verify(string $email, string $code, Request $request): mixed
    {
        if (($lockedFor = $this->lockout->lockedFor('magic_code', $email)) > 0) {
            throw new MagicCodeLockedException($lockedFor);
        }

        $user = $this->users->findByEmail($email);

        $normalized = $this->generator->normalize($code);
        $hash = $this->hasher->hash($normalized);

        // Look the challenge up unconditionally so the timing of the unknown-user
        // path matches the user-exists-but-bad-code path.
        $challenge = $user
            ? Challenge::query()
                ->where('user_id', $user->getKey())
                ->where('type', self::CODE)
                ->where('hash', $hash)
                ->first()
            : null;

        if (! $user || ! $challenge || ! $challenge->isActive()) {
            $this->lockout->recordFailure('magic_code', $email);
            event(new MagicCodeFailed($email, 'invalid_or_expired'));
            throw new MagicCodeInvalidException;
        }

        $this->ensureAllowed($user, $email);

        $claimed = Challenge::query()
            ->whereKey($challenge->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($claimed !== 1) {
            $this->lockout->recordFailure('magic_code', $email);
            event(new MagicCodeFailed($email, 'invalid_or_expired'));
            throw new MagicCodeInvalidException;
        }

        $this->invalidateSibling($challenge, self::LINK);

        $this->lockout->clear('magic_code', $email);

        event(new MagicCodeVerified($user));
        event(new UserAuthenticated('magic_code', $user));

        return $user;
    }

    /**
     * Shared authorization gate for both auth paths: domain policy first, then
     * the app's pre-auth gate. Throws on denial (both paths surface it the same).
     */
    protected function ensureAllowed(mixed $user, string $email): void
    {
        if (! DomainPolicy::allows('passwordless', 'login', $email)) {
            event(new AuthenticationDenied('magic_code', $user, 'domain_not_allowed'));
            throw new MagicCodeGateDeniedException('domain_not_allowed');
        }

        /** @var Passwordless $manager */
        $manager = $this->container->make(Passwordless::class);
        $decision = $manager->runGate($user, ['strategy' => 'magic_code']);

        if (! $decision->allowed) {
            event(new AuthenticationDenied('magic_code', $user, $decision->reason ?? 'denied'));
            throw new MagicCodeGateDeniedException($decision->reason ?? 'denied');
        }
    }

    /**
     * First-used-wins: retire the still-active sibling (the other entry method)
     * of the challenge that was just consumed, matched by the shared id.
     */
    protected function invalidateSibling(Challenge $consumed, string $siblingType): void
    {
        $magicCodeId = $consumed->metadata['magic_code_id'] ?? null;

        if ($magicCodeId === null) {
            return;
        }

        Challenge::query()
            ->where('type', $siblingType)
            ->where('metadata->magic_code_id', $magicCodeId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
