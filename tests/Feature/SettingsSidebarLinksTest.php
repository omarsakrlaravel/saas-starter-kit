<?php

use Illuminate\Support\Facades\File;

test('settings sidebar does not include payment methods billing link', function () {
    $viewPath = resource_path('themes/anchor/components/app/settings-layout.blade.php');
    $viewContents = File::get($viewPath);

    expect($viewContents)->not->toContain("route('settings.subscription.payment-methods')")
        ->and($viewContents)->not->toContain('Payment Methods');
});
