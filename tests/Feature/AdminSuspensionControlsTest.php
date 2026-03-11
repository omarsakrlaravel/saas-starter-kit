<?php

use App\Enums\AccountStatus;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\StatusHistoryRelationManager as OrgStatusHistoryRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\StatusHistoryRelationManager;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

describe('User suspension controls', function () {
    it('shows account status column and badge on user list', function () {
        $activeUser = User::factory()->create(['status' => AccountStatus::Active->value]);
        $restrictedUser = User::factory()->create(['status' => AccountStatus::Restricted->value]);
        $suspendedUser = User::factory()->create(['status' => AccountStatus::Suspended->value]);

        livewire(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords(collect([$activeUser, $restrictedUser, $suspendedUser]));
    });

    it('suspends a user via table action with reason', function () {
        $user = User::factory()->create(['status' => AccountStatus::Active->value]);

        livewire(ListUsers::class)
            ->callAction(TestAction::make('suspend')->table($user), [
                'reason' => 'Violated terms of service',
                'status' => 'suspended',
            ])
            ->assertNotified();

        $user->refresh();

        expect($user->status)->toBe(AccountStatus::Suspended)
            ->and($user->status_reason)->toBe('Violated terms of service');

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_type' => $user->getMorphClass(),
            'suspendable_id' => $user->id,
            'from_status' => AccountStatus::Active->value,
            'to_status' => AccountStatus::Suspended->value,
            'reason' => 'Violated terms of service',
            'applied_by' => $this->admin->id,
        ]);
    });

    it('restricts a user via table action with reason', function () {
        $user = User::factory()->create(['status' => AccountStatus::Active->value]);

        livewire(ListUsers::class)
            ->callAction(TestAction::make('suspend')->table($user), [
                'reason' => 'Billing issue',
                'status' => 'restricted',
            ])
            ->assertNotified();

        $user->refresh();

        expect($user->status)->toBe(AccountStatus::Restricted)
            ->and($user->status_reason)->toBe('Billing issue');

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_type' => $user->getMorphClass(),
            'suspendable_id' => $user->id,
            'from_status' => AccountStatus::Active->value,
            'to_status' => AccountStatus::Restricted->value,
        ]);
    });

    it('unsuspends a user via table action', function () {
        $user = User::factory()->create([
            'status' => AccountStatus::Suspended->value,
            'status_reason' => 'Policy violation',
        ]);

        livewire(ListUsers::class)
            ->callAction(TestAction::make('unsuspend')->table($user))
            ->assertNotified();

        $user->refresh();

        expect($user->status)->toBe(AccountStatus::Active)
            ->and($user->status_reason)->toBe('Unsuspended by admin');

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_type' => $user->getMorphClass(),
            'suspendable_id' => $user->id,
            'from_status' => AccountStatus::Suspended->value,
            'to_status' => AccountStatus::Active->value,
        ]);
    });

    it('hides suspend action when user is already suspended', function () {
        $user = User::factory()->create(['status' => AccountStatus::Suspended->value]);

        livewire(ListUsers::class)
            ->assertActionHidden(TestAction::make('suspend')->table($user));
    });

    it('hides unsuspend action when user is already active', function () {
        $user = User::factory()->create(['status' => AccountStatus::Active->value]);

        livewire(ListUsers::class)
            ->assertActionHidden(TestAction::make('unsuspend')->table($user));
    });

    it('records applied_by as the current admin user', function () {
        $user = User::factory()->create(['status' => AccountStatus::Active->value]);

        livewire(ListUsers::class)
            ->callAction(TestAction::make('suspend')->table($user), [
                'reason' => 'Admin action test',
                'status' => 'suspended',
            ]);

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_id' => $user->id,
            'applied_by' => $this->admin->id,
        ]);
    });

    it('filters users by account status', function () {
        $activeUser = User::factory()->create(['status' => AccountStatus::Active->value]);
        $suspendedUser = User::factory()->create(['status' => AccountStatus::Suspended->value]);

        livewire(ListUsers::class)
            ->filterTable('status', AccountStatus::Suspended->value)
            ->assertCanSeeTableRecords(collect([$suspendedUser]))
            ->assertCanNotSeeTableRecords(collect([$activeUser]));
    });

    it('displays status history on user edit page', function () {
        $user = User::factory()->create(['status' => AccountStatus::Active->value]);

        $history = $user->recordStatusTransition(
            toStatus: AccountStatus::Suspended,
            reason: 'Test suspension',
            appliedById: $this->admin->id,
        );

        livewire(StatusHistoryRelationManager::class, [
            'ownerRecord' => $user,
            'pageClass' => EditUser::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords(collect([$history]));
    });
});

