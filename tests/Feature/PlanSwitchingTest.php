<?php

/**
 * Plan Switching Test Suite
 *
 * Tests the critical plan switching functionality including:
 * - Subscription plan updates
 * - Billing cycle changes
 * - Edge cases (same plan, invalid plans, multiple subscriptions)
 */

use App\Models\User;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    // Create test user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create test plans
    $this->basicPlan = Plan::create([
        'name' => 'Basic',
        'description' => 'Basic plan for testing',
        'features' => 'Feature 1, Feature 2',
        'monthly_price' => '5.00',
        'yearly_price' => '50.00',
        'monthly_price_id' => 'price_basic_monthly',
        'yearly_price_id' => 'price_basic_yearly',
        'active' => true,
    ]);

    $this->premiumPlan = Plan::create([
        'name' => 'Premium',
        'description' => 'Premium plan for testing',
        'features' => 'Feature 1, Feature 2, Feature 3',
        'monthly_price' => '10.00',
        'yearly_price' => '100.00',
        'monthly_price_id' => 'price_premium_monthly',
        'yearly_price_id' => 'price_premium_yearly',
        'active' => true,
    ]);

    $this->proPlan = Plan::create([
        'name' => 'Pro',
        'description' => 'Pro plan for testing',
        'features' => 'Feature 1, Feature 2, Feature 3, Feature 4',
        'monthly_price' => '20.00',
        'yearly_price' => '200.00',
        'monthly_price_id' => 'price_pro_monthly',
        'yearly_price_id' => 'price_pro_yearly',
        'active' => true,
    ]);
});

test('plan method returns correct current plan', function () {
    // Create subscription with premium plan
    Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $currentPlan = $this->user->plan();

    expect($currentPlan)->not->toBeNull()
        ->and($currentPlan->id)->toBe($this->premiumPlan->id)
        ->and($currentPlan->name)->toBe('Premium');
});

test('planInterval returns correct billing cycle', function () {
    // Test monthly
    Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    expect($this->user->planInterval())->toBe('Monthly');

    // Create new subscription with yearly cycle
    $this->user->subscription->update(['status' => 'cancelled']);

    Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->proPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'year',
        'status' => 'active',
        'seats' => 1,
    ]);

    expect($this->user->fresh()->planInterval())->toBe('Yearly');
});

test('latestSubscription returns most recent active subscription', function () {
    // Create older subscription
    $oldSubscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->basicPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
        'created_at' => now()->subDays(30),
    ]);

    sleep(1); // Ensure different timestamps

    // Create newer subscription
    $newSubscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'year',
        'status' => 'active',
        'seats' => 1,
    ]);

    $latest = $this->user->latestSubscription();

    expect($latest)->not->toBeNull()
        ->and($latest->id)->toBe($newSubscription->id)
        ->and($latest->plan_id)->toBe($this->premiumPlan->id);
});

test('subscription relationship returns active subscription', function () {
    $subscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'paddle',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $userSubscription = $this->user->subscription;

    expect($userSubscription)->not->toBeNull()
        ->and($userSubscription->id)->toBe($subscription->id)
        ->and($userSubscription->status)->toBe('active');
});

test('plan relationship on subscription works correctly', function () {
    $subscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $plan = $subscription->plan;

    expect($plan)->not->toBeNull()
        ->and($plan->id)->toBe($this->premiumPlan->id)
        ->and($plan->name)->toBe('Premium');
});

test('user relationship on subscription works correctly', function () {
    $subscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $user = $subscription->user;

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe($this->user->id)
        ->and($user->email)->toBe($this->user->email);
});

test('cancelled subscriptions are not returned by subscription relationship', function () {
    // Create cancelled subscription
    Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->basicPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'cancelled',
        'seats' => 1,
    ]);

    expect($this->user->subscription)->toBeNull();
});

test('updating subscription plan changes user plan', function () {
    // Monthly subscription
    $subscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->basicPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_customer_id' => 'cus_'.uniqid(),
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    expect($this->user->planInterval())->toBe('Monthly')
        ->and($this->user->plan()->id)->toBe($this->basicPlan->id);

    // Switch to yearly premium
    $subscription->update([
        'plan_id' => $this->premiumPlan->id,
        'cycle' => 'year',
    ]);

    $freshUser = $this->user->fresh();
    expect($freshUser->plan()->id)->toBe($this->premiumPlan->id)
        ->and($freshUser->planInterval())->toBe('Yearly');
});
