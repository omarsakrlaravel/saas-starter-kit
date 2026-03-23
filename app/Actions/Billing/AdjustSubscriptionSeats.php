<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class AdjustSubscriptionSeats
{
    public function execute(Subscription $subscription, int $newQuantity): ActionResult
    {
        if ($newQuantity < 1) {
            return ActionResult::fail('Quantity must be at least 1.');
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripeSubscription = $stripe->subscriptions->retrieve($subscription->stripe_id);
            $stripe->subscriptions->update($subscription->stripe_id, [
                'items' => [
                    ['id' => $stripeSubscription->items->data[0]->id, 'quantity' => $newQuantity],
                ],
                'proration_behavior' => $newQuantity > $subscription->quantity
                    ? 'create_prorations'
                    : 'none',
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        $subscription->update(['quantity' => $newQuantity]);

        return ActionResult::ok('Seats updated to '.$newQuantity.'.');
    }
}
