<?php

use App\Models\User;
use App\Notifications\SubscriptionCancelled;
use Illuminate\Support\Facades\Notification;

it('sends subscription cancelled notification via mail', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new SubscriptionCancelled());

    Notification::assertSentTo($user, SubscriptionCancelled::class);
});

it('links to subscription settings', function () {
    $user = User::factory()->create();

    $notification = new SubscriptionCancelled();
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Your subscription has been cancelled')
        ->and($mail->actionText)->toBe('View Plans')
        ->and($mail->actionUrl)->toBe(route('settings.subscription'));
});

it('includes the user name in the greeting', function () {
    $user = User::factory()->create(['name' => 'John Smith']);

    $notification = new SubscriptionCancelled();
    $mail = $notification->toMail($user);

    expect($mail->greeting)->toBe('Hello John Smith!');
});

it('uses only the mail channel', function () {
    $user = User::factory()->create();

    $notification = new SubscriptionCancelled();

    expect($notification->via($user))->toBe(['mail']);
});
