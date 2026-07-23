<?php

namespace Webteractive\Passwordless;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Support\AuthEvent;
use Webteractive\Passwordless\Support\Decision;
use Webteractive\Passwordless\Testing\PasswordlessFake;

class Passwordless
{
    protected ?Closure $gate = null;

    protected ?Closure $recorder = null;

    protected ?Closure $socialResolver = null;

    protected ?Closure $redirectResolver = null;

    protected ?PasswordlessFake $fake = null;

    public function __construct(protected Container $container) {}

    public function magicLink(): MagicLinkStrategy
    {
        return $this->container->make(MagicLinkStrategy::class);
    }

    public function loginCode(): LoginCodeStrategy
    {
        return $this->container->make(LoginCodeStrategy::class);
    }

    public function social(): SocialStrategy
    {
        return $this->container->make(SocialStrategy::class);
    }

    public function magicCode(): MagicCodeStrategy
    {
        return $this->container->make(MagicCodeStrategy::class);
    }

    /**
     * Override how a Socialite user maps to (or creates) an app user. The
     * closure receives ($provider, \Laravel\Socialite\Contracts\User, $container)
     * and must return the app user, or null to deny.
     */
    public function resolveSocialUserUsing(Closure $cb): void
    {
        $this->socialResolver = $cb;
    }

    public function socialResolver(): ?Closure
    {
        return $this->socialResolver;
    }

    public function gateUsing(Closure $cb): void
    {
        $this->gate = $cb;
    }

    /**
     * Customize where server-driven logins (social callback, published embed
     * controllers) land when Laravel has no intended URL to return to. The
     * closure receives ($user, $request) and must return a URL string; it is
     * used as the fallback for redirect()->intended(), so a middleware-set
     * intended URL still wins when present.
     */
    public function redirectUsing(Closure $cb): void
    {
        $this->redirectResolver = $cb;
    }

    public function resolveRedirect(mixed $user, Request $request): string
    {
        $default = config('passwordless.redirect', '/');

        if ($this->redirectResolver === null) {
            return $default;
        }

        $result = ($this->redirectResolver)($user, $request);

        // Fail safe: null / non-string / empty coerces back to the config
        // default, the same spirit as runGate() coercing invalid returns.
        return is_string($result) && $result !== '' ? $result : $default;
    }

    public function recordUsing(Closure $cb): void
    {
        $this->recorder = $cb;
    }

    public function runGate(mixed $user, array $context = []): Decision
    {
        if ($this->gate === null) {
            return self::allow();
        }

        $result = ($this->gate)($user, $context);

        // Fail closed on misuse: a closure that returns false/null/string is a
        // bug, not implicit allow. Coerce anything that isn't a Decision to deny.
        return $result instanceof Decision ? $result : self::deny('gate_returned_invalid');
    }

    public function record(AuthEvent $event): void
    {
        if ($this->recorder !== null) {
            ($this->recorder)($event);
        }
    }

    public static function allow(): Decision
    {
        return Decision::allow();
    }

    public static function deny(string $reason): Decision
    {
        return Decision::deny($reason);
    }

    public function fake(): PasswordlessFake
    {
        return $this->fake = new PasswordlessFake($this->container);
    }

    /**
     * Forward unknown method calls (e.g. `Passwordless::assertLinkSent(...)`)
     * to the active fake so the standard `Mail::fake(); Mail::assertSent(...)`
     * facade pattern works for this package too.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($this->fake !== null && method_exists($this->fake, $method)) {
            return $this->fake->{$method}(...$arguments);
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }
}
