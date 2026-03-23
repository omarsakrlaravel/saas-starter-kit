<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ApplyCouponToSubscription
{
    public function execute(Subscription $subscription, string $couponCode): ActionResult
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $couponId = $couponCode;

            $promotionCodes = $stripe->promotionCodes->all([
                'code' => $couponCode,
                'active' => true,
                'limit' => 1,
            ]);

            if (! empty($promotionCodes->data)) {
                $couponId = $promotionCodes->data[0]->coupon->id;
            }

            $stripe->subscriptions->update($subscription->stripe_id, [
                'coupon' => $couponId,
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Coupon "'.$couponCode.'" applied.');
    }
}
