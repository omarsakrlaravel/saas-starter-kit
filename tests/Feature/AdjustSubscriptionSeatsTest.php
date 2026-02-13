<?php

use App\Models\Organization;
use App\Models\User;
use Wave\Actions\Billing\Stripe\UpdateSubscriptionQuantity;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    // Clean seeded org data so tests control their own state
    Subscription::query()->delete();
    Organization::query()->delete();

    $this->plan = Plan::where('name', 'Premium')->first();
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

    $this->mock(UpdateSubscriptionQuantity::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andReturnUsing(function (Subscription $subscription, int $delta, string $prorationBehavior = 'create_prorations') {
            $newQuantity = $subscription->seats + $delta;
            if ($newQuantity < 1) {
                throw new RuntimeException('Subscription must have at least 1 seat.');
            }
            $subscription->seats = $newQuantity;
            $subscription->save();
        })->byDefault();
    });
});

test('reduces empty seats on organization subscriptions', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 5,
    ]);

    // Only owner occupies 1 seat, 4 are empty
    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $subscription->refresh();
    expect($subscription->seats)->toBe(1);
});

test('skips subscriptions where all seats are occupied', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Full Org',
        'slug' => 'full-org',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $member = User::factory()->create();
    $org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 2,
    ]);

    // 2 occupied (owner + member), 2 seats — no change
    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $subscription->refresh();
    expect($subscription->seats)->toBe(2);
});

test('counts pending invites as occupied seats', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Invite Org',
        'slug' => 'invite-org',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $invited = User::factory()->create();
    $org->members()->attach($invited->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $owner->id,
        'invited_at' => now(),
    ]);

    $subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 5,
    ]);

    // 2 occupied (owner + invited), should reduce to 2
    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $subscription->refresh();
    expect($subscription->seats)->toBe(2);
});

test('skips cancelled subscriptions', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Cancelled Org',
        'slug' => 'cancelled-org',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'cancelled',
        'seats' => 5,
    ]);

    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $subscription->refresh();
    expect($subscription->seats)->toBe(5);
});

test('skips user subscriptions', function () {
    $user = User::factory()->create();

    $subscription = Subscription::create([
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 5,
    ]);

    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $subscription->refresh();
    expect($subscription->seats)->toBe(5);
});

test('handles multiple organizations in a single run', function () {
    // Org 1: 5 seats, 1 occupied → should reduce to 1
    $owner1 = User::factory()->create();
    $org1 = Organization::create([
        'name' => 'Org One',
        'slug' => 'org-one',
        'owner_user_id' => $owner1->id,
        'active' => true,
    ]);
    $sub1 = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org1->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 5,
    ]);

    // Org 2: 3 seats, 2 occupied → should reduce to 2
    $owner2 = User::factory()->create();
    $org2 = Organization::create([
        'name' => 'Org Two',
        'slug' => 'org-two',
        'owner_user_id' => $owner2->id,
        'active' => true,
    ]);
    $member = User::factory()->create();
    $org2->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $sub2 = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org2->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 3,
    ]);

    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();

    $sub1->refresh();
    $sub2->refresh();
    expect($sub1->seats)->toBe(1)
        ->and($sub2->seats)->toBe(2);
});

test('uses no-proration when adjusting seats', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Proration Test',
        'slug' => 'proration-test',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $this->plan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 3,
    ]);

    $this->mock(UpdateSubscriptionQuantity::class, function ($mock) {
        $mock->shouldReceive('__invoke')
            ->once()
            ->withArgs(function (Subscription $subscription, int $delta, string $prorationBehavior) {
                return $delta === -2 && $prorationBehavior === 'none';
            })
            ->andReturnUsing(function (Subscription $subscription, int $delta) {
                $subscription->seats += $delta;
                $subscription->save();
            });
    });

    $this->artisan('subscriptions:adjust-seats')
        ->assertSuccessful();
});
