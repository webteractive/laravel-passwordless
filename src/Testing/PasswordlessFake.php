<?php

namespace Webteractive\Passwordless\Testing;

use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert as PHPUnit;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;

class PasswordlessFake
{
    public array $linksSent = [];

    public array $codesSent = [];

    /**
     * The user instance the fake strategies should return from verify/consume.
     * Tests set this with `respondWith($user)` to simulate a successful login.
     */
    public mixed $verifyResponse = null;

    public function __construct(protected Container $container)
    {
        $this->container->instance(MagicLinkStrategy::class, new FakeMagicLinkStrategy($this));
        $this->container->instance(LoginCodeStrategy::class, new FakeLoginCodeStrategy($this));
    }

    public function respondWith(mixed $user): self
    {
        $this->verifyResponse = $user;

        return $this;
    }

    public function recordLink(string $email, array $context = []): void
    {
        $this->linksSent[] = compact('email', 'context');
    }

    public function recordCode(string $email, array $context = []): void
    {
        $this->codesSent[] = compact('email', 'context');
    }

    public function assertLinkSent(string $email): void
    {
        PHPUnit::assertNotEmpty(
            array_filter($this->linksSent, fn ($l) => $l['email'] === $email),
            "No magic link was sent to [{$email}]."
        );
    }

    public function assertCodeSent(string $email): void
    {
        PHPUnit::assertNotEmpty(
            array_filter($this->codesSent, fn ($l) => $l['email'] === $email),
            "No login code was sent to [{$email}]."
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->linksSent, 'Expected no magic links sent.');
        PHPUnit::assertEmpty($this->codesSent, 'Expected no login codes sent.');
    }
}
