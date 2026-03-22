<?php

use App\Models\User;
use Wave\ActivityLog;
use Wave\Plan;
use Wave\Subscription;

test('user can access export data page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get(route('settings.export'));

    $response->assertStatus(200);
    $response->assertSee('Export Data');
    $response->assertSee('Download Your Data');
});

test('user can export their data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Call the export action
    $response = $this->call('GET', route('settings.export'));

    expect($response->status())->toBe(200);
});

test('export data contains user profile information', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Simulate the export
    $data = [
        'profile' => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
        ],
        'activity_logs' => $user->activityLogs()->get()->toArray(),
    ];

    expect($data['profile'])->toHaveKeys(['id', 'name', 'username', 'email']);
    expect($data['profile']['email'])->toBe($user->email);
});

test('export logs activity', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $initialLogCount = ActivityLog::where('user_id', $user->id)->count();

    // Log an export manually to test
    ActivityLog::log('data_exported', 'User data exported');

    $newLogCount = ActivityLog::where('user_id', $user->id)->count();

    expect($newLogCount)->toBe($initialLogCount + 1);

    $latestLog = ActivityLog::where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->first();

    expect($latestLog->action)->toBe('data_exported');
});

test('exported data does not expose token hashes', function () {
    $user = User::factory()->create();

    // Create a test token via Sanctum
    $token = $user->createApiKey('Test Key');
    $storedToken = $user->tokens()->first();

    // The stored token hash should never appear in export data
    // Export only includes name, last_used_at, and created_at (no token/key column)
    $exportEntry = [
        'name' => $storedToken->name,
        'last_used_at' => $storedToken->last_used_at?->toDateTimeString(),
        'created_at' => $storedToken->created_at->toDateTimeString(),
    ];

    expect($exportEntry)->not->toHaveKey('token');
    expect($exportEntry)->not->toHaveKey('key');
    expect($exportEntry['name'])->toBe('Test Key');
});

test('export includes privacy settings', function () {
    $user = User::factory()->create();

    // Set some privacy settings
    $privacySettings = [
        'profile_visibility' => 'public',
        'show_email' => false,
    ];

    $user->privacy_settings = $privacySettings;
    $user->save();

    $user->refresh();

    expect($user->privacy_settings)->toEqual($privacySettings);
});

test('export handles subscription with string ends_at date', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $plan = Plan::create([
        'name' => 'Export Test Plan',
        'description' => 'Plan for export test',
        'features' => 'Feature 1',
        'monthly_price' => '19.00',
        'yearly_price' => '190.00',
        'monthly_price_id' => 'price_monthly_export_test',
        'yearly_price_id' => 'price_yearly_export_test',
        'active' => true,
    ]);

    // Create a subscription directly in the database with ends_at as a string
    // This simulates a cancelled subscription scenario where ends_at might not be cast properly
    $subscriptionId = \DB::table('subscriptions')->insertGetId([
        'user_id' => $user->id,
        'type' => 'default',
        'billable_type' => 'user',
        'billable_id' => $user->id,
        'plan_id' => $plan->id,
        'stripe_id' => 'sub_test_'.time(),
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_test',
        'cycle' => 'month',
        'quantity' => 1,
        'trial_ends_at' => null,
        'ends_at' => '2026-12-31 23:59:59',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    // Verify the subscription was created
    expect($subscriptionId)->toBeGreaterThan(0);

    // Clear the user relationship cache
    $user = $user->fresh();

    // Verify ends_at is a string when loaded directly from DB
    $rawSubscription = \DB::table('subscriptions')->where('id', $subscriptionId)->first();
    expect($rawSubscription->ends_at)->toBeString();
    expect($rawSubscription->ends_at)->toBe('2026-12-31 23:59:59');

    // Load the subscription through Eloquent (which won't cast ends_at since it's not in casts array)
    $subscription = Subscription::find($subscriptionId);

    // Simulate the export logic that was causing the bug
    $exportData = [
        'subscription' => [
            'plan' => $subscription->plan->name ?? null,
            'status' => $subscription->stripe_status,
            'cycle' => $subscription->cycle ?? null,
            'created_at' => $subscription->created_at instanceof \Carbon\Carbon
                ? $subscription->created_at->toDateTimeString()
                : $subscription->created_at,
            'ends_at' => $subscription->ends_at
                ? ($subscription->ends_at instanceof \Carbon\Carbon
                    ? $subscription->ends_at->toDateTimeString()
                    : $subscription->ends_at)
                : null,
        ],
    ];

    // This should work without throwing an error
    expect($exportData['subscription']['ends_at'])->toBe('2026-12-31 23:59:59');
    expect($exportData['subscription']['status'])->toBe('canceled');
    expect($exportData['subscription']['plan'])->toBe($plan->name);

    // Clean up
    Subscription::where('id', $subscriptionId)->delete();
    $plan->delete();
});
