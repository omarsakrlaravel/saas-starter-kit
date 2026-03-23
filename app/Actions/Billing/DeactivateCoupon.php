<?php

namespace App\Actions\Billing;

use App\Models\Coupon;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class DeactivateCoupon
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Coupon $coupon): ActionResult
    {
        if ($coupon->stripe_id) {
            try {
                $this->stripe->coupons->update($coupon->stripe_id, [
                    'metadata' => ['deactivated_by_admin' => 'true'],
                ]);
            } catch (ApiErrorException) {
                // Best-effort -- coupon may not exist in Stripe
            }
        }

        $coupon->update(['active' => false]);

        return ActionResult::ok('Coupon deactivated.');
    }
}
