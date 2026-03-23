<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RefundLastPayment
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Subscription $subscription): ActionResult
    {
        try {
            $invoices = $this->stripe->invoices->all([
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

            $this->stripe->refunds->create([
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
