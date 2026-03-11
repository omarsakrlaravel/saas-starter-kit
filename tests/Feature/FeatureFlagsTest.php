<?php

/**
 * Feature Flags (Pennant) Test Suite
 *
 * Tests Pennant integration including:
 * - Default scope resolution via TenantContext (org or user fallback)
 * - Plan-gated features (AiReports)
 * - Rollout features (NewEditor) consistency
 * - Kill switch features (MaintenanceMode) activation/deactivation
 * - Backward compatibility with existing @canUseFeature system
 */

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Role;
use Wave\Plan;
use Wave\Subscription;
use Wave\TenantContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    Feature::flushCache();

    Role::firstOrCreate(
        ['name' => 'admin'],
        ['guard_name' => 'web']
    );
});

test('default scope resolves to organization via TenantContext', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org',
        'owner_user_id' => $user->id,
    ]);

    app(TenantContext::class)->set($org->id);
    $this->actingAs($user);

    Feature::define('test-org-scope', fn ($scope) => $scope instanceof Organization);

    expect(Feature::active('test-org-scope'))->toBeTrue();

    Feature::purge('test-org-scope');
});

test('default scope falls back to user when no tenant context', function () {
    $user = User::factory()->create();

    // Do not set TenantContext -- it remains empty
    $this->actingAs($user);

    Feature::define('test-user-scope', fn ($scope) => $scope instanceof User);

    expect(Feature::active('test-user-scope'))->toBeTrue();

    Feature::purge('test-user-scope');
});

test('plan-gated feature resolves based on plan features array', function () {
    $plan = Plan::create([
        'name' => 'Pro With AI',
        'description' => 'Pro plan with ai-reports',
        'features' => ['ai-reports', 'advanced-analytics'],
        'monthly_price' => '29.00',
        'active' => true,
    ]);

    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Org With AI',
        'slug' => 'org-with-ai',
        'owner_user_id' => $user->id,
    ]);

    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $org->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_ai_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    app(TenantContext::class)->set($org->id);
    $this->actingAs($user);

    expect(Feature::active('ai-reports'))->toBeTrue();

    // Second org without ai-reports
    $planBasic = Plan::create([
        'name' => 'Basic No AI',
        'description' => 'Basic plan without ai-reports',
        'features' => ['basic-feature'],
        'monthly_price' => '9.00',
        'active' => true,
    ]);

    $user2 = User::factory()->create();
    $otherOrg = Organization::create([
        'name' => 'Org Without AI',
        'slug' => 'org-without-ai',
        'owner_user_id' => $user2->id,
    ]);

    Subscription::create([
        'user_id' => $user2->id,
        'type' => 'default',
        'billable_type' => 'organization',
        'billable_id' => $otherOrg->id,
        'plan_id' => $planBasic->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_basic_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    expect(Feature::for($otherOrg)->active('ai-reports'))->toBeFalse();
});

test('plan-gated feature returns false for org without subscription', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'No Sub Org',
        'slug' => 'no-sub-org',
        'owner_user_id' => $user->id,
    ]);

    app(TenantContext::class)->set($org->id);
    $this->actingAs($user);

    expect(Feature::active('ai-reports'))->toBeFalse();
});

test('kill switch defaults to inactive', function () {
    expect(Feature::for(null)->active('maintenance-mode'))->toBeFalse();
});

test('kill switch can be activated and deactivated globally', function () {
    Feature::for(null)->activate('maintenance-mode');
    expect(Feature::for(null)->active('maintenance-mode'))->toBeTrue();

    Feature::for(null)->deactivate('maintenance-mode');
    Feature::flushCache();
    expect(Feature::for(null)->active('maintenance-mode'))->toBeFalse();
});

test('kill switch is scope-independent', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Kill Switch Org',
        'slug' => 'kill-switch-org',
        'owner_user_id' => $user->id,
    ]);

    app(TenantContext::class)->set($org->id);
    $this->actingAs($user);

    Feature::for(null)->activate('maintenance-mode');

    // Null scope is active
    expect(Feature::for(null)->active('maintenance-mode'))->toBeTrue();

    // Org scope resolves from the class definition (default false), not from null scope
    expect(Feature::for($org)->active('maintenance-mode'))->toBeFalse();

    Feature::for(null)->deactivate('maintenance-mode');
});

test('rollout feature resolves consistently for same scope', function () {
    $user = User::factory()->create();
    $org = Organization::create([
        'name' => 'Rollout Org',
        'slug' => 'rollout-org',
        'owner_user_id' => $user->id,
    ]);

    app(TenantContext::class)->set($org->id);
    $this->actingAs($user);

    $result = Feature::active('new-editor');
    $result2 = Feature::active('new-editor');

    expect($result)->toBe($result2);
});

test('Feature::for() allows checking against different organization', function () {
    $user = User::factory()->create();
    $org1 = Organization::create([
        'name' => 'Org One',
        'slug' => 'org-one',
        'owner_user_id' => $user->id,
    ]);
    $org2 = Organization::create([
        'name' => 'Org Two',
        'slug' => 'org-two',
        'owner_user_id' => $user->id,
    ]);

    app(TenantContext::class)->set($org1->id);
    $this->actingAs($user);

    $resultOrg1 = Feature::active('new-editor');
    $resultOrg2 = Feature::for($org2)->active('new-editor');

    // Both should be booleans (may differ due to lottery)
    expect($resultOrg1)->toBeBool()
        ->and($resultOrg2)->toBeBool();
});

test('existing canUseFeature system is unaffected by Pennant', function () {
    $plan = Plan::create([
        'name' => 'Compat Plan',
        'description' => 'Plan for backward compat test',
        'features' => ['Basic features'],
        'monthly_price' => '10.00',
        'active' => true,
        'limits' => [
            'api_keys' => 5,
        ],
    ]);

    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_'.uniqid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_compat_monthly',
        'cycle' => 'month',
        'quantity' => 1,
    ]);

    $this->actingAs($user);

    expect($user->canUseFeature('api_keys'))->toBeTrue()
        ->and($user->featureLimit('api_keys'))->toBe(5);
});
