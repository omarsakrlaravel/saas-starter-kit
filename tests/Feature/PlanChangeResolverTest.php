<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanChangeResolver;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->user = User::factory()->create();
    $this->resolver = new PlanChangeResolver();

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

    $this->proPlan = Plan::create([
        'name' => 'Pro',
        'features' => 'Feature 1, Feature 2, Feature 3',
        'monthly_price' => '50.00',
        'yearly_price' => '500.00',
        'monthly_price_id' => 'price_pro_monthly',
        'yearly_price_id' => 'price_pro_yearly',
        'active' => true,
        'sort_order' => 3,
    ]);
});

function createTestSubscription(User $user, Plan $plan, string $cycle = 'month'): Subscription
{
    return Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => $cycle === 'month' ? $plan->monthly_price_id : $plan->yearly_price_id,
        'cycle' => $cycle,
        'quantity' => 1,
    ]);
}

test('same plan and same cycle returns same', function () {
    $subscription = createTestSubscription($this->user, $this->basicPlan, 'month');

    expect($this->resolver->resolve($subscription, $this->basicPlan, 'month'))->toBe('same');
});

test('same plan monthly to yearly is cycle_change', function () {
    $subscription = createTestSubscription($this->user, $this->basicPlan, 'month');

    expect($this->resolver->resolve($subscription, $this->basicPlan, 'year'))->toBe('cycle_change');
});

test('same plan yearly to monthly is cycle_change', function () {
    $subscription = createTestSubscription($this->user, $this->basicPlan, 'year');

    expect($this->resolver->resolve($subscription, $this->basicPlan, 'month'))->toBe('cycle_change');
});

test('higher price plan is upgrade', function () {
    $subscription = createTestSubscription($this->user, $this->basicPlan, 'month');

    expect($this->resolver->resolve($subscription, $this->premiumPlan, 'month'))->toBe('upgrade');
});

test('lower price plan is downgrade', function () {
    $subscription = createTestSubscription($this->user, $this->premiumPlan, 'month');

    expect($this->resolver->resolve($subscription, $this->basicPlan, 'month'))->toBe('downgrade');
});

test('upgrade from basic monthly to pro yearly', function () {
    $subscription = createTestSubscription($this->user, $this->basicPlan, 'month');

    expect($this->resolver->resolve($subscription, $this->proPlan, 'year'))->toBe('upgrade');
});

test('downgrade from pro monthly to basic yearly', function () {
    $subscription = createTestSubscription($this->user, $this->proPlan, 'month');

    // Pro monthly = $50/mo, Basic yearly = $100/12 = $8.33/mo → downgrade
    expect($this->resolver->resolve($subscription, $this->basicPlan, 'year'))->toBe('downgrade');
});

test('equal effective price uses sort_order for tiebreaker', function () {
    $planA = Plan::create([
        'name' => 'Plan A',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '120.00',
        'monthly_price_id' => 'price_a_monthly',
        'yearly_price_id' => 'price_a_yearly',
        'active' => true,
        'sort_order' => 5,
    ]);

    $planB = Plan::create([
        'name' => 'Plan B',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '120.00',
        'monthly_price_id' => 'price_b_monthly',
        'yearly_price_id' => 'price_b_yearly',
        'active' => true,
        'sort_order' => 10,
    ]);

    $subscription = createTestSubscription($this->user, $planA, 'month');

    // Plan B has higher sort_order → upgrade
    expect($this->resolver->resolve($subscription, $planB, 'month'))->toBe('upgrade');

    // From B to A → downgrade
    $subscription->update(['plan_id' => $planB->id]);
    expect($this->resolver->resolve($subscription->fresh(), $planA, 'month'))->toBe('downgrade');
});

test('equal effective price and equal sort_order returns same', function () {
    $planA = Plan::create([
        'name' => 'Plan A',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '120.00',
        'monthly_price_id' => 'price_a2_monthly',
        'yearly_price_id' => 'price_a2_yearly',
        'active' => true,
        'sort_order' => 5,
    ]);

    $planB = Plan::create([
        'name' => 'Plan B',
        'features' => 'Feature 1',
        'monthly_price' => '10.00',
        'yearly_price' => '120.00',
        'monthly_price_id' => 'price_b2_monthly',
        'yearly_price_id' => 'price_b2_yearly',
        'active' => true,
        'sort_order' => 5,
    ]);

    $subscription = createTestSubscription($this->user, $planA, 'month');

    expect($this->resolver->resolve($subscription, $planB, 'month'))->toBe('same');
});
