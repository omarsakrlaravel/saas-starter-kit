<?php

use App\Listeners\HandleStripeWebhook;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->listener = app(HandleStripeWebhook::class);
    $this->owner = User::factory()->create();

    $this->plan = Plan::query()->first();

    if (! $this->plan) {
        $this->plan = Plan::create([
            'name' => 'Premium',
            'description' => 'Premium test plan',
            'features' => 'Feature 1, Feature 2',
            'monthly_price' => '10.00',
            'yearly_price' => '100.00',
            'monthly_price_id' => 'price_premium_monthly',
            'yearly_price_id' => 'price_premium_yearly',
            'active' => true,
        ]);
    }
});

test('checkout session completed updates an existing subscription metadata immediately', function () {
    $organization = Organization::create([
        'name' => 'Acme Team',
        'slug' => 'acme-team',
        'owner_user_id' => $this->owner->id,
        'active' => true,
    ]);

    $stripeSubscriptionId = 'sub_checkout_meta_'.uniqid();

    $subscription = Subscription::create([
        'user_id' => $this->owner->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->owner->id,
        'plan_id' => $this->plan->id,
        'stripe_id' => $stripeSubscriptionId,
        'stripe_status' => 'active',
        'stripe_price' => (string) ($this->plan->monthly_price_id ?: 'price_test_monthly'),
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $event = new WebhookReceived([
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.uniqid(),
                'subscription' => $stripeSubscriptionId,
                'metadata' => [
                    'billable_type' => 'organization',
                    'billable_id' => (string) $organization->id,
                    'plan_id' => (string) $this->plan->id,
                    'billing_cycle' => 'year',
                ],
            ],
        ],
    ]);

    $this->listener->handle($event);

    $subscription->refresh();

    expect($subscription->billable_type)->toBe('organization')
        ->and($subscription->billable_id)->toBe($organization->id)
        ->and($subscription->plan_id)->toBe($this->plan->id)
        ->and($subscription->cycle)->toBe('year')
        ->and(Cache::has('stripe_sub_meta_'.$stripeSubscriptionId))->toBeTrue();
});
