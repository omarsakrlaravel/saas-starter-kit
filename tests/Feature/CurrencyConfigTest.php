<?php

it('reads default currency from saas config', function () {
    expect(config('saas.currency'))->toBe('usd');
    expect(currencySymbol())->toBe('$');
});

it('respects config override for default currency', function () {
    config(['saas.currency' => 'eur']);
    expect(currencySymbol())->toBe("\u{20AC}");
    expect(currencySymbol(null))->toBe("\u{20AC}");
});

it('still allows explicit currency override', function () {
    config(['saas.currency' => 'eur']);
    expect(currencySymbol('gbp'))->toBe("\u{00A3}");
});
