<?php

/**
 * Subscription Page Test Suite
 *
 * Tests the subscription settings pages including:
 * - Subscription overview for subscribed users
 * - Change plan sub-page rendering
 * - Authentication requirements for all sub-pages
 */

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Premium',
        'description' => 'Premium test plan',
        'features' => 'Feature 1, Feature 2',
        'monthly_price' => '29.00',
        'yearly_price' => '290.00',
        'monthly_price_id' => 'price_monthly_test',
        'yearly_price_id' => 'price_yearly_test',
        'active' => true,
    ]);
});

afterEach(function () {
    Subscription::query()->delete();
    if (isset($this->plan)) {
        $this->plan->delete();
    }
});

it('shows subscription overview for subscribed user', function () {
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $this->plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_yearly_test',
        'cycle' => 'year',
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->get('/settings/subscription')
        ->assertOk()
        ->assertSee('Premium Plan');
});

it('shows change plan page for subscribed user', function () {
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $this->plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_yearly_test',
        'cycle' => 'year',
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->get('/settings/subscription/change-plan')
        ->assertOk()
        ->assertSee('Back to Subscription');
});

it('requires auth for subscription sub-pages', function () {
    $this->get('/settings/subscription/change-plan')
        ->assertRedirect();
});
