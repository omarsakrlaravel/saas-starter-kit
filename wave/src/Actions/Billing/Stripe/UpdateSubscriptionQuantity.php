<?php

namespace Wave\Actions\Billing\Stripe;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Wave\Subscription;

class UpdateSubscriptionQuantity
{
    /**
     * Update the seat quantity on a subscription.
     *
     * @param  int  $delta  Positive to add seats, negative to remove
     *
     * @throws RuntimeException
     */
    public function __invoke(Subscription $subscription, int $delta): ?string
    {
        $newQuantity = $subscription->quantity + $delta;
        $invoicePaymentUrl = null;

        if ($newQuantity < 1) {
            throw new RuntimeException('Subscription must have at least 1 seat.');
        }

        if ($subscription->billable_type !== 'organization') {
            throw new RuntimeException('Seat updates are only available for organization subscriptions.');
        }

        try {
            if ($delta > 0) {
                // For increases: invoice now, and fail fast if payment cannot be completed.
                $subscription->errorIfPaymentFails()
                    ->alwaysInvoice()
                    ->updateQuantity($newQuantity);

                // Check if there's an unpaid invoice requiring action
                if (! empty($subscription->stripe_id)) {
                    $stripe = new StripeClient(config('services.stripe.secret'));
                    $stripeSubscription = $stripe->subscriptions->retrieve($subscription->stripe_id, ['expand' => ['latest_invoice']]);

                    $invoice = $stripeSubscription->latest_invoice;
                    if ($invoice && ! empty($invoice->hosted_invoice_url) && ! ($invoice->paid ?? true)) {
                        $invoicePaymentUrl = $invoice->hosted_invoice_url;
                    }
                }
            } else {
                // For decreases: no proration
                $subscription->noProrate()->updateQuantity($newQuantity);
            }
        } catch (CardException $e) {
            throw new RuntimeException('Unable to charge the saved payment method for the additional seats.', 0, $e);
        } catch (ApiErrorException $e) {
            throw new RuntimeException('Failed to update Stripe subscription: '.$e->getMessage(), 0, $e);
        }

        return $invoicePaymentUrl;
    }
}
