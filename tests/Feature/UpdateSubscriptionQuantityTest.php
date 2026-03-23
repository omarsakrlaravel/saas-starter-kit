<?php

use App\Actions\Billing\UpdateSeatQuantity;
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

    $result = app(UpdateSeatQuantity::class)->execute($subscription, 3);

    expect($result->success)->toBeTrue()
        ->and($result->paymentUrl)->toBeNull();
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

    $result = app(UpdateSeatQuantity::class)->execute($subscription, -2);

    expect($result->success)->toBeTrue()
        ->and($result->paymentUrl)->toBeNull();
});

test('seat updates are rejected for non organization subscriptions', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 2;
    $subscription->billable_type = 'user';

    $result = app(UpdateSeatQuantity::class)->execute($subscription, 1);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('organization');
});

test('seat updates must keep at least one seat', function () {
    $subscription = \Mockery::mock(Subscription::class)->makePartial();
    $subscription->quantity = 1;
    $subscription->billable_type = 'organization';

    $result = app(UpdateSeatQuantity::class)->execute($subscription, -1);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('at least 1');
});
