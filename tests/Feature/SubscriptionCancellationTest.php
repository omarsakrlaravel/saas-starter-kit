<?php

use App\Models\User;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->premiumPlan = Plan::where('name', 'Premium')->first();

    if (! $this->premiumPlan) {
        $this->premiumPlan = Plan::create([
            'name' => 'Premium Plan',
            'description' => 'Premium subscription plan',
            'features' => 'Feature 1, Feature 2',
            'monthly_price' => '10.00',
            'yearly_price' => '100.00',
            'monthly_price_id' => 'price_monthly_test',
            'yearly_price_id' => 'price_yearly_test',
            'active' => true,
        ]);
    }
});

test('subscription cancellation sets stripe_status to canceled', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_test',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    // Simulate cancellation (can't call Cashier cancel() without Stripe)
    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ]);

    expect($subscription->fresh()->stripe_status)->toBe('canceled');
});

test('subscriber returns false after cancellation with past ends_at', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_test',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    \Illuminate\Support\Facades\Cache::forget("user_subscriber_{$this->user->id}");
    expect($this->user->fresh()->subscriber())->toBeTrue();

    // Simulate cancellation with past ends_at
    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
    ]);

    \Illuminate\Support\Facades\Cache::forget("user_subscriber_{$this->user->id}");
    expect($this->user->fresh()->subscriber())->toBeFalse();
});

test('subscriber returns true during grace period', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_test',
        'cycle' => 'month',
        'quantity' => 1,
        'ends_at' => now()->addDays(15),
    ]);

    \Illuminate\Support\Facades\Cache::forget("user_subscriber_{$this->user->id}");
    expect($this->user->fresh()->subscriber())->toBeTrue();
});

test('multiple subscriptions only cancel the specific one', function () {
    $subscription1 = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_old_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_test',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $subscription2 = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_new_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_yearly_test',
        'cycle' => 'year',
        'quantity' => 1,
    ]);

    // Simulate cancellation of first subscription
    $subscription1->update([
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
    ]);

    expect($subscription1->fresh()->stripe_status)->toBe('canceled')
        ->and($subscription2->fresh()->stripe_status)->toBe('active');

    \Illuminate\Support\Facades\Cache::forget("user_subscriber_{$this->user->id}");
    expect($this->user->fresh()->subscriber())->toBeTrue();
});
