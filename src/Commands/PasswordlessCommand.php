<?php

namespace Webteractive\Passwordless\Commands;

use Illuminate\Console\Command;

class PasswordlessCommand extends Command
{
    public $signature = 'laravel-passwordless';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