describe('Organization suspension controls', function () {
    it('shows account status column and badge on organization list', function () {
        $activeOrg = Organization::query()->create([
            'name' => 'Active Org',
            'slug' => 'active-org',
            'status' => AccountStatus::Active->value,
            'owner_user_id' => $this->admin->id,
        ]);
        $suspendedOrg = Organization::query()->create([
            'name' => 'Suspended Org',
            'slug' => 'suspended-org',
            'status' => AccountStatus::Suspended->value,
            'owner_user_id' => $this->admin->id,
        ]);

        livewire(ListOrganizations::class)
            ->assertOk()
            ->assertCanSeeTableRecords(collect([$activeOrg, $suspendedOrg]));
    });

    it('suspends an organization via table action with reason', function () {
        $org = Organization::query()->create([
            'name' => 'Test Org',
            'slug' => 'test-org',
            'status' => AccountStatus::Active->value,
            'owner_user_id' => $this->admin->id,
        ]);

        livewire(ListOrganizations::class)
            ->callAction(TestAction::make('suspend')->table($org), [
                'reason' => 'Terms violation',
                'status' => 'suspended',
            ])
            ->assertNotified();

        $org->refresh();

        expect($org->status)->toBe(AccountStatus::Suspended)
            ->and($org->status_reason)->toBe('Terms violation');

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_type' => $org->getMorphClass(),
            'suspendable_id' => $org->id,
            'from_status' => AccountStatus::Active->value,
            'to_status' => AccountStatus::Suspended->value,
            'applied_by' => $this->admin->id,
        ]);
    });

    it('unsuspends an organization via table action', function () {
        $org = Organization::query()->create([
            'name' => 'Suspended Org',
            'slug' => 'suspended-org-test',
            'status' => AccountStatus::Suspended->value,
            'status_reason' => 'Policy violation',
            'owner_user_id' => $this->admin->id,
        ]);

        livewire(ListOrganizations::class)
            ->callAction(TestAction::make('unsuspend')->table($org))
            ->assertNotified();

        $org->refresh();

        expect($org->status)->toBe(AccountStatus::Active)
            ->and($org->status_reason)->toBe('Unsuspended by admin');

        $this->assertDatabaseHas('account_status_histories', [
            'suspendable_type' => $org->getMorphClass(),
            'suspendable_id' => $org->id,
            'from_status' => AccountStatus::Suspended->value,
            'to_status' => AccountStatus::Active->value,
        ]);
    });

    it('displays status history on organization edit page', function () {
        $org = Organization::query()->create([
            'name' => 'History Org',
            'slug' => 'history-org',
            'status' => AccountStatus::Active->value,
            'owner_user_id' => $this->admin->id,
        ]);

        $history = $org->recordStatusTransition(
            toStatus: AccountStatus::Suspended,
            reason: 'Test suspension',
            appliedById: $this->admin->id,
        );

        livewire(OrgStatusHistoryRelationManager::class, [
            'ownerRecord' => $org,
            'pageClass' => EditOrganization::class,
        ])
            ->assertOk()
            ->assertCanSeeTableRecords(collect([$history]));
    });
});
