<?php

namespace App\Console\Commands;

use App\Services\Attempts\AttemptExpirer;
use Illuminate\Console\Command;

class ExpireAttempts extends Command
{
    protected $signature = 'expire:attempts {--limit=100}';

    protected $description = 'Auto-submit in-progress attempts past their deadline';

    public function handle(AttemptExpirer $expirer): int
    {
        $count = $expirer->expireDue((int) $this->option('limit'));
        $this->info("Expired {$count} attempt(s).");

        return self::SUCCESS;
    }
}
