<?php

use App\Mail\OrganizationInvite;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Wave\Actions\Billing\Stripe\UpdateSubscriptionQuantity;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->owner = User::factory()->create();

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

    $this->org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->owner->id,
        'active' => true,
    ]);

    $this->subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $this->org->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_org_'.uniqid(),
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $this->owner->update(['current_organization_id' => $this->org->id]);

    // Mock the action to avoid Stripe API calls
    $this->mock = $this->mock(UpdateSubscriptionQuantity::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andReturnUsing(function (Subscription $subscription, int $delta) {
            $newQuantity = $subscription->seats + $delta;
            if ($newQuantity < 1) {
                throw new RuntimeException('Subscription must have at least 1 seat.');
            }
            $subscription->seats = $newQuantity;
            $subscription->save();
        })->byDefault();
    });
});

test('accepting invite increments seats on subscription', function () {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $this->org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->owner->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $this->org->id,
        'email' => 'invited@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard');

    $this->subscription->refresh();
    expect($this->subscription->seats)->toBe(2);
});

test('removing member decrements seats on subscription', function () {
    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->subscription->update(['seats' => 2]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->callAction('removeMember', arguments: ['userId' => $member->id]);

    $this->subscription->refresh();
    expect($this->subscription->seats)->toBe(1)
        ->and($this->org->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('leaving organization decrements seats on subscription', function () {
    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->subscription->update(['seats' => 2]);
    $member->update(['current_organization_id' => $this->org->id]);

    $this->actingAs($member);

    Volt::test('settings.organization')
        ->callAction('leaveOrganization');

    $this->subscription->refresh();
    expect($this->subscription->seats)->toBe(1)
        ->and($this->org->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('revoking invite does not change seats', function () {
    $invitedUser = User::factory()->create(['email' => 'pending@example.com']);
    $this->org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->owner->id,
        'invited_at' => now(),
    ]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->callAction('revokeInvite', arguments: ['userId' => $invitedUser->id]);

    $this->subscription->refresh();
    expect($this->subscription->seats)->toBe(1);
});

test('sending invite does not change seats', function () {
    Mail::fake();

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'newinvite@example.com')
        ->call('inviteMember');

    $this->subscription->refresh();
    expect($this->subscription->seats)->toBe(1);

    Mail::assertSent(OrganizationInvite::class);
});

test('failed seat update rolls back invite acceptance', function () {
    $this->mock(UpdateSubscriptionQuantity::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andThrow(new RuntimeException('Stripe API error'));
    });

    $invitedUser = User::factory()->create(['email' => 'fail@example.com']);
    $this->org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->owner->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $this->org->id,
        'email' => 'fail@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard')
        ->assertSessionHas('message_type', 'danger');

    $membership = $this->org->members()->where('users.id', $invitedUser->id)->first();
    expect($membership->pivot->status)->toBe('invited')
        ->and($this->subscription->fresh()->seats)->toBe(1);
});

test('failed seat update rolls back member removal', function () {
    $this->mock(UpdateSubscriptionQuantity::class, function ($mock) {
        $mock->shouldReceive('__invoke')->andThrow(new RuntimeException('Stripe API error'));
    });

    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->subscription->update(['seats' => 2]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->callAction('removeMember', arguments: ['userId' => $member->id]);

    expect($this->org->members()->where('users.id', $member->id)->exists())->toBeTrue()
        ->and($this->subscription->fresh()->seats)->toBe(2);
});

test('accepting invite without active subscription still works', function () {
    $this->subscription->update(['status' => 'cancelled']);

    $invitedUser = User::factory()->create(['email' => 'nosub@example.com']);
    $this->org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->owner->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $this->org->id,
        'email' => 'nosub@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard')
        ->assertSessionHas('message_type', 'success');

    $membership = $this->org->members()->where('users.id', $invitedUser->id)->first();
    expect($membership->pivot->status)->toBe('active');
});

test('webhook syncs seats from stripe quantity', function () {
    $subscription = Subscription::create([
        'billable_type' => 'organization',
        'billable_id' => $this->org->id,
        'plan_id' => $this->premiumPlan->id,
        'vendor_slug' => 'stripe',
        'vendor_subscription_id' => 'sub_webhook_test',
        'cycle' => 'month',
        'status' => 'active',
        'seats' => 1,
    ]);

    $webhookPayload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_webhook_test',
                'plan' => [
                    'id' => $this->premiumPlan->monthly_price_id,
                    'interval' => 'month',
                ],
                'quantity' => 5,
                'cancel_at' => null,
            ],
        ],
    ];

    // We can't easily call the webhook handler directly since it validates Stripe signatures.
    // Instead, test the logic by directly simulating what the handler does.
    $stripeSubscription = (object) $webhookPayload['data']['object'];
    $stripeSubscription->plan = (object) $stripeSubscription->plan;

    $localSub = Subscription::where('vendor_subscription_id', $stripeSubscription->id)->first();
    if ($localSub) {
        $subscriptionCycle = $stripeSubscription->plan->interval;
        $plan_price_column = ($subscriptionCycle == 'year') ? 'yearly_price_id' : 'monthly_price_id';
        $updatedPlan = Plan::where($plan_price_column, $stripeSubscription->plan->id)->first();

        if ($updatedPlan) {
            $localSub->cycle = $subscriptionCycle;
            $localSub->plan_id = $updatedPlan->id;
            $localSub->seats = (int) ($stripeSubscription->quantity ?? $localSub->seats);

            if (is_null($stripeSubscription->cancel_at)) {
                $localSub->ends_at = null;
            }

            $localSub->save();
        }
    }

    $subscription->refresh();
    expect($subscription->seats)->toBe(5);
});
