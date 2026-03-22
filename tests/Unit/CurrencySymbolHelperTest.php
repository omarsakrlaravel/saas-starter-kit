<?php

it('returns dollar sign for usd', function () {
    expect(currencySymbol('usd'))->toBe('$');
});

it('returns euro sign for eur', function () {
    expect(currencySymbol('eur'))->toBe("\u{20AC}");
});

it('returns pound sign for gbp', function () {
    expect(currencySymbol('gbp'))->toBe("\u{00A3}");
});

it('returns yen sign for jpy', function () {
    expect(currencySymbol('jpy'))->toBe("\u{00A5}");
});

it('is case insensitive', function () {
    expect(currencySymbol('USD'))->toBe('$');
    expect(currencySymbol('Eur'))->toBe("\u{20AC}");
});

it('falls back to uppercase code for unknown currencies', function () {
    expect(currencySymbol('xyz'))->toBe('XYZ');
    expect(currencySymbol('krw'))->toBe('KRW');
});

it('defaults to usd when null', function () {
    expect(currencySymbol(null))->toBe('$');
    expect(currencySymbol())->toBe('$');
});

it('trims whitespace', function () {
    expect(currencySymbol(' usd '))->toBe('$');
});
