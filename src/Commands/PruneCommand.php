<?php

namespace Webteractive\Passwordless\Commands;

use Illuminate\Console\Command;
use Webteractive\Passwordless\Models\Challenge;

class PruneCommand extends Command
{
    public $signature = 'passwordless:prune';

    public $description = 'Delete consumed or expired passwordless challenges.';

    public function handle(): int
    {
        $deleted = Challenge::query()
            ->where(function ($q) {
                $q->whereNotNull('consumed_at')
                    ->orWhere('expires_at', '<=', now());
            })
            ->delete();

        $this->info("Pruned {$deleted} challenge(s).");

        return self::SUCCESS;
    }
}
