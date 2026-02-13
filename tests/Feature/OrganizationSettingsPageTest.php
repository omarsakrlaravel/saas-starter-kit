<?php

use App\Mail\OrganizationInvite;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->user = User::factory()->create();
});

test('organization settings page loads for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk();
});

test('unauthenticated user is redirected from organization settings', function () {
    $this->get('/settings/organization')
        ->assertRedirect(route('login'));
});

test('user with no organization sees create form', function () {
    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Create Organization');
});

test('owner can create organization', function () {
    $this->actingAs($this->user);

    Volt::test('settings.organization')
        ->set('organizationName', 'My Team')
        ->call('createOrganization');

    $org = Organization::where('name', 'My Team')->first();
    expect($org)->not->toBeNull()
        ->and($org->owner_user_id)->toBe($this->user->id);
});

test('owner sees member list and invite form', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee('Invite by email address');
});

test('member sees read-only view without invite form', function () {
    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);
    $this->user->organizations()->attach($org->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertDontSee('Invite by email address');
});

test('owner can invite member by email', function () {
    Mail::fake();

    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'new@example.com')
        ->call('inviteMember');

    Mail::assertSent(OrganizationInvite::class, function ($mail) {
        return $mail->hasTo('new@example.com');
    });

    expect($org->members()->wherePivot('status', 'invited')->count())->toBe(1);
});

test('member cannot send invitations', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);
    $this->user->organizations()->attach($org->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    $this->user->update(['current_organization_id' => $org->id]);

    $this->actingAs($this->user);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'new@example.com')
        ->call('inviteMember');

    Mail::assertNotSent(OrganizationInvite::class);
});

test('cannot invite same email twice', function () {
    Mail::fake();

    $existingMember = User::factory()->create(['email' => 'existing@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $this->user->update(['current_organization_id' => $org->id]);
    $org->members()->attach($existingMember->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($this->user);

    Volt::test('settings.organization')
        ->set('inviteEmail', 'existing@example.com')
        ->call('inviteMember')
        ->assertHasErrors('inviteEmail');

    Mail::assertNotSent(OrganizationInvite::class);
});

test('existing user can accept invite via signed URL', function () {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);
    $org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->user->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'invited@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->get($url)
        ->assertRedirect('/dashboard');

    $membership = $org->members()->where('users.id', $invitedUser->id)->first();
    expect($membership->pivot->status)->toBe('active')
        ->and($membership->pivot->joined_at)->not->toBeNull();
});

test('invalid signed URL is rejected', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $this->get(route('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'test@example.com',
    ]))
        ->assertForbidden();
});

test('guest clicking invite is redirected to login with invite in session', function () {
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'newuser@example.com',
    ], now()->addDays(7));

    $this->get($url)
        ->assertRedirect(route('login'))
        ->assertSessionHas('org_invite_url', $url);
});

test('after login, user is redirected to pending invite URL from session', function () {
    $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
    $org = Organization::create([
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'owner_user_id' => $this->user->id,
        'active' => true,
    ]);

    $org->members()->attach($invitedUser->id, [
        'role' => 'member',
        'status' => 'invited',
        'invited_by' => $this->user->id,
        'invited_at' => now(),
    ]);

    $url = URL::signedRoute('organization.invite.accept', [
        'organization' => $org->id,
        'email' => 'invited@example.com',
    ], now()->addDays(7));

    $this->actingAs($invitedUser)
        ->withSession(['org_invite_url' => $url])
        ->get('/dashboard')
        ->assertRedirect($url);

    expect(session('org_invite_url'))->toBeNull();
});
