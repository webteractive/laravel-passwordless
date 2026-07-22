<?php

namespace Webteractive\Passwordless\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            PasswordlessServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('passwordless.user_model', User::class);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        foreach (glob(__DIR__.'/../database/migrations/*.php.stub') as $stub) {
            (require $stub)->up();
        }
    }
}
