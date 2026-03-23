<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class MarkInvoiceUncollectible
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Invoice $invoice): ActionResult
    {
        try {
            $this->stripe->invoices->markUncollectible($invoice->stripe_id);

            $invoice->update(['status' => 'uncollectible']);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Invoice marked as uncollectible.');
    }
}
