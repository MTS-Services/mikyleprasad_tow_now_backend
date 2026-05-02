<?php

namespace App\Console\Commands;

use App\Jobs\SyncCurrencyRatesJob;
use Illuminate\Console\Command;

class SyncCurrencyRatesCommand extends Command
{
    protected $signature = 'currency:sync-rates';

    protected $description = 'Fetch latest FX rates from Frankfurter (free API) and append rows to currency_rates';

    public function handle(): int
    {
        if (! config('currency.fx_sync.enabled', false)) {
            $this->warn('FX sync is disabled. Set CURRENCY_FX_SYNC_ENABLED=true in .env.');

            return self::SUCCESS;
        }

        $this->info('Fetching currency rates…');
        SyncCurrencyRatesJob::dispatchSync();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
