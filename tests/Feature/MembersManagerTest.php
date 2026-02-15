<?php

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    if (! Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

describe('Members Resource', function () {
    it('hides the current admin from the listing', function () {
        livewire(ListMembers::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords(collect([$this->admin]));
    });

    it('shows other admin-role users', function () {
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('admin');

        livewire(ListMembers::class)
            ->assertOk()
            ->assertCanSeeTableRecords(collect([$otherAdmin]));

        $otherAdmin->forceDelete();
    });

    it('auto-assigns admin role when creating a member', function () {
        livewire(CreateMember::class)
            ->fillForm([
                'name' => 'New Admin',
                'username' => 'newadmin',
                'email' => 'newadmin@example.com',
                'password' => 'password123',
            ])
            ->call('create')
            ->assertNotified()
            ->assertRedirect();

        $newMember = User::where('email', 'newadmin@example.com')->first();
        expect($newMember)->not->toBeNull();
        expect($newMember->hasRole('admin'))->toBeTrue();

        $newMember->forceDelete();
    });
});

describe('Users Resource', function () {
    it('shows only non-admin users', function () {
        $nonAdmin = User::factory()->create();

        livewire(ListUsers::class)
            ->assertOk()
            ->searchTable($nonAdmin->name)
            ->assertCanSeeTableRecords(collect([$nonAdmin]))
            ->assertCanNotSeeTableRecords(collect([$this->admin]));

        $nonAdmin->forceDelete();
    });
});

describe('Roles Resource', function () {
    it('hides the admin role from listing', function () {
        $adminRole = Role::findByName('admin', 'web');

        livewire(ListRoles::class)
            ->assertOk()
            ->assertCanNotSeeTableRecords(collect([$adminRole]));
    });

    it('prevents creating a role named admin', function () {
        livewire(CreateRole::class)
            ->fillForm([
                'name' => 'admin',
                'guard_name' => 'web',
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });
});
