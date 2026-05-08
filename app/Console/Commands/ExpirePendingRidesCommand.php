<?php

namespace App\Console\Commands;

use App\Jobs\ExpirePendingRidesJob;
use Illuminate\Console\Command;

class ExpirePendingRidesCommand extends Command
{
    protected $signature = 'rides:expire-pending';

    protected $description = 'Expire requested rides that crossed expires_at';

    public function handle(): int
    {
        ExpirePendingRidesJob::dispatchSync();
        $this->info('Expired pending rides check complete.');

        return self::SUCCESS;
    }
}
