<?php

use Illuminate\Support\Facades\File;

test('adding a card marks it redisplayable for future checkout sessions', function () {
    $componentContents = File::get(base_path('wave/src/Http/Livewire/Billing/PaymentMethods.php'));

    expect($componentContents)
        ->toContain('$this->markPaymentMethodForCheckoutRedisplay($user, $paymentMethodId);')
        ->and($componentContents)->toContain("'allow_redisplay' => 'always'");
});
