<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RefundInvoice
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Invoice $invoice, int $amountInCents): ActionResult
    {
        if ($amountInCents > $invoice->amount_paid) {
            return ActionResult::fail('Refund amount exceeds amount paid.');
        }

        try {
            $stripeInvoice = $this->stripe->invoices->retrieve($invoice->stripe_id);
            $this->stripe->refunds->create([
                'charge' => $stripeInvoice->charge,
                'amount' => $amountInCents,
            ]);

            $newAmountPaid = $invoice->amount_paid - $amountInCents;
            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'amount_remaining' => $invoice->total - $newAmountPaid,
            ]);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        return ActionResult::ok('Refund processed successfully.');
    }
}
