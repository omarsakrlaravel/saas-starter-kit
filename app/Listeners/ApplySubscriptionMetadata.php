<?php

namespace App\Listeners;

use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookHandled;

class ApplySubscriptionMetadata
{
    /**
     * Apply Wave metadata after Cashier creates the subscription record.
     *
     * WebhookHandled fires AFTER Cashier's handler, so the subscription
     * row already exists when this runs.
     */
    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if ($type !== 'customer.subscription.created') {
            return;
        }

        $stripeSubscription = $event->payload['data']['object'] ?? null;
        if (! is_array($stripeSubscription)) {
            return;
        }

        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $cacheKey = 'stripe_sub_meta_'.$stripeSubscriptionId;
        $metadata = $this->resolveMetadata($stripeSubscription, $cacheKey);
        if (! is_array($metadata)) {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        Cache::forget($cacheKey);

        $attributes = [
            'billable_type' => $metadata['billable_type'],
            'billable_id' => $metadata['billable_id'],
        ];

        if (($metadata['plan_id'] ?? null) > 0) {
            $attributes['plan_id'] = $metadata['plan_id'];
        }

        if (($metadata['cycle'] ?? null) !== null) {
            $attributes['cycle'] = $metadata['cycle'];
        }

        $subscription->fill($attributes);
        $subscription->save();

        // Unload the morphTo so it resolves with the updated billable_type/id.
        $subscription->unsetRelation('billable');
        $subscription->clearBillableCache();

        // Also clear the checkout user's own cache directly,
        // in case their billing context differs from the billable.
        $subscription->user?->clearUserCache($subscription->plan_id);
    }

    /**
     * @return array{billable_type: string, billable_id: int, plan_id: int|null, cycle: string|null}|null
     */
    protected function resolveMetadata(array $stripeSubscription, string $cacheKey): ?array
    {
        $payloadMetadata = $stripeSubscription['metadata'] ?? null;
        $normalizedPayload = is_array($payloadMetadata) ? $this->normalizeMetadata($payloadMetadata) : null;
        if ($normalizedPayload !== null) {
            return $normalizedPayload;
        }

        $cachedMetadata = Cache::pull($cacheKey);
        if (! is_array($cachedMetadata)) {
            return null;
        }

        return $this->normalizeMetadata($cachedMetadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{billable_type: string, billable_id: int, plan_id: int|null, cycle: string|null}|null
     */
    protected function normalizeMetadata(array $metadata): ?array
    {
        $billableType = in_array(($metadata['billable_type'] ?? ''), ['user', 'organization'], true)
            ? $metadata['billable_type']
            : null;
        $billableId = (int) ($metadata['billable_id'] ?? 0);
        $planId = (int) ($metadata['plan_id'] ?? 0);
        $cycleValue = $metadata['cycle'] ?? $metadata['billing_cycle'] ?? null;
        $cycle = $cycleValue === 'year' ? 'year' : ($cycleValue === 'month' ? 'month' : null);

        if (! is_string($billableType) || $billableId <= 0) {
            return null;
        }

        return [
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'plan_id' => $planId > 0 ? $planId : null,
            'cycle' => $cycle,
        ];
    }
}
