<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\MerchantApi;
use App\Jobs\SyncMerchantApiJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync all merchant APIs based on their configured intervals
        $schedule->call(function () {
            $apis = MerchantApi::with('merchant')
                ->where('is_active', true)
                ->get();

            foreach ($apis as $api) {
                $shouldSync = !$api->last_synced_at ||
                    $api->last_synced_at->addMinutes($api->sync_interval_minutes)->isPast();

                if ($shouldSync) {
                    SyncMerchantApiJob::dispatch($api);

                    \Illuminate\Support\Facades\Log::info('Dispatched scheduled sync job', [
                        'merchant' => $api->merchant->name,
                        'api' => $api->name,
                        'api_id' => $api->id,
                        'interval' => $api->sync_interval_minutes
                    ]);
                }
            }
        })->everyMinute()->name('sync-merchant-apis')->withoutOverlapping();

        // Clean up old logs (example)
        $schedule->command('log:clear --keep-last=30')->daily();

        // Backup database (example)
        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run')->daily()->at('01:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
