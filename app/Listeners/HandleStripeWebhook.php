<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Wave\Coupon;
use Wave\CouponRedemption;
use Wave\Invoice;
use Wave\PaymentMethod;
use Wave\Plan;
use Wave\PromotionCode;
use Wave\Subscription;
use Wave\Transaction;

class HandleStripeWebhook
{
    /**
     * Handle Stripe webhooks dispatched by Cashier.
     *
     * Cashier's WebhookController handles standard fields (stripe_status,
     * stripe_price, quantity, trial_ends_at, ends_at). This listener only
     * sets custom Wave fields: billable_type, billable_id, plan_id, cycle.
     */
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->handleCheckoutSessionCompleted($event->payload),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->payload),

            // Invoice events
            'invoice.created',
            'invoice.updated' => $this->handleInvoiceUpsert($event->payload),
            'invoice.paid' => $this->handleInvoicePaid($event->payload),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->payload),
            'invoice.voided' => $this->handleInvoiceVoided($event->payload),

            // Charge events (→ Transactions)
            'charge.succeeded' => $this->handleChargeSucceeded($event->payload),
            'charge.failed' => $this->handleChargeFailed($event->payload),
            'charge.refunded' => $this->handleChargeRefunded($event->payload),
            'charge.updated' => $this->handleChargeUpdated($event->payload),

            // Coupon events
            'coupon.created',
            'coupon.updated' => $this->handleCouponUpsert($event->payload),
            'coupon.deleted' => $this->handleCouponDeleted($event->payload),

            // Promotion code events
            'promotion_code.created',
            'promotion_code.updated' => $this->handlePromotionCodeUpsert($event->payload),

            // Payment method events
            'payment_method.attached' => $this->handlePaymentMethodAttached($event->payload),
            'payment_method.detached' => $this->handlePaymentMethodDetached($event->payload),
            'payment_method.updated' => $this->handlePaymentMethodUpdated($event->payload),

            // Customer events
            'customer.updated' => $this->handleCustomerUpdated($event->payload),

            default => null,
        };
    }

    protected function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? null;
        if (! is_array($session)) {
            return;
        }

        $sessionId = $session['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $cacheKey = 'stripe_checkout_session_'.$sessionId;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addHours(24));

        $stripeSubscriptionId = $session['subscription'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $billableType = in_array(($metadata['billable_type'] ?? ''), ['user', 'organization'], true)
            ? $metadata['billable_type']
            : 'user';
        $billableId = (int) ($metadata['billable_id'] ?? 0);
        $planId = (int) ($metadata['plan_id'] ?? 0);
        $billingCycle = ($metadata['billing_cycle'] ?? 'month') === 'year' ? 'year' : 'month';

        if ($billableId <= 0) {
            return;
        }

        // Cache metadata so ApplySubscriptionMetadata listener can apply it
        // after Cashier creates the subscription record.
        Cache::put('stripe_sub_meta_'.$stripeSubscriptionId, [
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'plan_id' => $planId,
            'cycle' => $billingCycle,
        ], now()->addHour());

        $this->applyMetadataToExistingSubscription($stripeSubscriptionId, [
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'plan_id' => $planId,
            'cycle' => $billingCycle,
        ]);
    }

    protected function handleSubscriptionUpdated(array $payload): void
    {
        $stripeSubscription = $payload['data']['object'] ?? null;
        if (! is_array($stripeSubscription)) {
            return;
        }

        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        // Update plan_id and cycle if the price changed
        $priceId = $stripeSubscription['items']['data'][0]['price']['id'] ?? null;
        $updatedPlan = $this->findPlanByPriceId(is_string($priceId) ? $priceId : null);

        if ($updatedPlan) {
            $subscription->plan_id = $updatedPlan['plan_id'];
            $subscription->cycle = $updatedPlan['cycle'];

            // Clear pending change if the Stripe update matches the pending plan+cycle
            if ($subscription->hasPendingChange()
                && (int) $subscription->pending_plan_id === $updatedPlan['plan_id']
                && $subscription->pending_cycle === $updatedPlan['cycle']) {
                $subscription->pending_plan_id = null;
                $subscription->pending_cycle = null;
                $subscription->pending_change_scheduled_at = null;
            }
        }

        // Update payment timestamps
        [$periodStart, $periodEnd] = $this->extractPeriodDates($stripeSubscription);
        if ($periodStart) {
            $subscription->last_payment_at = Carbon::createFromTimestamp((int) $periodStart);
        }
        if ($periodEnd) {
            $subscription->next_payment_at = Carbon::createFromTimestamp((int) $periodEnd);
        }

        $subscription->save();
        $subscription->clearBillableCache();
    }

    protected function handleSubscriptionDeleted(array $payload): void
    {
        $stripeSubscription = $payload['data']['object'] ?? null;
        if (! is_array($stripeSubscription)) {
            return;
        }

        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->clearBillableCache();
    }

    /**
     * @return array{plan_id: int, cycle: string}|null
     */
    protected function findPlanByPriceId(?string $priceId): ?array
    {
        if (! $priceId) {
            return null;
        }

        $monthlyPlan = Plan::query()->where('monthly_price_id', $priceId)->first();
        if ($monthlyPlan) {
            return ['plan_id' => (int) $monthlyPlan->id, 'cycle' => 'month'];
        }

        $yearlyPlan = Plan::query()->where('yearly_price_id', $priceId)->first();
        if ($yearlyPlan) {
            return ['plan_id' => (int) $yearlyPlan->id, 'cycle' => 'year'];
        }

        return null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function extractPeriodDates(array $stripeSubscription): array
    {
        $periodStart = $stripeSubscription['current_period_start'] ?? null;
        $periodEnd = $stripeSubscription['current_period_end'] ?? null;

        if (! $periodStart && isset($stripeSubscription['items']['data'][0])) {
            $item = $stripeSubscription['items']['data'][0];
            $periodStart = $item['current_period_start'] ?? null;
            $periodEnd = $item['current_period_end'] ?? null;
        }

        return [
            is_numeric($periodStart) ? (int) $periodStart : null,
            is_numeric($periodEnd) ? (int) $periodEnd : null,
        ];
    }

    /**
     * @param  array{billable_type: string, billable_id: int, plan_id: int, cycle: string}  $metadata
     */
    protected function applyMetadataToExistingSubscription(string $stripeSubscriptionId, array $metadata): void
    {
        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $attributes = [
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
            'cycle' => $metadata['cycle'],
        ];

        if ($metadata['plan_id'] > 0) {
            $attributes['plan_id'] = $metadata['plan_id'];
        }

        $subscription->fill($attributes);
        $subscription->save();

        $subscription->unsetRelation('billable');
        $subscription->clearBillableCache();
        $subscription->user?->clearUserCache($subscription->plan_id);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: resolve billable from Stripe customer ID
    // ──────────────────────────────────────────────────────────────

    /**
     * Find the billable (User) from a Stripe customer ID.
     *
     * @return array{type: string, id: int}|null
     */
    protected function resolveBillable(string $stripeCustomerId): ?array
    {
        $user = \App\Models\User::where('stripe_id', $stripeCustomerId)->first();
        if ($user) {
            return ['type' => 'user', 'id' => $user->id];
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────
    // Invoice events
    // ──────────────────────────────────────────────────────────────

    protected function handleInvoiceUpsert(array $payload): void
    {
        try {
            $invoice = $payload['data']['object'] ?? null;
            if (! is_array($invoice)) {
                return;
            }

            $stripeId = $invoice['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $customerId = $invoice['customer'] ?? null;
            $billable = is_string($customerId) ? $this->resolveBillable($customerId) : null;

            $subscriptionStripeId = $invoice['subscription'] ?? null;
            $localSubscription = is_string($subscriptionStripeId)
                ? Subscription::query()->where('stripe_id', $subscriptionStripeId)->first()
                : null;

            $lineItems = $this->extractLineItems($invoice);

            Invoice::query()->updateOrCreate(
                ['stripe_id' => $stripeId],
                [
                    'stripe_customer_id' => is_string($customerId) ? $customerId : null,
                    'billable_type' => $billable['type'] ?? null,
                    'billable_id' => $billable['id'] ?? null,
                    'subscription_id' => $localSubscription?->id,
                    'number' => $invoice['number'] ?? null,
                    'status' => $invoice['status'] ?? null,
                    'currency' => $invoice['currency'] ?? null,
                    'amount_due' => $invoice['amount_due'] ?? 0,
                    'amount_paid' => $invoice['amount_paid'] ?? 0,
                    'amount_remaining' => $invoice['amount_remaining'] ?? 0,
                    'subtotal' => $invoice['subtotal'] ?? 0,
                    'tax' => $invoice['tax'] ?? 0,
                    'total' => $invoice['total'] ?? 0,
                    'period_start' => $this->carbonFromTimestamp($invoice['period_start'] ?? null),
                    'period_end' => $this->carbonFromTimestamp($invoice['period_end'] ?? null),
                    'due_date' => $this->carbonFromTimestamp($invoice['due_date'] ?? null),
                    'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                    'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
                    'line_items' => $lineItems,
                    'metadata' => is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : null,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: invoice upsert failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleInvoicePaid(array $payload): void
    {
        try {
            $invoice = $payload['data']['object'] ?? null;
            if (! is_array($invoice)) {
                return;
            }

            $stripeId = $invoice['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $customerId = $invoice['customer'] ?? null;
            $billable = is_string($customerId) ? $this->resolveBillable($customerId) : null;

            $subscriptionStripeId = $invoice['subscription'] ?? null;
            $localSubscription = is_string($subscriptionStripeId)
                ? Subscription::query()->where('stripe_id', $subscriptionStripeId)->first()
                : null;

            $paidAt = $invoice['status_transitions']['paid_at'] ?? null;
            $lineItems = $this->extractLineItems($invoice);

            $localInvoice = Invoice::query()->updateOrCreate(
                ['stripe_id' => $stripeId],
                [
                    'stripe_customer_id' => is_string($customerId) ? $customerId : null,
                    'billable_type' => $billable['type'] ?? null,
                    'billable_id' => $billable['id'] ?? null,
                    'subscription_id' => $localSubscription?->id,
                    'number' => $invoice['number'] ?? null,
                    'status' => $invoice['status'] ?? 'paid',
                    'currency' => $invoice['currency'] ?? null,
                    'amount_due' => $invoice['amount_due'] ?? 0,
                    'amount_paid' => $invoice['amount_paid'] ?? 0,
                    'amount_remaining' => $invoice['amount_remaining'] ?? 0,
                    'subtotal' => $invoice['subtotal'] ?? 0,
                    'tax' => $invoice['tax'] ?? 0,
                    'total' => $invoice['total'] ?? 0,
                    'period_start' => $this->carbonFromTimestamp($invoice['period_start'] ?? null),
                    'period_end' => $this->carbonFromTimestamp($invoice['period_end'] ?? null),
                    'due_date' => $this->carbonFromTimestamp($invoice['due_date'] ?? null),
                    'paid_at' => $this->carbonFromTimestamp($paidAt),
                    'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                    'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
                    'line_items' => $lineItems,
                    'metadata' => is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : null,
                ],
            );

            $this->recordCouponRedemptionFromInvoice($invoice, $localInvoice, $localSubscription, $billable);

            // Apply pending plan change if this invoice represents a new billing period
            if ($localSubscription && $localSubscription->hasPendingChange()) {
                $localSubscription->applyPendingChange();
            }
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: invoice paid failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleInvoicePaymentFailed(array $payload): void
    {
        try {
            $invoice = $payload['data']['object'] ?? null;
            if (! is_array($invoice)) {
                return;
            }

            $stripeId = $invoice['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $localInvoice = Invoice::query()->where('stripe_id', $stripeId)->first();
            if ($localInvoice) {
                $localInvoice->update([
                    'status' => $invoice['status'] ?? 'open',
                    'amount_remaining' => $invoice['amount_remaining'] ?? $localInvoice->amount_remaining,
                ]);
            } else {
                $this->handleInvoiceUpsert($payload);
            }
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: invoice payment_failed failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleInvoiceVoided(array $payload): void
    {
        try {
            $invoice = $payload['data']['object'] ?? null;
            if (! is_array($invoice)) {
                return;
            }

            $stripeId = $invoice['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $localInvoice = Invoice::query()->where('stripe_id', $stripeId)->first();
            if ($localInvoice) {
                $localInvoice->update(['status' => 'void']);
            } else {
                $this->handleInvoiceUpsert($payload);
            }
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: invoice voided failed', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Charge events (→ Transactions)
    // ──────────────────────────────────────────────────────────────

    protected function handleChargeSucceeded(array $payload): void
    {
        try {
            $this->upsertTransactionFromCharge($payload, 'succeeded');
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: charge succeeded failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleChargeFailed(array $payload): void
    {
        try {
            $this->upsertTransactionFromCharge($payload, 'failed');
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: charge failed failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleChargeRefunded(array $payload): void
    {
        try {
            $charge = $payload['data']['object'] ?? null;
            if (! is_array($charge)) {
                return;
            }

            $stripeId = $charge['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $transaction = Transaction::query()->where('stripe_id', $stripeId)->first();
            if (! $transaction) {
                $this->upsertTransactionFromCharge($payload, 'refunded');

                return;
            }

            $refundedAmount = $charge['amount_refunded'] ?? 0;
            $totalAmount = $charge['amount'] ?? $transaction->amount;

            $transaction->update([
                'refunded_amount' => (int) $refundedAmount,
                'status' => $refundedAmount >= $totalAmount ? 'refunded' : 'partially_refunded',
            ]);
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: charge refunded failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleChargeUpdated(array $payload): void
    {
        try {
            $charge = $payload['data']['object'] ?? null;
            if (! is_array($charge)) {
                return;
            }

            $stripeId = $charge['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $status = $charge['status'] ?? 'succeeded';
            $this->upsertTransactionFromCharge($payload, $status);
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: charge updated failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create or update a Transaction record from a Stripe charge payload.
     */
    protected function upsertTransactionFromCharge(array $payload, string $status): void
    {
        $charge = $payload['data']['object'] ?? null;
        if (! is_array($charge)) {
            return;
        }

        $stripeId = $charge['id'] ?? null;
        if (! is_string($stripeId) || $stripeId === '') {
            return;
        }

        $customerId = $charge['customer'] ?? null;
        $billable = is_string($customerId) ? $this->resolveBillable($customerId) : null;

        $invoiceStripeId = $charge['invoice'] ?? null;
        $localInvoice = is_string($invoiceStripeId)
            ? Invoice::query()->where('stripe_id', $invoiceStripeId)->first()
            : null;

        $paymentMethodDetails = $charge['payment_method_details'] ?? [];
        $cardDetails = is_array($paymentMethodDetails) ? ($paymentMethodDetails['card'] ?? []) : [];

        Transaction::query()->updateOrCreate(
            ['stripe_id' => $stripeId],
            [
                'stripe_customer_id' => is_string($customerId) ? $customerId : null,
                'invoice_id' => $localInvoice?->id,
                'billable_type' => $billable['type'] ?? null,
                'billable_id' => $billable['id'] ?? null,
                'amount' => $charge['amount'] ?? 0,
                'currency' => $charge['currency'] ?? null,
                'status' => $status,
                'payment_method_type' => is_array($paymentMethodDetails) ? ($paymentMethodDetails['type'] ?? null) : null,
                'payment_method_brand' => is_array($cardDetails) ? ($cardDetails['brand'] ?? null) : null,
                'payment_method_last4' => is_array($cardDetails) ? ($cardDetails['last4'] ?? null) : null,
                'failure_code' => $charge['failure_code'] ?? null,
                'failure_message' => $charge['failure_message'] ?? null,
                'refunded_amount' => $charge['amount_refunded'] ?? 0,
                'description' => $charge['description'] ?? null,
                'metadata' => is_array($charge['metadata'] ?? null) ? $charge['metadata'] : null,
            ],
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Coupon events
    // ──────────────────────────────────────────────────────────────

    protected function handleCouponUpsert(array $payload): void
    {
        try {
            $coupon = $payload['data']['object'] ?? null;
            if (! is_array($coupon)) {
                return;
            }

            $stripeId = $coupon['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $redeemBy = $coupon['redeem_by'] ?? null;

            Coupon::query()->updateOrCreate(
                ['stripe_id' => $stripeId],
                [
                    'name' => $coupon['name'] ?? null,
                    'amount_off' => $coupon['amount_off'] ?? null,
                    'percent_off' => $coupon['percent_off'] ?? null,
                    'currency' => $coupon['currency'] ?? null,
                    'duration' => $coupon['duration'] ?? null,
                    'duration_in_months' => $coupon['duration_in_months'] ?? null,
                    'max_redemptions' => $coupon['max_redemptions'] ?? null,
                    'times_redeemed' => $coupon['times_redeemed'] ?? 0,
                    'active' => $coupon['valid'] ?? true,
                    'valid' => $coupon['valid'] ?? true,
                    'redeem_by' => $this->carbonFromTimestamp($redeemBy),
                    'metadata' => is_array($coupon['metadata'] ?? null) ? $coupon['metadata'] : null,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: coupon upsert failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handleCouponDeleted(array $payload): void
    {
        try {
            $coupon = $payload['data']['object'] ?? null;
            if (! is_array($coupon)) {
                return;
            }

            $stripeId = $coupon['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $localCoupon = Coupon::query()->where('stripe_id', $stripeId)->first();
            if ($localCoupon) {
                $localCoupon->update([
                    'active' => false,
                    'valid' => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: coupon deleted failed', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Promotion code events
    // ──────────────────────────────────────────────────────────────

    protected function handlePromotionCodeUpsert(array $payload): void
    {
        try {
            $promoCode = $payload['data']['object'] ?? null;
            if (! is_array($promoCode)) {
                return;
            }

            $stripeId = $promoCode['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            $couponStripeId = $promoCode['coupon']['id'] ?? null;
            $localCoupon = is_string($couponStripeId)
                ? Coupon::query()->where('stripe_id', $couponStripeId)->first()
                : null;

            $restrictions = $promoCode['restrictions'] ?? [];
            $expiresAt = $promoCode['expires_at'] ?? null;

            PromotionCode::query()->updateOrCreate(
                ['stripe_id' => $stripeId],
                [
                    'coupon_id' => $localCoupon?->id,
                    'code' => $promoCode['code'] ?? null,
                    'active' => $promoCode['active'] ?? true,
                    'max_redemptions' => $promoCode['max_redemptions'] ?? null,
                    'times_redeemed' => $promoCode['times_redeemed'] ?? 0,
                    'first_time_transaction' => is_array($restrictions) ? ($restrictions['first_time_transaction'] ?? false) : false,
                    'minimum_amount' => is_array($restrictions) ? ($restrictions['minimum_amount'] ?? null) : null,
                    'minimum_amount_currency' => is_array($restrictions) ? ($restrictions['minimum_amount_currency'] ?? null) : null,
                    'expires_at' => $this->carbonFromTimestamp($expiresAt),
                    'metadata' => is_array($promoCode['metadata'] ?? null) ? $promoCode['metadata'] : null,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: promotion code upsert failed', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Payment method events
    // ──────────────────────────────────────────────────────────────

    protected function handlePaymentMethodAttached(array $payload): void
    {
        try {
            $this->upsertPaymentMethod($payload);
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: payment_method attached failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handlePaymentMethodUpdated(array $payload): void
    {
        try {
            $this->upsertPaymentMethod($payload);
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: payment_method updated failed', ['error' => $e->getMessage()]);
        }
    }

    protected function handlePaymentMethodDetached(array $payload): void
    {
        try {
            $pm = $payload['data']['object'] ?? null;
            if (! is_array($pm)) {
                return;
            }

            $stripeId = $pm['id'] ?? null;
            if (! is_string($stripeId) || $stripeId === '') {
                return;
            }

            PaymentMethod::query()->where('stripe_id', $stripeId)->delete();
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: payment_method detached failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create or update a local PaymentMethod record from a Stripe payload.
     */
    protected function upsertPaymentMethod(array $payload): void
    {
        $pm = $payload['data']['object'] ?? null;
        if (! is_array($pm)) {
            return;
        }

        $stripeId = $pm['id'] ?? null;
        if (! is_string($stripeId) || $stripeId === '') {
            return;
        }

        $customerId = $pm['customer'] ?? null;
        $billable = is_string($customerId) ? $this->resolveBillable($customerId) : null;

        $cardDetails = $pm['card'] ?? [];

        PaymentMethod::query()->updateOrCreate(
            ['stripe_id' => $stripeId],
            [
                'stripe_customer_id' => is_string($customerId) ? $customerId : null,
                'billable_type' => $billable['type'] ?? null,
                'billable_id' => $billable['id'] ?? null,
                'type' => $pm['type'] ?? null,
                'brand' => is_array($cardDetails) ? ($cardDetails['brand'] ?? null) : null,
                'last4' => is_array($cardDetails) ? ($cardDetails['last4'] ?? null) : null,
                'exp_month' => is_array($cardDetails) ? ($cardDetails['exp_month'] ?? null) : null,
                'exp_year' => is_array($cardDetails) ? ($cardDetails['exp_year'] ?? null) : null,
                'is_default' => false,
                'metadata' => is_array($pm['metadata'] ?? null) ? $pm['metadata'] : null,
            ],
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Customer events
    // ──────────────────────────────────────────────────────────────

    protected function handleCustomerUpdated(array $payload): void
    {
        try {
            $customer = $payload['data']['object'] ?? null;
            if (! is_array($customer)) {
                return;
            }

            $customerId = $customer['id'] ?? null;
            if (! is_string($customerId) || $customerId === '') {
                return;
            }

            $defaultPmId = $customer['invoice_settings']['default_payment_method'] ?? null;
            if (! is_string($defaultPmId) || $defaultPmId === '') {
                return;
            }

            // Reset all payment methods for this customer to non-default
            PaymentMethod::query()
                ->where('stripe_customer_id', $customerId)
                ->update(['is_default' => false]);

            // Set the default payment method
            PaymentMethod::query()
                ->where('stripe_customer_id', $customerId)
                ->where('stripe_id', $defaultPmId)
                ->update(['is_default' => true]);
        } catch (\Throwable $e) {
            Log::error('HandleStripeWebhook: customer updated failed', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Convert a Unix timestamp to a Carbon instance, or null.
     */
    protected function carbonFromTimestamp(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    /**
     * Extract line items from a Stripe invoice payload as a simple array.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extractLineItems(array $invoice): array
    {
        $lines = $invoice['lines']['data'] ?? [];
        if (! is_array($lines)) {
            return [];
        }

        $items = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $items[] = [
                'stripe_id' => $line['id'] ?? null,
                'description' => $line['description'] ?? null,
                'amount' => $line['amount'] ?? 0,
                'currency' => $line['currency'] ?? null,
                'quantity' => $line['quantity'] ?? 1,
                'price_id' => $line['price']['id'] ?? null,
                'product_id' => $line['price']['product'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Record a coupon redemption when an invoice with a discount is paid.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array{type: string, id: int}|null  $billable
     */
    protected function recordCouponRedemptionFromInvoice(
        array $invoice,
        Invoice $localInvoice,
        ?Subscription $localSubscription,
        ?array $billable,
    ): void {
        $discount = $invoice['discount'] ?? null;
        if (! is_array($discount)) {
            $discounts = $invoice['discounts'] ?? [];
            $discount = is_array($discounts) && isset($discounts[0]) && is_array($discounts[0])
                ? $discounts[0]
                : null;
        }

        if (! is_array($discount)) {
            return;
        }

        $couponData = $discount['coupon'] ?? null;
        if (! is_array($couponData)) {
            return;
        }

        $couponStripeId = $couponData['id'] ?? null;
        $localCoupon = is_string($couponStripeId)
            ? Coupon::query()->where('stripe_id', $couponStripeId)->first()
            : null;

        if (! $localCoupon) {
            return;
        }

        $promoCodeStripeId = $discount['promotion_code'] ?? null;
        $localPromoCode = is_string($promoCodeStripeId)
            ? PromotionCode::query()->where('stripe_id', $promoCodeStripeId)->first()
            : null;

        // Calculate discount amount: difference between subtotal and total before tax
        $subtotal = (int) ($invoice['subtotal'] ?? 0);
        $totalBeforeTax = (int) ($invoice['total'] ?? 0) - (int) ($invoice['tax'] ?? 0);
        $discountAmount = max(0, $subtotal - $totalBeforeTax);

        // Avoid duplicate redemptions for the same invoice + coupon
        $exists = CouponRedemption::query()
            ->where('coupon_id', $localCoupon->id)
            ->where('invoice_id', $localInvoice->id)
            ->exists();

        if ($exists) {
            return;
        }

        CouponRedemption::query()->create([
            'coupon_id' => $localCoupon->id,
            'promotion_code_id' => $localPromoCode?->id,
            'invoice_id' => $localInvoice->id,
            'subscription_id' => $localSubscription?->id,
            'billable_type' => $billable['type'] ?? null,
            'billable_id' => $billable['id'] ?? null,
            'discount_amount' => $discountAmount,
            'redeemed_at' => now(),
        ]);
    }
}
