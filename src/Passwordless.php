<?php

namespace Webteractive\Passwordless;

use Closure;
use Illuminate\Contracts\Container\Container;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Support\AuthEvent;
use Webteractive\Passwordless\Support\Decision;
use Webteractive\Passwordless\Testing\PasswordlessFake;

class Passwordless
{
    protected ?Closure $gate = null;

    protected ?Closure $recorder = null;

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

    public function gateUsing(Closure $cb): void
    {
        $this->gate = $cb;
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
