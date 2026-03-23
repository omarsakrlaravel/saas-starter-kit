<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ApplyCouponToSubscription
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Subscription $subscription, string $couponCode): ActionResult
    {
        try {
            $couponId = $couponCode;

            $promotionCodes = $this->stripe->promotionCodes->all([
                'code' => $couponCode,
                'active' => true,
                'limit' => 1,
            ]);

            if (! empty($promotionCodes->data)) {
                $couponId = $promotionCodes->data[0]->coupon->id;
            }

            $this->stripe->subscriptions->update($subscription->stripe_id, [
                'coupon' => $couponId,
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Coupon "'.$couponCode.'" applied.');
    }
}
