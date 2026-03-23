<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class MarkInvoiceUncollectible
{
    public function execute(Invoice $invoice): ActionResult
    {
        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripe->invoices->markUncollectible($invoice->stripe_id);

            $invoice->update(['status' => 'uncollectible']);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Invoice marked as uncollectible.');
    }
}
