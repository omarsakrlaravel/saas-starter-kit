<?php

namespace App\Listeners;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;
use Wave\Plan;
use Wave\Subscription;

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
}
