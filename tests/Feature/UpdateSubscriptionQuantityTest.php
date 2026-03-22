<?php

use App\Actions\Billing\Stripe\UpdateSubscriptionQuantity;
use App\Models\Subscription;

test('seat increase uses payment failure guard before applying quantity change', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 2;
    $subscription->billable_type = 'organization';
    $subscription->stripe_id = null;
    $subscription->shouldReceive('errorIfPaymentFails')->once()->andReturnSelf();
    $subscription->shouldReceive('alwaysInvoice')->once()->andReturnSelf();
    $subscription->shouldReceive('updateQuantity')->once()->with(5)->andReturnSelf();
    $subscription->shouldNotReceive('noProrate');

    $paymentUrl = app(UpdateSubscriptionQuantity::class)($subscription, 3);

    expect($paymentUrl)->toBeNull();
});

test('seat decrease updates quantity without proration', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 5;
    $subscription->billable_type = 'organization';
    $subscription->stripe_id = null;
    $subscription->shouldReceive('noProrate')->once()->andReturnSelf();
    $subscription->shouldReceive('updateQuantity')->once()->with(3)->andReturnSelf();
    $subscription->shouldNotReceive('errorIfPaymentFails');
    $subscription->shouldNotReceive('alwaysInvoice');

    $paymentUrl = app(UpdateSubscriptionQuantity::class)($subscription, -2);

    expect($paymentUrl)->toBeNull();
});

test('seat updates are rejected for non organization subscriptions', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 2;
    $subscription->billable_type = 'user';

    expect(fn () => app(UpdateSubscriptionQuantity::class)($subscription, 1))
        ->toThrow(RuntimeException::class, 'Seat updates are only available for organization subscriptions.');
});

test('seat updates must keep at least one seat', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 1;
    $subscription->billable_type = 'organization';

    expect(fn () => app(UpdateSubscriptionQuantity::class)($subscription, -1))
        ->toThrow(RuntimeException::class, 'Subscription must have at least 1 seat.');
});
