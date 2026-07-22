<?php

namespace Webteractive\Passwordless\Strategies\MagicLink;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Events\AuthenticationDenied;
use Webteractive\Passwordless\Events\MagicLinkConsumed;
use Webteractive\Passwordless\Events\MagicLinkRequested;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Models\Challenge;
use Webteractive\Passwordless\Notifications\MagicLinkNotification;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Support\BrowserCookie;
use Webteractive\Passwordless\Support\ResendCooldown;
use Webteractive\Passwordless\Support\TokenHasher;
use Webteractive\Passwordless\Support\UserResolver;

class DefaultMagicLinkStrategy implements MagicLinkStrategy
{
    public function __construct(
        protected Container $container,
        protected TokenHasher $hasher,
        protected ResendCooldown $cooldown,
        protected BrowserCookie $browserCookie,
        protected UserResolver $users,
    ) {}

    public function send(string $email, array $context = []): void
    {
        if ($this->cooldown->remaining('magic_link', $email) > 0) {
            throw new MagicLinkResendCooldownException(
                $this->cooldown->remaining('magic_link', $email)
            );
        }

        $sameBrowser = (bool) config('passwordless.strategies.magic_link.same_browser', true);
        $ttl = (int) config('passwordless.strategies.magic_link.ttl', 15 * 60);

        // Always run the crypto work and (when same_browser is enabled) cookie
        // generation regardless of whether the user exists. Combined with
        // ShouldQueue notifications, this keeps the timing of the two paths
        // close enough that response time is not a meaningful enumeration
        // oracle. The remaining delta is one INSERT and one queue dispatch.
        $token = $this->hasher->generate();
        $hash = $this->hasher->hash($token);
        $browserToken = $sameBrowser ? $this->browserCookie->generate() : null;

        $user = $this->users->findOrCreate($email);

        if ($user) {
            $metadata = [
                'ip' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'intended_url' => $context['intended_url'] ?? null,
            ];

            if ($browserToken !== null) {
                $metadata['browser_hash'] = $this->browserCookie->hash($browserToken);
            }

            Challenge::query()->create([
                'user_id' => $user->getKey(),
                'type' => 'link',
                'hash' => $hash,
                'metadata' => $metadata,
                'expires_at' => now()->addSeconds($ttl),
            ]);

            $url = URL::temporarySignedRoute(
                config('passwordless.strategies.magic_link.route_name', 'passwordless.magic-link.consume'),
                now()->addSeconds($ttl),
                ['token' => $token]
            );

            if (method_exists($user, 'notify')) {
                $user->notify(new MagicLinkNotification($url));
            }

            if ($browserToken !== null) {
                Cookie::queue($this->browserCookie->make($browserToken, $ttl));
            }
        }

        event(new MagicLinkRequested($email, $user, $context));
        $this->cooldown->start('magic_link', $email);
    }

    public function consume(string $token, Request $request): mixed
    {
        $hash = $this->hasher->hash($token);

        /** @var Challenge|null $challenge */
        $challenge = Challenge::query()
            ->where('type', 'link')
            ->where('hash', $hash)
            ->first();

        if (! $challenge || ! $challenge->isActive()) {
            throw new MagicLinkInvalidException;
        }

        // Drive the same-browser check off the stored metadata, not the current
        // config. If the row was created with a browser_hash, it must match —
        // regardless of whether the operator has since toggled the config.
        $expectedBrowserHash = $challenge->metadata['browser_hash'] ?? null;
        if ($expectedBrowserHash !== null) {
            $cookie = $this->browserCookie->fromRequest($request);

            if (! $cookie || ! hash_equals($expectedBrowserHash, $this->browserCookie->hash($cookie))) {
                throw new MagicLinkDifferentBrowserException;
            }
        }

        $userModel = config('passwordless.user_model');
        $user = $userModel::query()->find($challenge->user_id);

        if (! $user) {
            throw new MagicLinkInvalidException;
        }

        /** @var Passwordless $manager */
        $manager = $this->container->make(Passwordless::class);
        $decision = $manager->runGate($user, ['strategy' => 'magic_link']);

        if (! $decision->allowed) {
            event(new AuthenticationDenied('magic_link', $user, $decision->reason ?? 'denied'));
            throw new MagicLinkGateDeniedException($decision->reason ?? 'denied');
        }

        // Atomic single-use consumption: only one parallel request can flip
        // consumed_at from null to now(). The loser sees affected=0 and is
        // told the link is invalid.
        $claimed = Challenge::query()
            ->whereKey($challenge->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($claimed !== 1) {
            throw new MagicLinkInvalidException;
        }

        event(new MagicLinkConsumed($user, ['intended_url' => $challenge->metadata['intended_url'] ?? null]));
        event(new UserAuthenticated('magic_link', $user));

        return $user;
    }
}
