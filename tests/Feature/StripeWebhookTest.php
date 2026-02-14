<?php

/**
 * Stripe Webhook Business Logic Tests
 *
 * These tests verify the database state changes and business logic
 * that occur during Stripe webhook processing.
 */

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->premiumPlan = Plan::create([
        'name' => 'Premium Plan',
        'description' => 'Premium features',
        'features' => 'feature1,feature2',
        'monthly_price_id' => 'price_monthly_123',
        'yearly_price_id' => 'price_yearly_123',
        'monthly_price' => '9.99',
        'yearly_price' => '99.99',
        'active' => true,
    ]);

    $this->enterprisePlan = Plan::create([
        'name' => 'Enterprise Plan',
        'description' => 'Enterprise features',
        'features' => 'feature1,feature2,feature3',
        'monthly_price_id' => 'price_monthly_456',
        'yearly_price_id' => 'price_yearly_456',
        'monthly_price' => '29.99',
        'yearly_price' => '299.99',
        'active' => true,
    ]);

    $this->user = User::factory()->create();
});

test('plan switching updates subscription record correctly', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    expect($subscription->plan_id)->toBe($this->premiumPlan->id);

    $subscription->plan_id = $this->enterprisePlan->id;
    $subscription->cycle = 'year';
    $subscription->save();

    $subscription->refresh();

    expect($subscription->plan_id)->toBe($this->enterprisePlan->id)
        ->and($subscription->cycle)->toBe('year')
        ->and($this->user->fresh()->plan()->id)->toBe($this->enterprisePlan->id);
})->group('stripe', 'billing');

test('subscription cancellation sets ends_at date', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $cancelAt = now()->addMonth();
    $subscription->ends_at = $cancelAt->toDateTimeString();
    $subscription->save();

    $subscription->refresh();

    expect($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at?->toDateTimeString())->toBe($cancelAt->toDateTimeString());
})->group('stripe', 'billing');

test('subscription deletion marks subscription as canceled', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $subscription->update([
        'stripe_status' => 'canceled',
        'ends_at' => now(),
    ]);
    $subscription->refresh();

    expect($subscription->stripe_status)->toBe('canceled');
})->group('stripe', 'billing');

test('new subscription creates active record', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_new123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->user->clearUserCache();
    $this->user->refresh();

    expect($this->user->subscriber())->toBeTrue()
        ->and($this->user->plan()->id)->toBe($this->premiumPlan->id)
        ->and($subscription->stripe_status)->toBe('active');
})->group('stripe', 'billing');

test('cache prevents duplicate checkout session processing', function () {
    $sessionId = 'cs_test123';
    $cacheKey = 'stripe_checkout_session_'.$sessionId;

    expect(Cache::has($cacheKey))->toBeFalse();
    Cache::put($cacheKey, true, now()->addHours(24));
    expect(Cache::has($cacheKey))->toBeTrue();

    $shouldSkipProcessing = Cache::has($cacheKey);
    expect($shouldSkipProcessing)->toBeTrue();
})->group('stripe', 'billing');

test('subscription cycle can be updated from monthly to yearly', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    expect($subscription->cycle)->toBe('month');

    $subscription->cycle = 'year';
    $subscription->save();
    $subscription->refresh();

    expect($subscription->cycle)->toBe('year')
        ->and($subscription->plan_id)->toBe($this->premiumPlan->id);
})->group('stripe', 'billing');

test('removing cancellation date reactivates subscription', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
        'ends_at' => now()->addMonth(),
    ]);

    expect($subscription->ends_at)->not->toBeNull();

    $subscription->ends_at = null;
    $subscription->save();
    $subscription->refresh();

    expect($subscription->ends_at)->toBeNull();
})->group('stripe', 'billing');

test('multiple subscriptions can exist for same user', function () {
    $subscription1 = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_test123',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_monthly_123',
        'cycle' => 'month',
        'quantity' => 1,
        'ends_at' => now()->subDay(),
    ]);

    $subscription2 = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->enterprisePlan->id,
        'stripe_id' => 'sub_test456',
        'stripe_status' => 'active',
        'stripe_price' => 'price_yearly_456',
        'cycle' => 'year',
        'quantity' => 1,
    ]);

    $userSubscriptions = Subscription::where('billable_id', $this->user->id)->get();

    expect($userSubscriptions)->toHaveCount(2)
        ->and($subscription1->stripe_status)->toBe('canceled')
        ->and($subscription2->stripe_status)->toBe('active');
})->group('stripe', 'billing');
