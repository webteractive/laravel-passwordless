<?php

namespace Webteractive\Passwordless\Strategies\Social;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Events\AuthenticationDenied;
use Webteractive\Passwordless\Events\SocialAuthenticated;
use Webteractive\Passwordless\Events\UserAuthenticated;
use Webteractive\Passwordless\Models\SocialAccount;
use Webteractive\Passwordless\Passwordless;
use Webteractive\Passwordless\Support\DomainNotAllowedException;
use Webteractive\Passwordless\Support\DomainPolicy;

class DefaultSocialStrategy implements SocialStrategy
{
    public function __construct(protected Container $container) {}

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);

        $config = $this->providerConfig($provider);
        $driver = Socialite::driver($provider);

        // scopes()/with() live on the concrete OAuth2 provider, not the contract
        // (OAuth1 drivers have neither) — apply them only when supported.
        if (! empty($config['scopes']) && method_exists($driver, 'scopes')) {
            $driver->scopes($config['scopes']);
        }

        if (! empty($config['with']) && method_exists($driver, 'with')) {
            $driver->with($config['with']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider, Request $request): mixed
    {
        $this->ensureEnabled($provider);

        $oauth = Socialite::driver($provider)->user();

        try {
            [$user, $registered, $linked] = $this->resolve($provider, $oauth);
        } catch (DomainNotAllowedException) {
            event(new AuthenticationDenied('social', null, 'domain_not_allowed'));
            throw new SocialGateDeniedException('domain_not_allowed');
        }

        /** @var Passwordless $manager */
        $manager = $this->container->make(Passwordless::class);
        $decision = $manager->runGate($user, ['strategy' => 'social', 'provider' => $provider]);

        if (! $decision->allowed) {
            event(new AuthenticationDenied('social', $user, $decision->reason ?? 'denied'));
            throw new SocialGateDeniedException($decision->reason ?? 'denied');
        }

        $this->storeAccount($provider, $oauth, $user);

        event(new SocialAuthenticated($provider, $user, $registered, $linked));
        event(new UserAuthenticated('social', $user));

        return $user;
    }

    /**
     * @return array{0: mixed, 1: bool, 2: bool} [user, registered, linked]
     */
    protected function resolve(string $provider, SocialiteUser $oauth): array
    {
        /** @var Passwordless $manager */
        $manager = $this->container->make(Passwordless::class);

        if ($resolver = $manager->socialResolver()) {
            $user = $resolver($provider, $oauth, $this->container);

            if (! $user) {
                throw new SocialGateDeniedException('denied');
            }

            return [$user, false, false];
        }

        // 1. Known identity — the durable (provider, provider_id) match. Already
        // proven, so no email-verification requirement applies here.
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $oauth->getId())
            ->first();

        if ($account && $account->user) {
            return [$account->user, false, false];
        }

        $model = config('passwordless.user_model');
        $column = config('passwordless.user_email_column', 'email');
        $email = $oauth->getEmail();

        if (! $email) {
            throw new SocialGateDeniedException('no_email');
        }

        // Both linking to an existing account AND auto-registering require a
        // provably verified email — otherwise an attacker who controls an
        // unverified address at some provider could take over an account.
        if (! $this->emailIsVerified($provider, $oauth)) {
            throw new SocialGateDeniedException('email_not_verified');
        }

        // 2. Link the verified email to an existing user.
        if ($user = $model::query()->where($column, $email)->first()) {
            DomainPolicy::check('social', 'login', $email);

            return [$user, false, true];
        }

        // 3. Auto-register a new user.
        if (config('passwordless.social.auto_register', true)) {
            DomainPolicy::check('social', 'register', $email);

            $user = $model::query()->create([
                $column => $email,
                'name' => $oauth->getName() ?? $oauth->getNickname() ?? $email,
                'password' => Hash::make(Str::random(40)),
            ]);

            return [$user, true, false];
        }

        throw new SocialGateDeniedException('registration_disabled');
    }

    protected function storeAccount(string $provider, SocialiteUser $oauth, mixed $user): void
    {
        SocialAccount::query()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $oauth->getId()],
            [
                'user_id' => $user->getKey(),
                'email' => $oauth->getEmail(),
                'name' => $oauth->getName(),
                'nickname' => $oauth->getNickname(),
                'avatar' => $oauth->getAvatar(),
                'token' => $oauth->token ?? null,
                'refresh_token' => $oauth->refreshToken ?? null,
                'expires_at' => isset($oauth->expiresIn) && $oauth->expiresIn
                    ? now()->addSeconds($oauth->expiresIn)
                    : null,
            ]
        );
    }

    /**
     * An email is verified when the provider explicitly says so, or when the
     * provider is on the trusted allow-list. An explicit `false` always wins.
     */
    protected function emailIsVerified(string $provider, SocialiteUser $oauth): bool
    {
        $raw = method_exists($oauth, 'getRaw') ? $oauth->getRaw() : [];
        $flag = data_get($raw, 'email_verified');

        if ($flag !== null) {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        return in_array($provider, (array) config('passwordless.social.trusted_providers', []), true);
    }

    protected function ensureEnabled(string $provider): void
    {
        if (! $this->isEnabled($provider)) {
            throw new SocialProviderNotEnabledException($provider);
        }
    }

    protected function isEnabled(string $provider): bool
    {
        foreach ((array) config('passwordless.social.providers', []) as $key => $value) {
            $name = is_int($key) ? $value : $key;

            if ($name === $provider) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function providerConfig(string $provider): array
    {
        foreach ((array) config('passwordless.social.providers', []) as $key => $value) {
            if (is_int($key) && $value === $provider) {
                return [];
            }

            if ($key === $provider) {
                return is_array($value) ? $value : [];
            }
        }

        return [];
    }
}
