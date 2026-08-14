<?php

namespace Webteractive\Passwordless\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Routing\RouteCollection;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Webteractive\Passwordless\PasswordlessServiceProvider;
use Workbench\App\Models\User;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Webteractive\\Passwordless\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            // Fortify is a dev-only dependency. Booting it here lets the suite
            // exercise the 2FA-present paths; the package itself never requires it.
            FortifyServiceProvider::class,
            PasswordlessServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('passwordless.user_model', User::class);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('cache.default', 'array');

        // Mirror the starter kits' Fortify setup so the two-factor challenge
        // routes exist and confirm-password gating behaves as it does there.
        config()->set('fortify.guard', 'web');
        config()->set('fortify.features', [
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
        ]);
    }

    /**
     * Re-register the package's route file against the CURRENT config.
     *
     * Routes are registered once at boot, but the dev-login guard is evaluated
     * at registration time by design — so a test that changes its config has to
     * re-run the file to observe the effect. The package's own routes are
     * dropped first so re-registration does not leave duplicates behind.
     */
    public function reloadPasswordlessRoutes(): void
    {
        $router = $this->app['router'];

        $kept = new RouteCollection;

        foreach ($router->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'passwordless.')) {
                $kept->add($route);
            }
        }

        $router->setRoutes($kept);

        require __DIR__.'/../routes/web.php';

        $router->getRoutes()->refreshNameLookups();
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') as $stub) {
            (require $stub)->up();
        }
    }
}
