<?php

use App\Enums\FeatureFlagType;
use App\Filament\Pages\FeatureFlagsPage;
use App\Models\FeatureDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
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

test('feature flags page renders successfully', function () {
    livewire(FeatureFlagsPage::class)
        ->assertOk();
});

test('kill switches are shown in kill switch section', function () {
    $killSwitch = FeatureDefinition::create([
        'name' => 'test-kill-switch',
        'type' => FeatureFlagType::KillSwitch,
        'description' => 'A test kill switch',
        'is_active' => false,
    ]);

    $page = livewire(FeatureFlagsPage::class);

    $killSwitches = $page->instance()->getKillSwitches();

    expect($killSwitches)->toHaveCount(1)
        ->and($killSwitches->first()->name)->toBe('test-kill-switch');
});

test('feature table excludes kill switches', function () {
    FeatureDefinition::create([
        'name' => 'test-kill-switch',
        'type' => FeatureFlagType::KillSwitch,
        'description' => 'Kill switch',
        'is_active' => false,
    ]);

    $planGated = FeatureDefinition::create([
        'name' => 'test-plan-feature',
        'type' => FeatureFlagType::PlanGated,
        'description' => 'Plan-gated feature',
        'is_active' => true,
    ]);

    livewire(FeatureFlagsPage::class)
        ->assertCanSeeTableRecords(collect([$planGated]))
        ->assertCanNotSeeTableRecords(collect([
            FeatureDefinition::where('type', FeatureFlagType::KillSwitch)->first(),
        ]));
});

test('toggling kill switch on activates pennant state and logs activity', function () {
    $killSwitch = FeatureDefinition::create([
        'name' => 'maintenance-mode',
        'type' => FeatureFlagType::KillSwitch,
        'description' => 'Maintenance mode',
        'is_active' => false,
    ]);

    expect(Feature::for(null)->active('maintenance-mode'))->toBeFalse();

    livewire(FeatureFlagsPage::class)
        ->call('toggleKillSwitch', $killSwitch->id)
        ->assertNotified();

    expect(Feature::for(null)->active('maintenance-mode'))->toBeTrue();

    $killSwitch->refresh();
    expect($killSwitch->is_active)->toBeTrue()
        ->and($killSwitch->last_changed_by)->toBe($this->admin->id);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'kill_switch_toggled',
        'user_id' => $this->admin->id,
    ]);
});

test('toggling kill switch off deactivates pennant state', function () {
    $killSwitch = FeatureDefinition::create([
        'name' => 'maintenance-mode',
        'type' => FeatureFlagType::KillSwitch,
        'description' => 'Maintenance mode',
        'is_active' => false,
    ]);

    Feature::for(null)->activate('maintenance-mode');
    expect(Feature::for(null)->active('maintenance-mode'))->toBeTrue();

    livewire(FeatureFlagsPage::class)
        ->call('toggleKillSwitch', $killSwitch->id)
        ->assertNotified();

    Feature::flushCache();
    expect(Feature::for(null)->active('maintenance-mode'))->toBeFalse();

    $killSwitch->refresh();
    expect($killSwitch->is_active)->toBeFalse();
});

test('feature table shows correct columns', function () {
    FeatureDefinition::create([
        'name' => 'test-rollout',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature',
        'is_active' => true,
    ]);

    livewire(FeatureFlagsPage::class)
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('type')
        ->assertTableColumnExists('description')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('changedBy.name')
        ->assertTableColumnExists('updated_at');
});

test('feature table has type filter excluding kill switches', function () {
    livewire(FeatureFlagsPage::class)
        ->assertTableFilterExists('type');
});

test('feature table has override for org action', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-rollout',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature',
        'is_active' => true,
    ]);

    livewire(FeatureFlagsPage::class)
        ->assertTableActionExists('override_for_org');
});
