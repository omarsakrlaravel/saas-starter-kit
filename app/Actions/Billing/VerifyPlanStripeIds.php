<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class VerifyPlanStripeIds
{
    public function execute(Plan $plan): ActionResult
    {
        $stripe = new StripeClient(config('services.stripe.secret'));
        $issues = [];

        foreach (['monthly_price_id', 'yearly_price_id', 'onetime_price_id'] as $field) {
            $priceId = $plan->{$field};

            if (empty($priceId)) {
                continue;
            }

            try {
                $price = $stripe->prices->retrieve($priceId);

                if (! $price->active) {
                    $issues[] = $field.' ('.$priceId.') exists but is inactive';
                }
            } catch (ApiErrorException) {
                $issues[] = $field.' ('.$priceId.') not found in Stripe';
            }
        }

        if (empty($issues)) {
            return ActionResult::ok('All Stripe price IDs are valid and active.');
        }

        return ActionResult::fail(implode("\n", $issues));
    }
}
