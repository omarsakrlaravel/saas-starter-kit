<?php

use Illuminate\Support\Facades\File;

test('settings sidebar includes payment methods billing link', function () {
    $viewPath = resource_path('themes/anchor/components/app/settings-layout.blade.php');
    $viewContents = File::get($viewPath);

    expect($viewContents)->toContain("route('settings.subscription.payment-methods')")
        ->and($viewContents)->toContain('Payment Methods');
});
