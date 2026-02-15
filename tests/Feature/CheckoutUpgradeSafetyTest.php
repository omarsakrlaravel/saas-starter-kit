<?php

use App\Models\User;
use Wave\Http\Livewire\Billing\Checkout;
use Wave\Plan;

test('immediate upgrade uses error-if-incomplete payment behavior', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = new Plan([
        'id' => 123,
        'name' => 'Pro',
    ]);

    $subscription = Mockery::mock();
    $subscription->shouldReceive('errorIfPaymentFails')->once()->andReturnSelf();
    $subscription->shouldReceive('swapAndInvoice')->once()->with('price_pro_yearly')->andThrow(new RuntimeException('Payment failed'));
    $subscription->shouldNotReceive('update');
    $subscription->shouldNotReceive('clearBillableCache');

    $component = Mockery::mock(Checkout::class)->makePartial();
    $component->shouldAllowMockingProtectedMethods();
    $component->shouldReceive('ensureReusableDefaultPaymentMethod')
        ->once()
        ->with($user)
        ->andReturn(true);

    $method = new ReflectionMethod(Checkout::class, 'applyImmediateUpgrade');
    $method->setAccessible(true);

    $result = $method->invoke($component, $subscription, $plan, 'price_pro_yearly', 'year');

    expect($result)->toBeNull();
});

test('default payment method check passes when customer already has one', function () {
    $user = Mockery::mock();
    $defaultPaymentMethod = new stdClass();

    $user->shouldReceive('hasStripeId')->once()->andReturn(true);
    $user->shouldReceive('defaultPaymentMethod')->once()->andReturn($defaultPaymentMethod);

    $component = app(Checkout::class);
    $method = new ReflectionMethod(Checkout::class, 'ensureReusableDefaultPaymentMethod');
    $method->setAccessible(true);

    $result = $method->invoke($component, $user);

    expect($result)->toBeTrue();
});
