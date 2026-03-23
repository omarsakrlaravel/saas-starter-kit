<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RefundLastPayment
{
    public function execute(Subscription $subscription): ActionResult
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $invoices = $stripe->invoices->all([
                'subscription' => $subscription->stripe_id,
                'status' => 'paid',
                'limit' => 1,
            ]);

            if (empty($invoices->data)) {
                return ActionResult::fail('No paid invoices found.');
            }

            $latestInvoice = $invoices->data[0];

            if (empty($latestInvoice->payment_intent)) {
                return ActionResult::fail('No payment intent found on invoice.');
            }

            $stripe->refunds->create([
                'payment_intent' => $latestInvoice->payment_intent,
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        $currency = strtolower((string) ($latestInvoice->currency ?? 'usd'));
        $amount = number_format($latestInvoice->amount_paid / 100, 2);

        return ActionResult::ok('Refund of '.currencySymbol($currency).$amount.' issued.');
    }
}
