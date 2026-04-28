<?php

namespace Webteractive\Passwordless;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webteractive\Passwordless\Commands\PasswordlessCommand;

class PasswordlessServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-passwordless')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_passwordless_table')
            ->hasCommand(PasswordlessCommand::class);
    }
}
