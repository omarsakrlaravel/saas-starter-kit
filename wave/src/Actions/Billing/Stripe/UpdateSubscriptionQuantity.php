<?php

namespace Wave\Actions\Billing\Stripe;

use RuntimeException;
use Stripe\StripeClient;
use Wave\Subscription;

class UpdateSubscriptionQuantity
{
    /**
     * Update the seat quantity on a subscription.
     *
     * @param  int  $delta  Positive to add seats, negative to remove
     * @param  string  $prorationBehavior  Stripe proration behavior: 'create_prorations' (default) or 'none'
     *
     * @throws RuntimeException
     */
    public function __invoke(Subscription $subscription, int $delta, string $prorationBehavior = 'create_prorations'): void
    {
        $newQuantity = $subscription->seats + $delta;

        if ($newQuantity < 1) {
            throw new RuntimeException('Subscription must have at least 1 seat.');
        }

        if ($subscription->vendor_slug === 'stripe' && ! empty($subscription->vendor_subscription_id)) {
            $stripe = new StripeClient(config('wave.stripe.secret_key'));

            $stripeSubscription = $stripe->subscriptions->retrieve($subscription->vendor_subscription_id);
            $itemId = $stripeSubscription->items->data[0]->id;

            $stripe->subscriptions->update($subscription->vendor_subscription_id, [
                'items' => [
                    [
                        'id' => $itemId,
                        'quantity' => $newQuantity,
                    ],
                ],
                'proration_behavior' => $prorationBehavior,
            ]);
        }

        $subscription->seats = $newQuantity;
        $subscription->save();
    }
}
