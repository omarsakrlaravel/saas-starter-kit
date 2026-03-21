<?php

use App\Enums\AccountStatus;
use App\Jobs\Middleware\EnsureAccountActive;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Wave\ActivityLog;
use Wave\Http\Middleware\TokenMiddleware;
use Wave\Jobs\CreateActivityLog;

uses(RefreshDatabase::class);

it('redirects restricted users to the restricted page for protected web routes', function () {
    $user = User::factory()->create([
        'status' => AccountStatus::Restricted->value,
        'status_reason' => 'Billing issue',
    ]);

    $this->actingAs($user);

    $response = $this->get('/settings');

    $response->assertRedirect(route('account.restricted'));
});

it('returns blocked responses for suspended users on protected web routes', function () {
    $user = User::factory()->create([
        'status' => AccountStatus::Suspended->value,
        'status_reason' => 'Policy violation',
    ]);

    $this->actingAs($user);

    $response = $this->get('/settings');

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $response->assertSee('Account suspended');
});

it('blocks token-authenticated requests when account is not active', function () {
    if (strlen(config('jwt.secret', '')) < 32) {
        $this->markTestSkipped('JWT secret not configured for testing');
    }

    $user = User::factory()->create([
        'status' => AccountStatus::Suspended->value,
    ]);
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user');

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $response->assertJsonPath('error_code', 'account_suspended');
});

it('rejects blocked account token middleware usage for API key flow', function () {
    $user = User::factory()->create([
        'status' => AccountStatus::Suspended->value,
    ]);
    $apiKey = $user->createApiKey('Blocked service key');

    $request = Request::create('/api/token', 'POST', [
        'token' => $apiKey->plainTextToken,
    ]);

    $middleware = new TokenMiddleware(app('auth'));
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    expect($response->getData(true)['error_code'])->toBe('account_blocked');
});

it('re-checks account state at job execution via middleware', function () {
    $user = User::factory()->create();
    ActivityLog::query()->where('user_id', $user->id)->where('action', 'test.account_status_gate')->delete();

    $jobData = [
        'user_id' => $user->id,
        'action' => 'test.account_status_gate',
        'description' => 'first run',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
    ];

    $job = new CreateActivityLog($jobData);
    $middleware = new EnsureAccountActive();

    $executed = false;
    $middleware->handle($job, function () use (&$executed, $job): void {
        $executed = true;
        $job->handle();
    });

    $user->update(['status' => AccountStatus::Suspended->value]);

    $jobData['description'] = 'second run';
    $secondJob = new CreateActivityLog($jobData);

    $executedAfterSuspend = false;
    $middleware->handle($secondJob, function () use (&$executedAfterSuspend, $secondJob): void {
        $executedAfterSuspend = true;
        $secondJob->handle();
    });

    expect($executed)->toBeTrue()
        ->and($executedAfterSuspend)->toBeFalse()
        ->and(ActivityLog::where('action', 'test.account_status_gate')->count())->toBe(1);
});

it('only authorizes account channels for active accounts', function () {
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Test Org',
        'slug' => 'test-org-channel',
        'status' => AccountStatus::Active->value,
        'owner_user_id' => $user->id,
    ]);
    $user->update(['current_organization_id' => $organization->id]);

    $channels = Broadcast::getChannels();
    $userChannel = $channels->get('private.user.{id}');
    $organizationChannel = $channels->get('private.organization.{organization}');

    expect($userChannel)->not()->toBeNull();
    expect($organizationChannel)->not()->toBeNull();
    expect($userChannel($user, $user->id))->toBeTrue();
    expect($organizationChannel($user, $organization->id))->toBeTrue();

    $organization->update(['status' => AccountStatus::Suspended->value]);

    expect($user->isBlockedFromSession())->toBeTrue();
    expect($userChannel($user, $user->id))->toBeFalse();
    expect($organizationChannel($user, $organization->id))->toBeFalse();
});

it('does not authorize organization channels when current organization membership is stale', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $organization = Organization::create([
        'name' => 'Stale Org',
        'slug' => 'stale-org-channel',
        'status' => AccountStatus::Active->value,
        'owner_user_id' => $owner->id,
        'active' => true,
    ]);

    $organization->members()->attach($user->id, [
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->update(['current_organization_id' => $organization->id]);

    $channels = Broadcast::getChannels();
    $organizationChannel = $channels->get('private.organization.{organization}');

    expect($organizationChannel)->not()->toBeNull();
    expect($organizationChannel($user->fresh(), $organization->id))->toBeTrue();

    $organization->members()->detach($user->id);

    expect($organizationChannel($user->fresh(), $organization->id))->toBeFalse();
});
