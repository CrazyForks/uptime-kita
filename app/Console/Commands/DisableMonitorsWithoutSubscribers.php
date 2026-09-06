<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use Illuminate\Console\Command;

class DisableMonitorsWithoutSubscribers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:disable-without-subscribers {--dry-run : Show how many monitors would be disabled without updating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically disable uptime checks for monitors that have zero subscribers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Monitor::withoutGlobalScopes()
            ->where('uptime_check_enabled', true)
            ->doesntHave('users');

        $count = $query->count();

        if ($count === 0) {
            $this->info('No enabled monitors without subscribers found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN: {$count} monitor(s) without subscribers would be disabled.");

            return self::SUCCESS;
        }

        $updated = $query->update(['uptime_check_enabled' => false]);

        cache()->forget('public_monitors_status_counts');

        $this->info("Successfully disabled {$updated} monitor(s) without subscribers.");

        return self::SUCCESS;
    }
}
