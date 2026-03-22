<?php

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;

it('sends welcome notification via mail', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new WelcomeNotification());

    Notification::assertSentTo($user, WelcomeNotification::class);
});

it('links to the dashboard', function () {
    $user = User::factory()->create();

    $notification = new WelcomeNotification();
    $mail = $notification->toMail($user);

    expect($mail->actionText)->toBe('Go to Dashboard')
        ->and($mail->actionUrl)->toBe(url('/dashboard'));
});

it('includes the user name in the greeting', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $notification = new WelcomeNotification();
    $mail = $notification->toMail($user);

    expect($mail->greeting)->toBe('Hello Jane Doe!');
});

it('uses only the mail channel', function () {
    $user = User::factory()->create();

    $notification = new WelcomeNotification();

    expect($notification->via($user))->toBe(['mail']);
});
