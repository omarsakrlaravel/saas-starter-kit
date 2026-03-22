<?php

use Illuminate\Support\Facades\File;

test('checkout session is configured to reuse saved payment methods', function () {
    $checkoutComponent = File::get(app_path('Livewire/Billing/Checkout.php'));

    expect($checkoutComponent)
        ->toContain('$this->normalizeCheckoutRedisplayablePaymentMethods($user)')
        ->toContain("'payment_method_collection' => 'if_required'")
        ->and($checkoutComponent)->toContain("'saved_payment_method_options'")
        ->and($checkoutComponent)->toContain("'allow_redisplay_filters' => ['always', 'limited', 'unspecified']");
});
