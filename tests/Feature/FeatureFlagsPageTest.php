<?php

use App\Enums\FeatureFlagType;
use App\Filament\Resources\FeatureDefinitions\Pages\ListFeatureDefinitions;
use App\Filament\Resources\FeatureDefinitions\Pages\ViewFeatureDefinition;
use App\Filament\Widgets\KillSwitchesWidget;
use App\Models\FeatureDefinition;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Feature::flushCache();

    if (! Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

test('feature flags list page renders successfully', function () {
    livewire(ListFeatureDefinitions::class)
        ->assertOk();
});

test('kill switches are shown in kill switch widget', function () {
    $killSwitch = FeatureDefinition::create([
        'name' => 'test-kill-switch',
        'type' => FeatureFlagType::KillSwitch,
        'description' => 'A test kill switch',
        'is_active' => false,
    ]);

    $widget = livewire(KillSwitchesWidget::class);

    $killSwitches = $widget->instance()->getKillSwitches();

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

    livewire(ListFeatureDefinitions::class)
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

    livewire(KillSwitchesWidget::class)
        ->callAction('toggleKillSwitch', arguments: [
            'id' => $killSwitch->id,
            'active' => false,
            'name' => 'maintenance-mode',
        ])
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

    livewire(KillSwitchesWidget::class)
        ->callAction('toggleKillSwitch', arguments: [
            'id' => $killSwitch->id,
            'active' => true,
            'name' => 'maintenance-mode',
        ])
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

    livewire(ListFeatureDefinitions::class)
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('type')
        ->assertTableColumnExists('description')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('changedBy.name')
        ->assertTableColumnExists('updated_at');
});

test('feature table has type filter excluding kill switches', function () {
    livewire(ListFeatureDefinitions::class)
        ->assertTableFilterExists('type');
});

test('feature table has override for org action', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-rollout',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature',
        'is_active' => true,
    ]);

    livewire(ListFeatureDefinitions::class)
        ->assertActionExists(TestAction::make('override_for_org')->table($feature));
});

test('feature table toggle column updates is_active and records last_changed_by', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-plan-feature',
        'type' => FeatureFlagType::PlanGated,
        'description' => 'Plan-gated feature',
        'is_active' => false,
    ]);

    livewire(ListFeatureDefinitions::class)
        ->assertTableColumnStateSet('is_active', false, $feature)
        ->call('updateTableColumnState', 'is_active', (string) $feature->getKey(), true);

    $feature->refresh();
    expect($feature->is_active)->toBeTrue()
        ->and($feature->last_changed_by)->toBe($this->admin->id);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'feature_flag_toggled',
        'user_id' => $this->admin->id,
    ]);
});

test('per-org override action activates feature for specific organization', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-rollout-override',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature for override test',
        'is_active' => true,
    ]);

    $org = Organization::create([
        'name' => 'Override Test Org',
        'slug' => 'override-test-org',
        'owner_user_id' => $this->admin->id,
    ]);

    Feature::for($org)->deactivate('test-rollout-override');
    Feature::flushCache();
    expect(Feature::for($org)->active('test-rollout-override'))->toBeFalse();

    livewire(ListFeatureDefinitions::class)
        ->callAction(TestAction::make('override_for_org')->table($feature), [
            'organization_id' => $org->id,
            'is_active' => true,
        ])
        ->assertNotified();

    Feature::flushCache();
    expect(Feature::for($org)->active('test-rollout-override'))->toBeTrue();

    $feature->refresh();
    expect($feature->last_changed_by)->toBe($this->admin->id);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'feature_flag_override',
        'user_id' => $this->admin->id,
    ]);
});

test('per-org override action deactivates feature for specific organization', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-rollout-deactivate',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature for deactivation override',
        'is_active' => true,
    ]);

    $org = Organization::create([
        'name' => 'Deactivate Override Org',
        'slug' => 'deactivate-override-org',
        'owner_user_id' => $this->admin->id,
    ]);

    Feature::for($org)->activate('test-rollout-deactivate');
    Feature::flushCache();
    expect(Feature::for($org)->active('test-rollout-deactivate'))->toBeTrue();

    livewire(ListFeatureDefinitions::class)
        ->callAction(TestAction::make('override_for_org')->table($feature), [
            'organization_id' => $org->id,
            'is_active' => false,
        ])
        ->assertNotified();

    Feature::flushCache();
    expect(Feature::for($org)->active('test-rollout-deactivate'))->toBeFalse();
});

test('view page renders with infolist', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-view-feature',
        'type' => FeatureFlagType::PlanGated,
        'description' => 'A feature for view page test',
        'is_active' => true,
    ]);

    livewire(ViewFeatureDefinition::class, ['record' => $feature->id])
        ->assertOk();
});

test('edit rollout percentage action updates percentage', function () {
    $feature = FeatureDefinition::create([
        'name' => 'test-rollout-edit',
        'type' => FeatureFlagType::Rollout,
        'description' => 'A rollout feature for edit test',
        'is_active' => true,
        'rollout_percentage' => 50,
    ]);

    livewire(ViewFeatureDefinition::class, ['record' => $feature->id])
        ->callAction('editRolloutPercentage', [
            'rollout_percentage' => 75,
        ])
        ->assertNotified();

    $feature->refresh();
    expect($feature->rollout_percentage)->toBe(75)
        ->and($feature->last_changed_by)->toBe($this->admin->id);
});
