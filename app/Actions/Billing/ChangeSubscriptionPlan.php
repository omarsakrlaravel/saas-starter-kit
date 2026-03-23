<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanChangeResolver;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ChangeSubscriptionPlan
{
    public function __construct(
        private PlanChangeResolver $resolver,
        private StripeClient $stripe,
    ) {}

    public function execute(Subscription $subscription, int $planId, string $cycle): ActionResult
    {
        $plan = Plan::find($planId);

        if (! $plan) {
            return ActionResult::fail('Selected plan not found.');
        }

        $priceId = $cycle === 'year' ? $plan->yearly_price_id : $plan->monthly_price_id;

        if (empty($priceId)) {
            return ActionResult::fail('No price configured for this billing cycle.');
        }

        $changeType = $this->resolver->resolve($subscription, $plan, $cycle);

        if ($changeType === 'same') {
            return ActionResult::fail('Already on this plan and cycle.');
        }

        if ($changeType === 'upgrade') {
            return $this->applyUpgrade($subscription, $plan, $priceId, $cycle);
        }

        return $this->scheduleDowngrade($subscription, $plan, $cycle);
    }

    private function applyUpgrade(Subscription $subscription, Plan $plan, string $priceId, string $cycle): ActionResult
    {
        try {
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_id);
            $this->stripe->subscriptions->update($subscription->stripe_id, [
                'items' => [
                    ['id' => $stripeSubscription->items->data[0]->id, 'price' => $priceId],
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        $subscription->update([
            'plan_id' => $plan->id,
            'stripe_price' => $priceId,
            'cycle' => $cycle,
            'pending_plan_id' => null,
            'pending_cycle' => null,
            'pending_change_scheduled_at' => null,
        ]);

        return ActionResult::ok('Upgraded to '.$plan->name.' ('.$cycle.'ly).');
    }

    private function scheduleDowngrade(Subscription $subscription, Plan $plan, string $cycle): ActionResult
    {
        $scheduledAt = $subscription->next_payment_at ?? $subscription->ends_at ?? now()->addMonth();

        $subscription->update([
            'pending_plan_id' => $plan->id,
            'pending_cycle' => $cycle,
            'pending_change_scheduled_at' => $scheduledAt,
        ]);

        $date = $scheduledAt instanceof \Carbon\Carbon ? $scheduledAt->format('M j, Y') : $scheduledAt;

        return ActionResult::ok('Downgrade to '.$plan->name.' scheduled for '.$date.'.');
    }
}
