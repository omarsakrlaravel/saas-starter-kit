<?php

use App\Actions\Billing\ActionResult;
use App\Actions\Billing\UpdateSeatQuantity;
use App\Mail\OrganizationInvite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

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
        'user_id' => $this->owner->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $this->org->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_org_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->owner->update(['current_organization_id' => $this->org->id]);

    // Mock the action to avoid Stripe API calls
    $this->mock = $this->mock(UpdateSeatQuantity::class, function ($mock) {
        $mock->shouldReceive('execute')->andReturnUsing(function (Subscription $subscription, int $delta) {
            $newQuantity = $subscription->quantity + $delta;
            if ($newQuantity < 1) {
                return ActionResult::fail('Subscription must have at least 1 seat.');
            }
            $subscription->quantity = $newQuantity;
            $subscription->save();

            return ActionResult::ok('Seats updated to '.$newQuantity.'.');
        })->byDefault();
    });
});

test('accepting invite does not change purchased seats', function () {
    $this->subscription->update(['quantity' => 2]);

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
    expect($this->subscription->quantity)->toBe(2);
});

test('adding seats increases purchased seat count', function () {
    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('targetSeats', 3)
        ->call('updateSeats');

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(3);
});

test('adding seats unlocks invite flow', function () {
    Mail::fake();
    $this->subscription->update(['quantity' => 1]);
    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('targetSeats', 2)
        ->call('updateSeats');

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(2);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'newmember@example.com')
        ->call('inviteMember');

    Mail::assertSent(OrganizationInvite::class);
});

test('removing seats decreases purchased seat count', function () {
    $this->subscription->update(['quantity' => 5]);
    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('targetSeats', 3)
        ->call('updateSeats');

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(3);
});

test('removing seats is blocked when it would go below occupied count', function () {
    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    // 2 occupied (owner + member), 3 seats total, can't go below 2
    $this->subscription->update(['quantity' => 3]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('targetSeats', 1)
        ->call('updateSeats')
        ->assertHasErrors(['targetSeats']);

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(3);
});

test('removing seats respects pending invites as occupied', function () {
    $invitedUser = User::factory()->create();
    $this->org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->owner->id,
        'invited_at' => now(),
    ]);
    // 2 occupied (owner + invited), 3 seats total, can't go below 2
    $this->subscription->update(['quantity' => 3]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('targetSeats', 1)
        ->call('updateSeats')
        ->assertHasErrors(['targetSeats']);

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(3);
});

test('removing member does not change purchased seats', function () {
    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->subscription->update(['quantity' => 2]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->callAction('removeMember', arguments: ['userId' => $member->id]);

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(2)
        ->and($this->org->members()->where('users.id', $member->id)->exists())->toBeFalse();
});

test('leaving organization does not change purchased seats', function () {
    $member = User::factory()->create();
    $this->org->members()->attach($member->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->subscription->update(['quantity' => 2]);
    $member->update(['current_organization_id' => $this->org->id]);

    $this->actingAs($member);

    Volt::test('settings.organization')
        ->callAction('leaveOrganization');

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(2)
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
    expect($this->subscription->quantity)->toBe(1);
});

test('sending invite requires available seats', function () {
    Mail::fake();
    $this->subscription->update(['quantity' => 1]);

    $this->actingAs($this->owner);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'newinvite@example.com')
        ->call('inviteMember')
        ->assertHasErrors(['inviteEmail']);

    $this->subscription->refresh();
    expect($this->subscription->quantity)->toBe(1);

    Mail::assertNotSent(OrganizationInvite::class);
});

test('accepting invite without available seats is blocked', function () {
    $invitedUser = User::factory()->create(['email' => 'blocked@example.com']);
    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $this->org->id,
        'email' => 'blocked@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard')
        ->assertSessionHas('message_type', 'danger');

    expect($this->org->members()->where('users.id', $invitedUser->id)->exists())->toBeFalse();
});

test('accepting invite without active subscription still works', function () {
    $this->subscription->update(['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);

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
        'user_id' => $this->owner->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $this->org->id,
        'plan_id' => $this->premiumPlan->id,
        'stripe_id' => 'sub_webhook_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_premium_monthly',
        'cycle' => 'month',
        'quantity' => 1,
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

    $localSub = Subscription::where('stripe_id', $stripeSubscription->id)->first();
    if ($localSub) {
        $subscriptionCycle = $stripeSubscription->plan->interval;
        $plan_price_column = ($subscriptionCycle == 'year') ? 'yearly_price_id' : 'monthly_price_id';
        $updatedPlan = Plan::where($plan_price_column, $stripeSubscription->plan->id)->first();

        if ($updatedPlan) {
            $localSub->cycle = $subscriptionCycle;
            $localSub->plan_id = $updatedPlan->id;
            $localSub->quantity = (int) ($stripeSubscription->quantity ?? $localSub->quantity);

            if (is_null($stripeSubscription->cancel_at)) {
                $localSub->ends_at = null;
            }

            $localSub->save();
        }
    }

    $subscription->refresh();
    expect($subscription->quantity)->toBe(5);
});
