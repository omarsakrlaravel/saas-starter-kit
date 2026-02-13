<?php

namespace Wave\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Wave\Actions\Billing\Stripe\UpdateSubscriptionQuantity;
use Wave\Subscription;

class AdjustSubscriptionSeats extends Command
{
    protected $signature = 'subscriptions:adjust-seats';

    protected $description = 'Reduce empty seats on organization subscriptions so the next billing cycle reflects actual usage';

    public function handle(UpdateSubscriptionQuantity $updateQuantity): int
    {
        $subscriptions = Subscription::where('status', 'active')
            ->where('billable_type', 'organization')
            ->get();

        $adjusted = 0;

        foreach ($subscriptions as $subscription) {
            $organization = Organization::find($subscription->billable_id);
            if (! $organization) {
                continue;
            }

            $occupied = $organization->occupiedSeatCount();
            $targetSeats = max($occupied, 1);

            if ($subscription->seats <= $targetSeats) {
                continue;
            }

            $oldSeats = $subscription->seats;
            $delta = $targetSeats - $subscription->seats;

            try {
                $updateQuantity($subscription, $delta, 'none');
                $this->info("Adjusted {$organization->name}: {$oldSeats} → {$targetSeats} seats");
                $adjusted++;
            } catch (\RuntimeException $e) {
                $this->error("Failed to adjust {$organization->name}: {$e->getMessage()}");
            }
        }

        $this->info("Adjusted {$adjusted} subscription(s).");

        return self::SUCCESS;
    }
}
