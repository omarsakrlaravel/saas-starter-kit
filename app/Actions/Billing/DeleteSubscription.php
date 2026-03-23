<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class DeleteSubscription
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Subscription $subscription): ActionResult
    {
        if ($subscription->stripe_id && in_array($subscription->stripe_status, ['active', 'trialing', 'past_due'])) {
            try {
                $this->stripe->subscriptions->cancel($subscription->stripe_id);
            } catch (ApiErrorException $e) {
                return ActionResult::fail('Stripe error: '.$e->getMessage());
            }
        }

        $subscription->delete();

        return ActionResult::ok('Subscription deleted.');
    }
}
