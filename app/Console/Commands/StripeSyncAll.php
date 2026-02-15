<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StripeSyncAll extends Command
{
    protected $signature = 'stripe:sync-all';

    protected $description = 'Backfill all Stripe data (coupons, invoices) into the local database';

    public function handle(): int
    {
        $this->info('Starting full Stripe data sync...');
        $this->newLine();

        $commands = [
            'stripe:sync-coupons',
            'stripe:sync-invoices',
        ];

        $failed = false;

        foreach ($commands as $command) {
            $this->info("Running: {$command}");
            $this->line(str_repeat('-', 50));

            $exitCode = $this->call($command);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Command {$command} failed.");
                $failed = true;
            }

            $this->newLine();
        }

        if ($failed) {
            $this->error('Stripe sync completed with errors. Review the output above.');

            return self::FAILURE;
        }

        $this->info('All Stripe data synced successfully.');

        return self::SUCCESS;
    }
}
