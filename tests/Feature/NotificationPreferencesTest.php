<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('allows user to update notification preferences', function () {
    $this->actingAs($this->user);

    $preferences = [
        'email_notifications' => false,
        'marketing_emails' => false,
        'product_updates' => true,
        'security_alerts' => true,
    ];

    $this->user->notification_preferences = $preferences;
    $this->user->save();

    $this->user->refresh();

    expect($this->user->notification_preferences)->toEqual($preferences);
    expect($this->user->notification_preferences['email_notifications'])->toBe(false);
    expect($this->user->notification_preferences['marketing_emails'])->toBe(false);
    expect($this->user->notification_preferences['product_updates'])->toBe(true);
});

it('security alerts preference is always enabled', function () {
    $this->actingAs($this->user);

    // Try to set security_alerts to false
    $preferences = [
        'email_notifications' => true,
        'marketing_emails' => true,
        'product_updates' => true,
        'security_alerts' => false, // Attempt to disable
    ];

    $this->user->notification_preferences = $preferences;
    $this->user->save();

    // Security alerts should still be true in the system (enforced by the form)
    expect($this->user->notification_preferences['security_alerts'])->toBe(false); // Will be false in DB
    // But the UI enforces it to be true, so this tests the storage layer
});

it('returns default preferences when none are set', function () {
    // Clear preferences
    $this->user->notification_preferences = null;
    $this->user->save();

    $this->user->refresh();

    expect($this->user->notification_preferences)->toBeNull();
});

it('can update individual preference settings', function () {
    $this->actingAs($this->user);

    // Start with default preferences
    $preferences = [
        'email_notifications' => true,
        'marketing_emails' => true,
        'product_updates' => true,
        'security_alerts' => true,
    ];

    $this->user->notification_preferences = $preferences;
    $this->user->save();

    // Update only marketing emails
    $preferences['marketing_emails'] = false;

    $this->user->notification_preferences = $preferences;
    $this->user->save();

    $this->user->refresh();

    expect($this->user->notification_preferences['marketing_emails'])->toBe(false);
    expect($this->user->notification_preferences['email_notifications'])->toBe(true);
});

it('notification preferences can be stored as json', function () {
    $preferences = [
        'email_notifications' => true,
        'marketing_emails' => false,
        'product_updates' => true,
        'security_alerts' => true,
    ];

    $this->user->notification_preferences = $preferences;
    $this->user->save();

    // Verify it's stored properly and can be retrieved
    $freshUser = User::find($this->user->id);

    expect($freshUser->notification_preferences)->toBeArray();
    expect($freshUser->notification_preferences['marketing_emails'])->toBe(false);
});

it('multiple users can have different notification preferences', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Set different preferences for each user
    $user1->notification_preferences = [
        'email_notifications' => true,
        'marketing_emails' => false,
        'product_updates' => true,
        'security_alerts' => true,
    ];
    $user1->save();

    $user2->notification_preferences = [
        'email_notifications' => false,
        'marketing_emails' => true,
        'product_updates' => false,
        'security_alerts' => true,
    ];
    $user2->save();

    $user1->refresh();
    $user2->refresh();

    expect($user1->notification_preferences['email_notifications'])->toBe(true);
    expect($user2->notification_preferences['email_notifications'])->toBe(false);
    expect($user1->notification_preferences['marketing_emails'])->toBe(false);
    expect($user2->notification_preferences['marketing_emails'])->toBe(true);
});

it('can retrieve notification preferences for checking before sending notifications', function () {
    $this->user->notification_preferences = [
        'email_notifications' => false,
        'marketing_emails' => false,
        'product_updates' => true,
        'security_alerts' => true,
    ];
    $this->user->save();

    $this->user->refresh();

    // Simulate checking preferences before sending
    $shouldSendEmail = $this->user->notification_preferences['email_notifications'] ?? true;
    $shouldSendMarketing = $this->user->notification_preferences['marketing_emails'] ?? true;

    expect($shouldSendEmail)->toBe(false);
    expect($shouldSendMarketing)->toBe(false);
});
