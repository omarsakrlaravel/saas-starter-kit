<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class VoidInvoice
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Invoice $invoice): ActionResult
    {
        try {
            $this->stripe->invoices->voidInvoice($invoice->stripe_id);

            $invoice->update(['status' => 'void']);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Invoice voided.');
    }
}
