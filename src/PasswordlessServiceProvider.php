<?php

namespace Webteractive\Passwordless;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webteractive\Passwordless\Channels\MailLoginCodeChannel;
use Webteractive\Passwordless\Commands\PruneCommand;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Contracts\SocialStrategy;
use Webteractive\Passwordless\Strategies\LoginCode\DefaultLoginCodeStrategy;
use Webteractive\Passwordless\Strategies\MagicCode\DefaultMagicCodeStrategy;
use Webteractive\Passwordless\Strategies\MagicLink\DefaultMagicLinkStrategy;
use Webteractive\Passwordless\Strategies\Social\DefaultSocialStrategy;

class PasswordlessServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('passwordless')
            ->hasConfigFile()
            ->hasMigrations([
                'create_passwordless_challenges_table',
                'create_passwordless_social_accounts_table',
            ])
            ->hasRoute('web')
            ->hasRoute('api')
            ->hasTranslations()
            ->hasViews()
            ->hasCommand(PruneCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Passwordless::class, fn ($app) => new Passwordless($app));

        $this->app->bind(MagicLinkStrategy::class, DefaultMagicLinkStrategy::class);
        $this->app->bind(LoginCodeStrategy::class, DefaultLoginCodeStrategy::class);
        $this->app->bind(MagicCodeStrategy::class, DefaultMagicCodeStrategy::class);
        $this->app->bind(SocialStrategy::class, DefaultSocialStrategy::class);
        $this->app->bind('passwordless.login_code_channels.mail', MailLoginCodeChannel::class);
    }

    public function packageBooted(): void
    {
        // Opt-in UI kit — publish-only stubs, one tag per starter-kit stack.
        // Nothing here is routed by default; publishing hands the app a login
        // page it owns and wires itself. The headless core is untouched.
        if ($this->app->runningInConsole()) {
            $uiStubs = __DIR__.'/../stubs/ui';

            $this->publishes([
                "{$uiStubs}/livewire/login.blade.php" => resource_path('views/passwordless/login.blade.php'),
                "{$uiStubs}/livewire/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-livewire');

            // Integrated with the Livewire starter kit: Blade + Flux page copying
            // the kit's own auth conventions (<x-layouts::auth>, Fortify-style
            // controller, classic form POST -> redirect).
            $this->publishes([
                "{$uiStubs}/livewire-embed/passwordless.blade.php" => resource_path('views/pages/auth/passwordless.blade.php'),
                "{$uiStubs}/livewire-embed/PasswordlessLoginController.php" => app_path('Http/Controllers/Auth/PasswordlessLoginController.php'),
                "{$uiStubs}/livewire-embed/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-livewire-embed');

            $this->publishes([
                "{$uiStubs}/react/Login.tsx" => resource_path('js/pages/passwordless/login.tsx'),
                "{$uiStubs}/react/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-react');

            // Integrated with the React starter kit: Inertia page under pages/auth/*
            // (auto-wrapped in the kit's AuthLayout) + Fortify-style controller.
            $this->publishes([
                "{$uiStubs}/react-embed/passwordless.tsx" => resource_path('js/pages/auth/passwordless.tsx'),
                "{$uiStubs}/react-embed/PasswordlessLoginController.php" => app_path('Http/Controllers/Auth/PasswordlessLoginController.php'),
                "{$uiStubs}/react-embed/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-react-embed');

            $this->publishes([
                "{$uiStubs}/vue/Login.vue" => resource_path('js/pages/passwordless/Login.vue'),
                "{$uiStubs}/vue/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-vue');

            // Integrated with the Vue starter kit: Inertia page under pages/auth/*
            // (auto-wrapped in the kit's AuthLayout) + Fortify-style controller.
            $this->publishes([
                "{$uiStubs}/vue-embed/Passwordless.vue" => resource_path('js/pages/auth/Passwordless.vue'),
                "{$uiStubs}/vue-embed/PasswordlessLoginController.php" => app_path('Http/Controllers/Auth/PasswordlessLoginController.php'),
                "{$uiStubs}/vue-embed/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-vue-embed');
        }
    }
}
