<?php

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(TenantContext::class)->set(null);

    $this->ownerA = User::factory()->create([
        'email' => 'owner-a@example.com',
    ]);

    $this->ownerB = User::factory()->create([
        'email' => 'owner-b@example.com',
    ]);

    $this->organizationA = Organization::query()->create([
        'name' => 'Organization A',
        'slug' => 'organization-a',
        'owner_user_id' => $this->ownerA->id,
        'active' => true,
    ]);

    $this->organizationB = Organization::query()->create([
        'name' => 'Organization B',
        'slug' => 'organization-b',
        'owner_user_id' => $this->ownerB->id,
        'active' => true,
    ]);

    $this->ownerA->update([
        'current_organization_id' => $this->organizationA->id,
    ]);

    $this->ownerB->update([
        'current_organization_id' => $this->organizationB->id,
    ]);

    $this->orgALogs = [
        ActivityLog::query()->create([
            'user_id' => $this->ownerA->id,
            'organization_id' => $this->organizationA->id,
            'action' => 'org_a_action_1',
            'description' => 'Organization A activity 1',
        ]),
        ActivityLog::query()->create([
            'user_id' => $this->ownerA->id,
            'organization_id' => $this->organizationA->id,
            'action' => 'org_a_action_2',
            'description' => 'Organization A activity 2',
        ]),
    ];

    $this->orgBLogs = [
        ActivityLog::query()->create([
            'user_id' => $this->ownerB->id,
            'organization_id' => $this->organizationB->id,
            'action' => 'org_b_action_1',
            'description' => 'Organization B activity 1',
        ]),
        ActivityLog::query()->create([
            'user_id' => $this->ownerB->id,
            'organization_id' => $this->organizationB->id,
            'action' => 'org_b_action_2',
            'description' => 'Organization B activity 2',
        ]),
    ];

    Route::middleware('web')->get('/_tenant-context-probe', function (TenantContext $context) {
        return response()->json([
            'has' => $context->has(),
            'organization_id' => $context->get(),
        ]);
    });
});

it('scopes queries to current tenant when context is set', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $logs = ActivityLog::query()->get();

    expect($logs)->toHaveCount(2);
    expect($logs->contains(fn (ActivityLog $log): bool => $log->organization_id === $this->organizationB->id))->toBeFalse();
});

it('returns all records when no tenant context is set', function () {
    app(TenantContext::class)->set(null);

    $logs = ActivityLog::query()->get();

    expect($logs)->toHaveCount(4);
});

it('prevents cross-tenant data access', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $crossTenantRecord = ActivityLog::query()->find($this->orgBLogs[0]->id);

    expect($crossTenantRecord)->toBeNull();
});

it('scopes relationship queries', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $users = User::query()
        ->whereKey([$this->ownerA->id, $this->ownerB->id])
        ->with('activityLogs')
        ->get()
        ->keyBy('id');

    expect($users->get($this->ownerA->id)->activityLogs)->toHaveCount(2);
    expect($users->get($this->ownerB->id)->activityLogs)->toHaveCount(0);
});

it('auto-fills organization_id on create when context is set', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $log = ActivityLog::query()->create([
        'user_id' => $this->ownerA->id,
        'action' => 'auto_fill_org',
        'description' => 'Auto fill org id from context',
    ]);

    expect($log->organization_id)->toBe($this->organizationA->id);
});

it('does not override explicitly set organization_id', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $log = ActivityLog::query()->create([
        'user_id' => $this->ownerA->id,
        'organization_id' => $this->organizationB->id,
        'action' => 'explicit_org_wins',
        'description' => 'Explicit org id should not be overwritten',
    ]);

    expect($log->organization_id)->toBe($this->organizationB->id);
});

it('leaves organization_id null when no context is set', function () {
    app(TenantContext::class)->set(null);

    $log = ActivityLog::query()->create([
        'user_id' => $this->ownerA->id,
        'action' => 'no_context_org',
        'description' => 'Organization remains null when context is missing',
    ]);

    expect($log->organization_id)->toBeNull();
});

it('allows bypassing scope with withoutGlobalScope', function () {
    app(TenantContext::class)->set($this->organizationA->id);

    $logs = ActivityLog::query()
        ->withoutGlobalScope(TenantScope::class)
        ->get();

    expect($logs)->toHaveCount(4);
});

it('sets tenant context for authenticated user with organization', function () {
    $response = $this->actingAs($this->ownerA)->get('/_tenant-context-probe');

    $response
        ->assertOk()
        ->assertJson([
            'has' => true,
            'organization_id' => $this->organizationA->id,
        ]);

    $context = app(TenantContext::class);

    expect($context->has())->toBeTrue();
    expect($context->get())->toBe($this->organizationA->id);
});

it('does not set tenant context for user without organization', function () {
    $userWithoutOrganization = User::factory()->create([
        'email' => 'owner-c@example.com',
        'current_organization_id' => null,
    ]);

    $response = $this->actingAs($userWithoutOrganization)->get('/_tenant-context-probe');

    $response
        ->assertOk()
        ->assertJson([
            'has' => false,
            'organization_id' => null,
        ]);

    expect(app(TenantContext::class)->has())->toBeFalse();
});

it('does not set tenant context for unauthenticated requests', function () {
    $response = $this->get('/_tenant-context-probe');

    $response
        ->assertOk()
        ->assertJson([
            'has' => false,
            'organization_id' => null,
        ]);

    expect(app(TenantContext::class)->has())->toBeFalse();
});

it('captures organization_id in activity log entries', function () {
    config([
        'activity.enabled' => true,
        'activity.queue' => false,
    ]);

    app(TenantContext::class)->set($this->organizationA->id);
    $this->actingAs($this->ownerA);

    $log = ActivityLog::log('test_action', 'test');

    expect($log)->not->toBeNull();
    expect($log?->organization_id)->toBe($this->organizationA->id);
});
