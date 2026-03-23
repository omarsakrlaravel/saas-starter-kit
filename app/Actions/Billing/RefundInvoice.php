<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RefundInvoice
{
    public function execute(Invoice $invoice, int $amountInCents): ActionResult
    {
        if ($amountInCents > $invoice->amount_paid) {
            return ActionResult::fail('Refund amount exceeds amount paid.');
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripeInvoice = $stripe->invoices->retrieve($invoice->stripe_id);
            $stripe->refunds->create([
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
