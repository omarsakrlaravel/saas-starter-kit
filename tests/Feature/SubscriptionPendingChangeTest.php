<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->user = User::factory()->create();

    $this->basicPlan = Plan::create([
        'name' => 'Basic',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '100.00',
        'monthly_price_id' => 'price_basic_monthly',
        'yearly_price_id' => 'price_basic_yearly',
        'active' => true,
        'sort_order' => 1,
    ]);

    $this->premiumPlan = Plan::create([
        'name' => 'Premium',
        'features' => 'Feature 1, Feature 2',
        'monthly_price' => '25.00',
        'yearly_price' => '250.00',
        'monthly_price_id' => 'price_premium_monthly',
        'yearly_price_id' => 'price_premium_yearly',
        'active' => true,
        'sort_order' => 2,
    ]);
});

test('hasPendingChange returns false when no pending change', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    expect($subscription->hasPendingChange())->toBeFalse();
});

test('hasPendingChange returns true when pending plan is set', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => now()->addMonth(),
    ]);

    expect($subscription->hasPendingChange())->toBeTrue();
});

test('cancelPendingChange clears all pending columns', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => now()->addMonth(),
    ]);

    $subscription->cancelPendingChange();

    $subscription->refresh();

    expect($subscription->pending_plan_id)->toBeNull()
        ->and($subscription->pending_cycle)->toBeNull()
        ->and($subscription->pending_change_scheduled_at)->toBeNull()
        ->and($subscription->hasPendingChange())->toBeFalse();
});

test('pendingPlan relationship returns the correct plan', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => now()->addMonth(),
    ]);

    expect($subscription->pendingPlan)->not->toBeNull()
        ->and($subscription->pendingPlan->id)->toBe($this->basicPlan->id)
        ->and($subscription->pendingPlan->name)->toBe('Basic');
});

test('pending_change_scheduled_at is cast to datetime', function () {
    $scheduledAt = now()->addMonth();

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => $scheduledAt,
    ]);

    $subscription->refresh();

    expect($subscription->pending_change_scheduled_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
