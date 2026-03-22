# Unified Currency Support

**Date:** 2026-03-22
**Status:** Approved

## Problem

Currency handling is inconsistent across the codebase:

1. Plans store currency as **symbols** (`$`, `EUR`) while Stripe and all other models use **ISO codes** (`usd`, `eur`)
2. Most Filament resources and widgets hardcode `$` when displaying monetary amounts, ignoring the stored `currency` column
3. The `Coupon::discountDisplay()` method computes currency but then hardcodes `$` anyway (line 70)
4. Factories already populate `currency` correctly (no changes needed there)

## Decisions

- **ISO codes everywhere** -- store 3-letter ISO currency codes (e.g., `usd`, `eur`, `gbp`, `jpy`), matching Stripe's format
- **Simple helper for admin display** -- a `currencySymbol()` function with a lookup map for common currencies, falling back to uppercase code
- **Keep Cashier's `formatAmount()` for customer-facing code** -- already in use in the User model
- **No aggregation changes** -- widgets continue to sum all records; just fix the display symbol. Multi-currency aggregation deferred until needed.

## Changes

### 1. Add `currencySymbol()` helper

**File:** `app/Helpers/globals.php`

Add a new global function:

```php
if (! function_exists('currencySymbol')) {
    function currencySymbol(?string $code = null): string
    {
        $symbols = [
            'usd' => '$',
            'eur' => "\u{20AC}",
            'gbp' => "\u{00A3}",
            'jpy' => "\u{00A5}",
            'cad' => 'CA$',
            'aud' => 'A$',
            'chf' => 'CHF',
            'inr' => "\u{20B9}",
            'brl' => 'R$',
            'mxn' => 'MX$',
        ];

        $normalized = strtolower(trim($code ?? 'usd'));

        return $symbols[$normalized] ?? strtoupper($normalized);
    }
}
```

### 2. Migrate Plans currency from symbols to ISO codes

**New migration:** `add_currency_iso_codes_to_plans_table`

- Convert existing values: `$` -> `usd`, `EUR`/`€` -> `eur`, `GBP`/`£` -> `gbp`, `JPY`/`¥` -> `jpy`
- Change column default from `$` to `usd`

**File:** `app/Filament/Resources/Plans/PlanResource.php` (lines 95-102)

Change the currency Select from symbols to ISO codes:

```php
Select::make('currency')
    ->default('usd')
    ->options([
        'usd' => 'USD ($)',
        'eur' => 'EUR (\u{20AC})',
        'gbp' => 'GBP (\u{00A3})',
        'jpy' => 'JPY (\u{00A5})',
    ]),
```

### 3. Fix Coupon `discountDisplay()`

**File:** `app/Models/Coupon.php` (line 70)

Replace `'$'.$amount.' off'` with `currencySymbol($this->currency).$amount.' off'`.

### 4. Fix Filament resources -- replace hardcoded `$`

All instances of `'$'.number_format(...)` should use `currencySymbol($record->currency)` instead.

#### InvoiceResource (`app/Filament/Resources/Invoices/InvoiceResource.php`)

| Line | Current | Fix |
|------|---------|-----|
| 57 | `'$'.number_format($record->amount_due / 100, 2)` | `currencySymbol($record->currency).number_format($record->amount_due / 100, 2)` |
| 60 | `'$'.number_format($record->amount_paid / 100, 2)` | `currencySymbol($record->currency).number_format($record->amount_paid / 100, 2)` |
| 62 | `'$'.number_format($record->total / 100, 2)` | `currencySymbol($record->currency).number_format($record->total / 100, 2)` |
| 113 | `'$'.number_format($state / 100, 2)` in table | Use `fn (int $state, Invoice $record): string => currencySymbol($record->currency).number_format($state / 100, 2)` |
| 156 | `->label('Refund Amount ($)')` | `->label('Refund Amount')` |

#### TransactionResource (`app/Filament/Resources/Transactions/TransactionResource.php`)

| Line | Current | Fix |
|------|---------|-----|
| 52 | `'$'.number_format($record->amount / 100, 2)` | `currencySymbol($record->currency).number_format(...)` |
| 57 | `'$'.number_format($record->refunded_amount / 100, 2)` | `currencySymbol($record->currency).number_format(...)` |
| 105 | table column `'$'.number_format(...)` | Use record's currency |
| 145 | modal description `'$'.number_format(...)` | `currencySymbol($record->currency).number_format(...)` |
| 183 | `->label('Refund Amount ($)')` | `->label('Refund Amount')` |
| 163 | notification body `'$'.number_format(...)` | `currencySymbol($record->currency).number_format(...)` |
| 180 | modal description hardcoded `$` | Use record's currency |
| 189 | TextInput prefix `'$'` | `currencySymbol($record->currency)` |
| 190 | helper text `'$'.number_format(...)` | Use record's currency |
| 212 | notification body `'$'.number_format(...)` | `currencySymbol($record->currency)` with the refund amount |

#### RefundResource (`app/Filament/Resources/Refunds/RefundResource.php`)

| Line | Fix |
|------|-----|
| 52 | Use `currencySymbol($record->currency)` |
| 55 | Use `currencySymbol($record->currency)` |
| 92 | Use record's currency in table formatStateUsing |
| 95 | Use record's currency in table formatStateUsing |

#### CouponResource (`app/Filament/Resources/Coupons/CouponResource.php`)

