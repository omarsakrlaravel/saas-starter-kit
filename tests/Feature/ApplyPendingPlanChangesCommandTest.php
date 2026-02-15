<?php

use App\Models\User;
use Wave\Plan;
use Wave\Subscription;

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

test('command reports no pending changes when none exist', function () {
    $this->artisan('wave:apply-pending-plan-changes')
        ->expectsOutput('No pending plan changes to apply.')
        ->assertSuccessful();
});

test('command skips subscriptions with future next_payment_at', function () {
    Subscription::create([
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
        'next_payment_at' => now()->addMonth(),
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => now()->addMonth(),
    ]);

    $this->artisan('wave:apply-pending-plan-changes')
        ->expectsOutput('No pending plan changes to apply.')
        ->assertSuccessful();
});

test('command skips non-active subscriptions', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
        'next_payment_at' => now()->subDay(),
        'pending_plan_id' => $this->basicPlan->id,
        'pending_cycle' => 'month',
        'pending_change_scheduled_at' => now()->subDay(),
    ]);

    $this->artisan('wave:apply-pending-plan-changes')
        ->expectsOutput('No pending plan changes to apply.')
        ->assertSuccessful();
});
