<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->user = User::factory()->create();

    $this->premiumPlan = Plan::where('name', 'Premium')->first();
    if (! $this->premiumPlan) {
        $this->premiumPlan = Plan::create([
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

test('latest subscription follows active current organization context', function () {
    $owner = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $this->user->organizations()->attach($organization->id, [
        'role' => 'member',
        'status' => 'active',
    ]);

    $userSubscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_user_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $organizationSubscription = Subscription::create([
        'user_id' => $owner->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $organization->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_org_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);
    $this->user->refresh();

    expect($this->user->latestSubscription()->id)->toBe($organizationSubscription->id)
        ->and($this->user->subscriber())->toBeTrue()
        ->and($userSubscription->fresh()->id)->not->toBeNull();
});

test('non-active organization membership falls back to direct user subscription', function () {
    $owner = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $this->user->organizations()->attach($organization->id, [
        'role' => 'member',
        'status' => 'invited',
    ]);

    $userSubscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_user_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);
    $this->user->refresh();

    expect($this->user->getBillingContext()['type'])->toBe('user')
        ->and($this->user->latestSubscription()->id)->toBe($userSubscription->id);
});

test('organization owner is automatically added as an active owner member', function () {
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $ownerMembership = $organization->members()
        ->where('users.id', $this->user->id)
        ->first();

    expect($ownerMembership)->not->toBeNull()
        ->and($ownerMembership->pivot->role)->toBe('owner')
        ->and($ownerMembership->pivot->status)->toBe('active')
        ->and($ownerMembership->pivot->joined_at)->not->toBeNull();
});

test('active organization member without billing role cannot manage billing context', function () {
    $owner = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $this->user->organizations()->attach($organization->id, [
        'role' => 'member',
        'status' => 'active',
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);
    $this->user->refresh();

    expect($this->user->getBillingContext()['type'])->toBe('organization')
        ->and($this->user->canManageBillingContext())->toBeFalse();
});

test('organization owner can manage billing context', function () {
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);
    $this->user->refresh();

    expect($this->user->getBillingContext()['type'])->toBe('organization')
        ->and($this->user->canManageBillingContext())->toBeTrue();
});

test('platform admin can manage any billing context', function () {
    $this->user->assignRole('admin');

    $owner = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $this->user->organizations()->attach($organization->id, [
        'role' => 'member',
        'status' => 'active',
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);

    expect($this->user->getBillingContext()['type'])->toBe('organization')
        ->and($this->user->canManageBillingContext())->toBeTrue();
});

test('cancel endpoint returns failure for unauthorized organization member', function () {
    $owner = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $this->user->organizations()->attach($organization->id, [
        'role' => 'member',
        'status' => 'active',
    ]);

    $subscription = Subscription::create([
        'user_id' => $owner->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $organization->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_org_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->user->update(['current_organization_id' => $organization->id]);
    $this->user->refresh();

    $this->actingAs($this->user)
        ->postJson(route('subscription.cancel'))
        ->assertUnprocessable()
        ->assertJson(['status' => 0]);
});

test('cancel endpoint returns failure response when user has no active subscription', function () {
    $this->actingAs($this->user)
        ->postJson(route('subscription.cancel'))
        ->assertUnprocessable()
        ->assertJson(['status' => 0]);
});

test('organization subscriptions resolve user to the owner', function () {
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $organization->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_org_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    expect($subscription->user)->not->toBeNull()
        ->and($subscription->user->id)->toBe($this->user->id)
        ->and($subscription->billable?->is($organization))->toBeTrue();
});

test('billing context can be switched through settings route', function () {
    $organization = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $this->user->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_user_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $organization->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_org_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('settings.billing-context'), [
            'current_organization_id' => $organization->id,
        ]);

    $response->assertRedirect();
    $this->user->refresh();

    expect($this->user->current_organization_id)->toBe($organization->id)
        ->and($this->user->getBillingContext()['type'])->toBe('organization')
        ->and($this->user->latestSubscription()?->billable_type)->toBe('organization');
});

test('billing context cannot be switched to organization you do not belong to', function () {
    $otherOwner = User::factory()->create();
    $otherOrganization = Organization::create([
        'name' => 'Other Corp',
        'slug' => 'other-corp',
        'owner_user_id' => $otherOwner->id,
        'active' => true,
    ]);

    $this->actingAs($this->user)
        ->post(route('settings.billing-context'), [
            'current_organization_id' => $otherOrganization->id,
        ])
        ->assertSessionHas('message', 'You do not belong to that organization.')
        ->assertSessionHas('message_type', 'danger');

    $this->user->refresh();
    expect($this->user->current_organization_id)->toBeNull();
});
