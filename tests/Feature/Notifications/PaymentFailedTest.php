<?php

use App\Models\User;
use App\Notifications\PaymentFailed;
use Illuminate\Support\Facades\Notification;

it('sends payment failed notification via mail', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->notify(new PaymentFailed());

    Notification::assertSentTo($user, PaymentFailed::class);
});

it('includes invoice url action when provided', function () {
    $user = User::factory()->create();
    $invoiceUrl = 'https://invoice.stripe.com/i/test_123';

    $notification = new PaymentFailed($invoiceUrl);
    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Action required: payment failed')
        ->and($mail->actionText)->toBe('Update Payment')
        ->and($mail->actionUrl)->toBe($invoiceUrl);
});

it('links to subscription settings when no invoice url provided', function () {
    $user = User::factory()->create();

    $notification = new PaymentFailed();
    $mail = $notification->toMail($user);

    expect($mail->actionText)->toBe('Manage Subscription')
        ->and($mail->actionUrl)->toBe(route('settings.subscription'));
});

it('includes the user name in the greeting', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $notification = new PaymentFailed();
    $mail = $notification->toMail($user);

    expect($mail->greeting)->toBe('Hello Jane Doe!');
});

it('uses only the mail channel', function () {
    $user = User::factory()->create();

    $notification = new PaymentFailed();

    expect($notification->via($user))->toBe(['mail']);
});
