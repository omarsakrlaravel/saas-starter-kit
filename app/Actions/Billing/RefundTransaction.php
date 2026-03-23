<?php

namespace App\Actions\Billing;

use App\Models\Transaction;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class RefundTransaction
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    /**
     * Refund a transaction. Pass null for amountInCents to refund the full amount.
     */
    public function execute(Transaction $transaction, ?int $amountInCents = null): ActionResult
    {
        $isFullRefund = $amountInCents === null;
        $refundParams = str_starts_with($transaction->stripe_id, 'pi_')
            ? ['payment_intent' => $transaction->stripe_id]
            : ['charge' => $transaction->stripe_id];

        if (! $isFullRefund) {
            $refundParams['amount'] = $amountInCents;
        }

        try {
            $this->stripe->refunds->create($refundParams);
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Stripe error: '.$e->getMessage());
        }

        if ($isFullRefund) {
            $transaction->update([
                'status' => 'refunded',
                'refunded_amount' => $transaction->amount,
            ]);

            $formatted = currencySymbol($transaction->currency).number_format($transaction->amount / 100, 2);

            return ActionResult::ok($formatted.' refunded.');
        }

        $newRefundedAmount = $transaction->refunded_amount + $amountInCents;
        $transaction->update([
            'status' => $newRefundedAmount >= $transaction->amount ? 'refunded' : 'partially_refunded',
            'refunded_amount' => $newRefundedAmount,
        ]);

        $formatted = currencySymbol($transaction->currency).number_format($amountInCents / 100, 2);

        return ActionResult::ok($formatted.' refunded.');
    }
}
