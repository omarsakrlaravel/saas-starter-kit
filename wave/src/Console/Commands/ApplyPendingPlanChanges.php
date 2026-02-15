<?php

namespace Wave\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Wave\Subscription;

class ApplyPendingPlanChanges extends Command
{
    protected $signature = 'wave:apply-pending-plan-changes';

    protected $description = 'Apply scheduled plan downgrades when the billing period has ended';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->whereNotNull('pending_plan_id')
            ->where(function ($query) {
                $query->where('pending_change_scheduled_at', '<=', now())
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNull('pending_change_scheduled_at')
                            ->where('next_payment_at', '<=', now());
                    });
            })
            ->where('stripe_status', 'active')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No pending plan changes to apply.');

            return self::SUCCESS;
        }

        $applied = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $result = $subscription->applyPendingChange();

            if ($result) {
                $applied++;
                $this->info("Applied pending change for subscription #{$subscription->id}");
            } else {
                $failed++;
                $this->warn("Failed to apply pending change for subscription #{$subscription->id}");
            }
        }

        $this->info("Done. Applied: {$applied}, Failed: {$failed}");
        Log::info('wave:apply-pending-plan-changes completed', [
            'applied' => $applied,
            'failed' => $failed,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
