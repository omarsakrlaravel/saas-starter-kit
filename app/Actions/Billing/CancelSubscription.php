<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class CancelSubscription
{
    public function execute(Subscription $subscription): ActionResult
    {
        if ($subscription->stripe_id) {
            try {
                $stripe = new StripeClient(config('services.stripe.secret'));
                $stripe->subscriptions->cancel($subscription->stripe_id);
            } catch (ApiErrorException $e) {
                return ActionResult::fail('Stripe error: '.$e->getMessage());
            }
        }

        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        return ActionResult::ok('Subscription canceled.');
    }
}
