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
     * @param  string|null  $prorationBehavior  Stripe proration behavior. When null: increases use 'always_invoice', decreases use 'none'
     *
     * @throws RuntimeException
     */
    public function __invoke(Subscription $subscription, int $delta, ?string $prorationBehavior = null): ?string
    {
        $newQuantity = $subscription->seats + $delta;
        $invoicePaymentUrl = null;

        if ($newQuantity < 1) {
            throw new RuntimeException('Subscription must have at least 1 seat.');
        }

        if ($subscription->billable_type !== 'organization') {
            throw new RuntimeException('Seat updates are only available for organization subscriptions.');
        }

        if (is_null($prorationBehavior)) {
            $prorationBehavior = $delta > 0 ? 'always_invoice' : 'none';
        }

        if ($subscription->vendor_slug === 'stripe' && ! empty($subscription->vendor_subscription_id)) {
            try {
                $stripe = new StripeClient(config('wave.stripe.secret_key'));

                $stripeSubscription = $stripe->subscriptions->retrieve($subscription->vendor_subscription_id);
                $itemId = $stripeSubscription->items->data[0]->id;

                $payload = [
                    'items' => [
                        [
                            'id' => $itemId,
                            'quantity' => $newQuantity,
                        ],
                    ],
                    'proration_behavior' => $prorationBehavior,
                ];

                // For seat increases, request payment but keep the invoice open if collection is pending.
                if ($delta > 0 && $prorationBehavior === 'always_invoice') {
                    $payload['payment_behavior'] = 'pending_if_incomplete';
                    $payload['expand'] = ['latest_invoice'];
                }

                $updatedSubscription = $stripe->subscriptions->update($subscription->vendor_subscription_id, $payload);

                if ($delta > 0 && ! empty($updatedSubscription->latest_invoice)) {
                    $invoice = is_string($updatedSubscription->latest_invoice)
                        ? $stripe->invoices->retrieve($updatedSubscription->latest_invoice)
                        : $updatedSubscription->latest_invoice;

                    if ($invoice && ! empty($invoice->hosted_invoice_url) && ! ($invoice->paid ?? true)) {
                        $invoicePaymentUrl = $invoice->hosted_invoice_url;
                    }
                }
            } catch (CardException $e) {
                throw new RuntimeException('Unable to charge the saved payment method for the additional seats.', 0, $e);
            } catch (ApiErrorException $e) {
                throw new RuntimeException('Failed to update Stripe subscription: '.$e->getMessage(), 0, $e);
            }
        }

        $subscription->seats = $newQuantity;
        $subscription->save();

        return $invoicePaymentUrl;
    }
}
