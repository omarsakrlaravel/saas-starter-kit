<?php

use App\Enums\AccountStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts user status and evaluates account state helpers', function () {
    $activeUser = User::factory()->create();
    $restrictedUser = User::factory()->create([
        'status' => AccountStatus::Restricted->value,
        'status_reason' => 'Billing grace period',
    ]);
    $suspendedUser = User::factory()->create([
        'status' => AccountStatus::Suspended->value,
    ]);

    expect($activeUser->status)->toBeInstanceOf(AccountStatus::class)
        ->and($activeUser->status)->toBe(AccountStatus::Active)
        ->and($activeUser->isActiveAccount())->toBeTrue()
        ->and($activeUser->isBlockedFromSession())->toBeFalse()
        ->and($activeUser->statusDisplay())->toBe('Active')
        ->and($restrictedUser->status)->toBeInstanceOf(AccountStatus::class)
        ->and($restrictedUser->isRestricted())->toBeTrue()
        ->and($restrictedUser->isBlockedFromSession())->toBeTrue()
        ->and($restrictedUser->statusDisplay())->toBe('Restricted')
        ->and($suspendedUser->status)->toBeInstanceOf(AccountStatus::class)
        ->and($suspendedUser->isSuspended())->toBeTrue()
        ->and($suspendedUser->isBlockedFromSession())->toBeTrue()
        ->and($suspendedUser->statusDisplay())->toBe('Suspended');
});

it('casts organization status and evaluates account helpers', function () {
    $activeOrg = Organization::query()->create([
        'name' => 'Active Org',
        'slug' => 'active-org',
        'status' => AccountStatus::Active->value,
        'status_reason' => null,
    ]);

    $restrictedOrg = Organization::query()->create([
        'name' => 'Restricted Org',
        'slug' => 'restricted-org',
        'status' => AccountStatus::Restricted->value,
        'status_reason' => 'billing hold',
    ]);

    expect($activeOrg->status)->toBeInstanceOf(AccountStatus::class)
        ->and($activeOrg->isActiveAccount())->toBeTrue()
        ->and($activeOrg->isBlockedFromSession())->toBeFalse()
        ->and($activeOrg->statusDisplay())->toBe('Active')
        ->and($restrictedOrg->status)->toBeInstanceOf(AccountStatus::class)
        ->and($restrictedOrg->isRestricted())->toBeTrue()
        ->and($restrictedOrg->isBlockedFromSession())->toBeTrue()
        ->and($restrictedOrg->statusDisplay())->toBe('Restricted');
});

it('checks user organization blocking and active organization fallback', function () {
    $user = User::factory()->create();
    $organization = Organization::query()->create([
        'name' => 'Default Org',
        'slug' => 'default-org',
        'status' => AccountStatus::Active->value,
        'owner_user_id' => $user->id,
    ]);

    $user->update(['current_organization_id' => $organization->id]);

    expect($user->organizationIsBlocked())->toBeFalse()
        ->and($user->activeOrganizationOrSelf()->is($organization))->toBeTrue()
        ->and($user->isBlockedFromSession())->toBeFalse();

    $organization->update([
        'status' => AccountStatus::Suspended->value,
        'status_reason' => 'Policy violation',
    ]);

    $user->unsetRelation('currentOrganization');

    expect($user->organizationIsBlocked())->toBeTrue()
        ->and($user->activeOrganizationOrSelf()->is($user))->toBeTrue()
        ->and($user->isBlockedFromSession())->toBeTrue();
});

it('filters users and organizations with status scopes', function () {
    $activeUser = User::factory()->create(['status' => AccountStatus::Active->value]);
    $restrictedUser = User::factory()->create(['status' => AccountStatus::Restricted->value]);
    $suspendedUser = User::factory()->create(['status' => AccountStatus::Suspended->value]);

    $userStatuses = User::query()
        ->scopeActiveOrRestricted()
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($userStatuses)->toContain($activeUser->id, $restrictedUser->id)
        ->and($userStatuses)->not()->toContain($suspendedUser->id);

    $activeOrg = Organization::query()->create([
        'name' => 'Scope Active Org',
        'slug' => 'scope-active-org',
        'status' => AccountStatus::Active->value,
        'owner_user_id' => $activeUser->id,
    ]);

    $restrictedOrg = Organization::query()->create([
        'name' => 'Scope Restricted Org',
        'slug' => 'scope-restricted-org',
        'status' => AccountStatus::Restricted->value,
        'owner_user_id' => $activeUser->id,
    ]);

    $suspendedOrg = Organization::query()->create([
        'name' => 'Scope Suspended Org',
        'slug' => 'scope-suspended-org',
        'status' => AccountStatus::Suspended->value,
        'owner_user_id' => $activeUser->id,
    ]);

    $orgStatuses = Organization::query()
        ->scopeActiveOrRestricted()
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($orgStatuses)->toContain($activeOrg->id, $restrictedOrg->id)
        ->and($orgStatuses)->not()->toContain($suspendedOrg->id);

    expect(User::query()->scopeWithStatus(AccountStatus::Suspended->value)->count())->toBe(1)
        ->and(Organization::query()->scopeWithStatus(AccountStatus::Active->value)->count())->toBe(1);
});
