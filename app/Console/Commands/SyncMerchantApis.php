<?php
namespace App\Console\Commands;

use App\Jobs\SyncMerchantApiJob;
use App\Models\MerchantApi;
use Illuminate\Console\Command;

class SyncMerchantApis extends Command
{
    protected $signature = 'merchant:sync-apis
                            {--merchant= : Sync only specific merchant ID}
                            {--api= : Sync only specific API ID}
                            {--all : Sync all active APIs}';

    protected $description = 'Sync all merchant APIs';

    public function handle(): int
    {
        $query = MerchantApi::with('merchant')
            ->where('is_active', true);

        if ($this->option('merchant')) {
            $query->where('merchant_id', $this->option('merchant'));
        }

        if ($this->option('api')) {
            $query->where('id', $this->option('api'));
        }

        if (!$this->option('all') && !$this->option('merchant') && !$this->option('api')) {
            $this->error('Please specify --all, --merchant, or --api option');
            return 1;
        }

        $apis = $query->get();

        if ($apis->isEmpty()) {
            $this->warn('No active APIs found to sync');
            return 0;
        }

        $this->info("Dispatching {$apis->count()} sync jobs...");

        foreach ($apis as $api) {
            SyncMerchantApiJob::dispatch($api);
            $this->line("Dispatched: {$api->merchant->name} - {$api->name}");
        }

        $this->info('All sync jobs have been dispatched!');

        return 0;
    }
}