| Line | Fix |
|------|-----|
| 132 | `'$'.number_format($record->amount_off / 100, 2).' off'` -> `currencySymbol($record->currency).number_format($record->amount_off / 100, 2).' off'` |

#### SubscriptionResource (`app/Filament/Resources/Subscriptions/SubscriptionResource.php`)

| Line | Fix |
|------|-----|
| 524-527 | Refund notification: get currency from `$latestInvoice->currency` and use `currencySymbol()` |

### 5. Fix Relation Managers

#### Organizations/InvoicesRelationManager (line 26)

Replace `'$'.number_format($state / 100, 2)` with `currencySymbol($record->currency).number_format($state / 100, 2)` using full closure with Invoice type hint.

#### Subscriptions/InvoicesRelationManager (line 28)

Same fix as above.

#### Users/InvoicesRelationManager (line 25)

Same fix as above.

#### Subscriptions/TransactionsRelationManager (line 26)

Replace hardcoded `$` with `currencySymbol($record->currency)` using Transaction type hint.

#### Coupons/RedemptionsRelationManager (line 21)

Replace hardcoded `$` with currency from the parent coupon: `currencySymbol($record->coupon?->currency)`.

### 6. Fix Widgets

#### RecentTransactionsWidget (line 37)

Replace `'$'.number_format($state / 100, 2)` with `currencySymbol($record->currency).number_format($state / 100, 2)` using full closure with Transaction type hint.

#### StatsOverviewWidget (line 49)

Replace `'$'.number_format($mrr, 2)` with `currencySymbol('usd').number_format($mrr, 2)`. Uses `usd` as default since MRR aggregates across plans. This is acceptable because multi-currency aggregation is deferred.

### 7. Fix Livewire components

#### CheckoutReview (`app/Livewire/Billing/CheckoutReview.php`)

Lines 134-138 compute currency but hardcode `$`:

```php
// Current (broken):
return '$'.$amount.' '.$currency.' off'...;

// Fix:
return currencySymbol($coupon->currency).$amount.' off'...;
```

### 8. Frontend Blade templates

All Blade files using `{{ $plan->currency }}` must switch to `{{ currencySymbol($plan->currency) }}`:

**File:** `resources/views/components/marketing/sections/pricing.blade.php` (line 63)

**File:** `resources/views/pages/settings/subscription.blade.php` (lines 74, 81)

**File:** `resources/views/wave/livewire/billing/checkout-review.blade.php` (lines 38, 42, 63, 154, 208, 215, 223 -- 7 occurrences)

**File:** `resources/views/wave/livewire/billing/checkout.blade.php` (line 84)

**File:** `resources/views/pages/settings/organization.blade.php` (lines 720, 732 -- Alpine data binding and display)

### 9. Test updates

Write a unit test for the `currencySymbol()` helper covering:
- Known currencies return correct symbols
- Unknown currencies fall back to uppercase code
- Null input defaults to `$` (usd)

Update any existing tests that assert hardcoded `$` in formatted amounts to use `currencySymbol()` instead, or assert the correct symbol for the factory's default `usd` currency.

## Out of Scope

- Adding `currency` column to subscriptions table (can derive from plan)
- Adding `currency` column to promotion_codes table (already has `minimum_amount_currency`; discount currency comes from coupon)
- Multi-currency revenue aggregation in widgets
- Locale-aware formatting in admin (keeps simple symbol prefix)
- Currency conversion
- Zero-decimal currency handling (JPY amounts stored in whole units vs cents) -- existing issue, not introduced by this change

## File Summary

| File | Change Type |
|------|-------------|
| `app/Helpers/globals.php` | Add `currencySymbol()` function |
| `database/migrations/xxxx_update_plans_currency_to_iso_codes.php` | New migration |
| `app/Filament/Resources/Plans/PlanResource.php` | Update currency dropdown |
| `app/Models/Coupon.php` | Fix `discountDisplay()` |
| `app/Filament/Resources/Invoices/InvoiceResource.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Transactions/TransactionResource.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Refunds/RefundResource.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Coupons/CouponResource.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Subscriptions/SubscriptionResource.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Organizations/RelationManagers/InvoicesRelationManager.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Subscriptions/RelationManagers/InvoicesRelationManager.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Subscriptions/RelationManagers/TransactionsRelationManager.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Users/RelationManagers/InvoicesRelationManager.php` | Replace hardcoded `$` |
| `app/Filament/Resources/Coupons/RelationManagers/RedemptionsRelationManager.php` | Replace hardcoded `$` |
| `app/Filament/Widgets/RecentTransactionsWidget.php` | Replace hardcoded `$` |
| `app/Filament/Widgets/StatsOverviewWidget.php` | Replace hardcoded `$` |
| `app/Livewire/Billing/CheckoutReview.php` | Fix hardcoded `$` in coupon display |
| `resources/views/components/marketing/sections/pricing.blade.php` | Use `currencySymbol()` |
| `resources/views/pages/settings/subscription.blade.php` | Use `currencySymbol()` |
| `resources/views/pages/settings/organization.blade.php` | Use `currencySymbol()` |
| `resources/views/wave/livewire/billing/checkout-review.blade.php` | Use `currencySymbol()` (7 occurrences) |
| `resources/views/wave/livewire/billing/checkout.blade.php` | Use `currencySymbol()` |
| `tests/Unit/CurrencySymbolHelperTest.php` | New test |
