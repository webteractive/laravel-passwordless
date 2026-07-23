<?php

namespace Webteractive\Passwordless\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Webteractive\Passwordless\Contracts\MagicLinkStrategy magicLink()
 * @method static \Webteractive\Passwordless\Contracts\LoginCodeStrategy loginCode()
 * @method static \Webteractive\Passwordless\Contracts\SocialStrategy social()
 * @method static void gateUsing(\Closure $cb)
 * @method static void resolveSocialUserUsing(\Closure $cb)
 * @method static void recordUsing(\Closure $cb)
 * @method static \Webteractive\Passwordless\Support\Decision runGate(mixed $user, array $context = [])
 * @method static void record(\Webteractive\Passwordless\Support\AuthEvent $event)
 * @method static \Webteractive\Passwordless\Support\Decision allow()
 * @method static \Webteractive\Passwordless\Support\Decision deny(string $reason)
 * @method static \Webteractive\Passwordless\Testing\PasswordlessFake fake()
 * @method static void assertLinkSent(string $email)
 * @method static void assertCodeSent(string $email)
 * @method static void assertNothingSent()
 * @method static \Webteractive\Passwordless\Testing\PasswordlessFake respondWith(mixed $user)
 *
 * @see \Webteractive\Passwordless\Passwordless
 */
class Passwordless extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Webteractive\Passwordless\Passwordless::class;
    }
}
