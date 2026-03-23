<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

/**
 * Customer-facing seat update using Cashier's payment flow.
 * For admin seat adjustments, use AdjustSubscriptionSeats instead.
 */
class UpdateSeatQuantity
{
    public function __construct(
        private StripeClient $stripe,
    ) {}

    public function execute(Subscription $subscription, int $delta): ActionResult
    {
        $newQuantity = $subscription->quantity + $delta;

        if ($newQuantity < 1) {
            return ActionResult::fail('Subscription must have at least 1 seat.');
        }

        if ($subscription->billable_type !== 'organization') {
            return ActionResult::fail('Seat updates are only available for organization subscriptions.');
        }

        try {
            if ($delta > 0) {
                $subscription->errorIfPaymentFails()
                    ->alwaysInvoice()
                    ->updateQuantity($newQuantity);

                if (! empty($subscription->stripe_id)) {
                    $stripeSubscription = $this->stripe->subscriptions->retrieve(
                        $subscription->stripe_id,
                        ['expand' => ['latest_invoice']],
                    );

                    $invoice = $stripeSubscription->latest_invoice;
                    if ($invoice && ! empty($invoice->hosted_invoice_url) && ! ($invoice->paid ?? true)) {
                        return ActionResult::pendingPayment(
                            'Seat upgrade is pending payment. Complete the invoice to apply the new seats.',
                            $invoice->hosted_invoice_url,
                        );
                    }
                }
            } else {
                $subscription->noProrate()->updateQuantity($newQuantity);
            }
        } catch (CardException) {
            return ActionResult::fail('Unable to charge the saved payment method for the additional seats.');
        } catch (ApiErrorException $e) {
            return ActionResult::fail('Failed to update subscription: '.$e->getMessage());
        }

        return ActionResult::ok('Seats updated to '.$newQuantity.'.');
    }
}
