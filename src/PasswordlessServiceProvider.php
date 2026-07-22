<?php

namespace Webteractive\Passwordless;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webteractive\Passwordless\Channels\MailLoginCodeChannel;
use Webteractive\Passwordless\Commands\PruneCommand;
use Webteractive\Passwordless\Contracts\LoginCodeStrategy;
use Webteractive\Passwordless\Contracts\MagicLinkStrategy;
use Webteractive\Passwordless\Strategies\LoginCode\DefaultLoginCodeStrategy;
use Webteractive\Passwordless\Strategies\MagicLink\DefaultMagicLinkStrategy;

class PasswordlessServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('passwordless')
            ->hasConfigFile()
            ->hasMigration('create_passwordless_challenges_table')
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

            $this->publishes([
                "{$uiStubs}/vue/Login.vue" => resource_path('js/pages/passwordless/Login.vue'),
                "{$uiStubs}/vue/routes.php" => base_path('routes/passwordless-ui.php'),
            ], 'passwordless-ui-vue');
        }
    }
}
