<?php

use App\Models\User;
use Wave\Http\Livewire\Billing\Checkout;
use Wave\Plan;

test('upgrade falls back to stripe checkout when no saved payment method', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = new Plan([
        'id' => 123,
        'name' => 'Pro',
    ]);

    $subscription = Mockery::mock();

    $component = Mockery::mock(Checkout::class)->makePartial();
    $component->shouldAllowMockingProtectedMethods();
    $component->shouldReceive('trySwapWithSavedMethod')
        ->once()
        ->andReturn(false);
    $component->shouldReceive('redirectToStripeCheckoutForSwap')
        ->once()
        ->with($user, $plan, 'price_pro_yearly', 'year')
        ->andReturn(null);

    $method = new ReflectionMethod(Checkout::class, 'applyImmediateUpgrade');
    $method->setAccessible(true);

    $result = $method->invoke($component, $subscription, $plan, 'price_pro_yearly', 'year');

    expect($result)->toBeNull();
});

test('upgrade succeeds immediately when saved payment method works', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $plan = new Plan([
        'id' => 123,
        'name' => 'Pro',
    ]);

    $subscription = Mockery::mock();

    $component = Mockery::mock(Checkout::class)->makePartial();
    $component->shouldAllowMockingProtectedMethods();
    $component->shouldReceive('trySwapWithSavedMethod')
        ->once()
        ->andReturn(true);
    $component->shouldNotReceive('redirectToStripeCheckoutForSwap');

    $method = new ReflectionMethod(Checkout::class, 'applyImmediateUpgrade');
    $method->setAccessible(true);

    $result = $method->invoke($component, $subscription, $plan, 'price_pro_yearly', 'year');

    // Returns a redirect when swap succeeds
    expect($result)->not->toBeNull();
});
