# Unified Currency Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Standardize all currency handling to ISO codes and replace every hardcoded `$` symbol with the dynamic `currencySymbol()` helper.

**Architecture:** Add a global `currencySymbol()` helper that maps ISO codes to display symbols. Migrate Plan records from symbols to ISO codes. Update all Filament resources, relation managers, widgets, Livewire components, and Blade templates to use the helper.

**Tech Stack:** Laravel 12, Filament v4, Livewire 3, Pest 4

**Spec:** `docs/superpowers/specs/2026-03-22-unified-currency-support-design.md`

---

### Task 1: Add `currencySymbol()` helper with tests

**Files:**
- Modify: `app/Helpers/globals.php`
- Create: `tests/Unit/CurrencySymbolHelperTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

// tests/Unit/CurrencySymbolHelperTest.php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CurrencySymbolHelper`
Expected: FAIL -- `currencySymbol` function not defined

- [ ] **Step 3: Add the helper function**

In `app/Helpers/globals.php`, add at the end of the file (before closing, if any):

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CurrencySymbolHelper`
Expected: All 8 tests PASS

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Helpers/globals.php tests/Unit/CurrencySymbolHelperTest.php
git commit -m "feat: add currencySymbol() helper with tests"
```

---

### Task 2: Migrate Plans currency column from symbols to ISO codes

**Files:**
- Create: `database/migrations/xxxx_update_plans_currency_to_iso_codes.php`
- Modify: `app/Filament/Resources/Plans/PlanResource.php`

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration update_plans_currency_to_iso_codes --table=plans --no-interaction`

Then write its content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Convert existing symbol values to ISO codes
        $mapping = [
            '$' => 'usd',
            "\u{20AC}" => 'eur',
            'EUR' => 'eur',
            "\u{00A3}" => 'gbp',
            'GBP' => 'gbp',
            "\u{00A5}" => 'jpy',
            'JPY' => 'jpy',
        ];

        foreach ($mapping as $symbol => $code) {
            DB::table('plans')->where('currency', $symbol)->update(['currency' => $code]);
        }

        // Change column default to 'usd'
        Schema::table('plans', function (Blueprint $table) {
            $table->string('currency', 3)->default('usd')->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('currency', 3)->default('$')->change();
        });

        DB::table('plans')->where('currency', 'usd')->update(['currency' => '$']);
        DB::table('plans')->where('currency', 'eur')->update(['currency' => "\u{20AC}"]);
        DB::table('plans')->where('currency', 'gbp')->update(['currency' => "\u{00A3}"]);
        DB::table('plans')->where('currency', 'jpy')->update(['currency' => "\u{00A5}"]);
    }
};
```

- [ ] **Step 2: Update PlanResource currency dropdown**

In `app/Filament/Resources/Plans/PlanResource.php`, replace lines 95-102:

Old:
```php
Select::make('currency')
    ->default('$')
    ->options([
        '$' => '$',
        '€' => '€',
        '£' => '£',
        '¥' => '¥',
    ]),
```

New:
```php
Select::make('currency')
    ->default('usd')
    ->options([
        'usd' => 'USD ($)',
        'eur' => "EUR (\u{20AC})",
        'gbp' => "GBP (\u{00A3})",
        'jpy' => "JPY (\u{00A5})",
    ]),
```

- [ ] **Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration runs successfully

- [ ] **Step 4: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*update_plans_currency_to_iso_codes* app/Filament/Resources/Plans/PlanResource.php
git commit -m "feat: migrate plans currency from symbols to ISO codes"
```

---

### Task 3: Fix Coupon model and CouponResource

**Files:**
- Modify: `app/Models/Coupon.php`
- Modify: `app/Filament/Resources/Coupons/CouponResource.php`

- [ ] **Step 1: Fix `Coupon::discountDisplay()`**

In `app/Models/Coupon.php`, replace lines 67-70:

Old:
```php
            $currency = strtoupper($this->currency ?? 'usd');
            $amount = number_format($this->amount_off / 100, 2);

            return '$'.$amount.' off';
```

New (remove the dead `$currency` variable):
```php
            $amount = number_format($this->amount_off / 100, 2);

            return currencySymbol($this->currency).$amount.' off';
```

- [ ] **Step 2: Fix CouponResource table discount column**

In `app/Filament/Resources/Coupons/CouponResource.php`, replace line 132:

Old:
```php
                            return '$'.number_format($record->amount_off / 100, 2).' off';
```

New:
```php
                            return currencySymbol($record->currency).number_format($record->amount_off / 100, 2).' off';
```

- [ ] **Step 3: Run existing coupon tests**

Run: `php artisan test --compact --filter=CouponModel`
Expected: PASS

- [ ] **Step 4: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Coupon.php app/Filament/Resources/Coupons/CouponResource.php
git commit -m "fix: use currencySymbol() in Coupon model and CouponResource"
```

---

### Task 4: Fix InvoiceResource

**Files:**
- Modify: `app/Filament/Resources/Invoices/InvoiceResource.php`

- [ ] **Step 1: Fix form placeholders (lines 57, 60, 62)**

Replace these three Placeholder `->content()` closures:

Line 57 -- old:
```php
->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->amount_due / 100, 2) : '—'),
```
new:
```php
->content(fn (?Invoice $record): string => $record ? currencySymbol($record->currency).number_format($record->amount_due / 100, 2) : '—'),
```

Line 60 -- old:
```php
->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->amount_paid / 100, 2) : '—'),
```
new:
```php
->content(fn (?Invoice $record): string => $record ? currencySymbol($record->currency).number_format($record->amount_paid / 100, 2) : '—'),
```

Line 62 -- old:
```php
->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->total / 100, 2) : '—'),
```
new:
```php
->content(fn (?Invoice $record): string => $record ? currencySymbol($record->currency).number_format($record->total / 100, 2) : '—'),
```

- [ ] **Step 2: Fix table column (line 113)**

Old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
```
New:
```php
->formatStateUsing(fn (int $state, Invoice $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
```

- [ ] **Step 3: Fix refund action label (line 156)**

Old:
```php
->label('Refund Amount ($)')
```
New:
```php
->label('Refund Amount')
```

- [ ] **Step 4: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Invoices/InvoiceResource.php
git commit -m "fix: use currencySymbol() in InvoiceResource"
```

---

### Task 5: Fix TransactionResource

**Files:**
- Modify: `app/Filament/Resources/Transactions/TransactionResource.php`

- [ ] **Step 1: Fix form placeholders (lines 52, 57)**

Line 52 -- old:
```php
->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->amount / 100, 2) : '—'),
```
new:
```php
->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->amount / 100, 2) : '—'),
```

Line 57 -- old:
```php
->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->refunded_amount / 100, 2) : '—'),
```
new:
```php
->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->refunded_amount / 100, 2) : '—'),
```

- [ ] **Step 2: Fix table column (line 105)**

Old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
```
New:
```php
->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
```

- [ ] **Step 3: Fix refund_full action (lines 145, 163)**

Line 145 modal description -- old:
```php
->modalDescription(fn (Transaction $record): string => 'Are you sure you want to refund $'.number_format($record->amount / 100, 2).'? This cannot be undone.')
```
new:
```php
->modalDescription(fn (Transaction $record): string => 'Are you sure you want to refund '.currencySymbol($record->currency).number_format($record->amount / 100, 2).'? This cannot be undone.')
```

Line 163 notification body -- old:
```php
->body('$'.number_format($record->amount / 100, 2).' has been refunded.')
```
new:
```php
->body(currencySymbol($record->currency).number_format($record->amount / 100, 2).' has been refunded.')
```

- [ ] **Step 4: Fix refund_partial action (lines 180, 183, 189, 190, 212)**

Line 180 modal description -- old:
```php
->modalDescription(fn (Transaction $record): string => 'Original amount: $'.number_format($record->amount / 100, 2).'. Already refunded: $'.number_format($record->refunded_amount / 100, 2).'.')
```
new:
```php
->modalDescription(fn (Transaction $record): string => 'Original amount: '.currencySymbol($record->currency).number_format($record->amount / 100, 2).'. Already refunded: '.currencySymbol($record->currency).number_format($record->refunded_amount / 100, 2).'.')
```

Line 183 label -- old:
```php
->label('Refund Amount ($)')
```
new:
```php
->label('Refund Amount')
```

Line 189 prefix -- old (inside `->schema(fn (Transaction $record): array => [` closure):
```php
->prefix('$')
```
new:
```php
->prefix(currencySymbol($record->currency))
```

Line 190 helper text -- old:
```php
->helperText('Maximum refundable: $'.number_format(($record->amount - $record->refunded_amount) / 100, 2)),
```
new:
```php
->helperText('Maximum refundable: '.currencySymbol($record->currency).number_format(($record->amount - $record->refunded_amount) / 100, 2)),
```

Line 212 notification body -- old:
```php
->body('$'.number_format($refundAmountCents / 100, 2).' has been refunded.')
```
new (need `$record` in scope -- it's available via `function (Transaction $record, array $data)` on line 192):
```php
->body(currencySymbol($record->currency).number_format($refundAmountCents / 100, 2).' has been refunded.')
```

- [ ] **Step 5: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Transactions/TransactionResource.php
git commit -m "fix: use currencySymbol() in TransactionResource"
```

---

### Task 6: Fix RefundResource

**Files:**
- Modify: `app/Filament/Resources/Refunds/RefundResource.php`

- [ ] **Step 1: Fix form placeholders (lines 52, 55)**

Line 52 -- old:
```php
->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->amount / 100, 2) : '—'),
```
new:
```php
->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->amount / 100, 2) : '—'),
```

Line 55 -- old:
```php
->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->refunded_amount / 100, 2) : '—'),
```
new:
```php
->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->refunded_amount / 100, 2) : '—'),
```

- [ ] **Step 2: Fix table columns (lines 92, 95)**

Line 92 -- old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
```
new:
```php
->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
```

Line 95 -- same pattern, same fix.

- [ ] **Step 3: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Refunds/RefundResource.php
git commit -m "fix: use currencySymbol() in RefundResource"
```

---

### Task 7: Fix SubscriptionResource

**Files:**
- Modify: `app/Filament/Resources/Subscriptions/SubscriptionResource.php`

- [ ] **Step 1: Fix refund notification (lines 524-527)**

Old:
```php
$amountFormatted = number_format($latestInvoice->amount_paid / 100, 2);

Notification::make()
    ->title('Refund of $'.$amountFormatted.' issued for invoice '.$latestInvoice->number.'.')
```
New:
```php
$currency = strtolower((string) ($latestInvoice->currency ?? 'usd'));
$amountFormatted = number_format($latestInvoice->amount_paid / 100, 2);

Notification::make()
    ->title('Refund of '.currencySymbol($currency).$amountFormatted.' issued for invoice '.$latestInvoice->number.'.')
```

Note: `$latestInvoice` here is a Stripe invoice object (from `$invoices->data[0]`), not a local Invoice model. Stripe invoice objects have a `currency` property.

- [ ] **Step 2: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Subscriptions/SubscriptionResource.php
git commit -m "fix: use currencySymbol() in SubscriptionResource refund notification"
```

---

### Task 8: Fix all Relation Managers

**Files:**
- Modify: `app/Filament/Resources/Organizations/RelationManagers/InvoicesRelationManager.php`
- Modify: `app/Filament/Resources/Subscriptions/RelationManagers/InvoicesRelationManager.php`
- Modify: `app/Filament/Resources/Users/RelationManagers/InvoicesRelationManager.php`
- Modify: `app/Filament/Resources/Subscriptions/RelationManagers/TransactionsRelationManager.php`
- Modify: `app/Filament/Resources/Coupons/RelationManagers/RedemptionsRelationManager.php`

- [ ] **Step 1: Fix Organizations/InvoicesRelationManager (line 26)**

Old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
```
New:
```php
->formatStateUsing(fn (int $state, Invoice $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
```

Also add `use App\Models\Invoice;` if not already imported (it is -- line 5).

- [ ] **Step 2: Fix Subscriptions/InvoicesRelationManager (line 28)**

Same fix as step 1. Import `Invoice` is already present (line 5).

- [ ] **Step 3: Fix Users/InvoicesRelationManager (line 25)**

Same fix as step 1. Import `Invoice` is already present (line 5).

- [ ] **Step 4: Fix Subscriptions/TransactionsRelationManager (line 26)**

Add `use App\Models\Transaction;` import at top of file.

Old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
```
New:
```php
->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
```

- [ ] **Step 5: Fix Coupons/RedemptionsRelationManager (line 21)**

Add `use App\Models\CouponRedemption;` import at top of file.

Old:
```php
->formatStateUsing(fn (?int $state): string => '$'.number_format(($state ?? 0) / 100, 2)),
```
New:
```php
->formatStateUsing(fn (?int $state, CouponRedemption $record): string => currencySymbol($record->coupon?->currency).number_format(($state ?? 0) / 100, 2)),
```

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Resources/Organizations/RelationManagers/InvoicesRelationManager.php \
       app/Filament/Resources/Subscriptions/RelationManagers/InvoicesRelationManager.php \
       app/Filament/Resources/Users/RelationManagers/InvoicesRelationManager.php \
       app/Filament/Resources/Subscriptions/RelationManagers/TransactionsRelationManager.php \
       app/Filament/Resources/Coupons/RelationManagers/RedemptionsRelationManager.php
git commit -m "fix: use currencySymbol() in all relation managers"
```

---

### Task 9: Fix Widgets

**Files:**
- Modify: `app/Filament/Widgets/RecentTransactionsWidget.php`
- Modify: `app/Filament/Widgets/StatsOverviewWidget.php`

- [ ] **Step 1: Fix RecentTransactionsWidget (line 37)**

Old:
```php
->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2))
```
New:
```php
->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2))
```

The `Transaction` import is already present (line 5).

- [ ] **Step 2: Fix StatsOverviewWidget (line 49)**

Old:
```php
return Stat::make('MRR', '$'.number_format($mrr, 2))
```
New:
```php
return Stat::make('MRR', currencySymbol('usd').number_format($mrr, 2))
```

- [ ] **Step 3: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Filament/Widgets/RecentTransactionsWidget.php app/Filament/Widgets/StatsOverviewWidget.php
git commit -m "fix: use currencySymbol() in dashboard widgets"
```

---

### Task 10: Fix AppStats command

**Files:**
- Modify: `app/Console/Commands/AppStats.php`

- [ ] **Step 1: Fix hardcoded `$` in revenue display (lines 180-181)**

Old:
```php
$this->components->twoColumnDetail('MRR (Monthly Recurring Revenue)', '$'.number_format($stats['revenue']['mrr'], 2));
$this->components->twoColumnDetail('ARR (Annual Recurring Revenue)', '$'.number_format($stats['revenue']['arr'], 2));
```
New:
```php
$this->components->twoColumnDetail('MRR (Monthly Recurring Revenue)', currencySymbol('usd').number_format($stats['revenue']['mrr'], 2));
$this->components->twoColumnDetail('ARR (Annual Recurring Revenue)', currencySymbol('usd').number_format($stats['revenue']['arr'], 2));
```

- [ ] **Step 2: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/AppStats.php
git commit -m "fix: use currencySymbol() in AppStats command"
```

---

### Task 11: Fix CheckoutReview Livewire component

**Files:**
- Modify: `app/Livewire/Billing/CheckoutReview.php`

- [ ] **Step 1: Fix coupon display (lines 134-138)**

Old:
```php
        if ($coupon->amount_off) {
            $amount = number_format($coupon->amount_off / 100, 2);
            $currency = strtoupper($coupon->currency ?? 'usd');

            return '$'.$amount.' '.$currency.' off'.($coupon->duration === 'once' ? ' (first payment)' : '');
        }
```
New:
```php
        if ($coupon->amount_off) {
            $amount = number_format($coupon->amount_off / 100, 2);

            return currencySymbol($coupon->currency).$amount.' off'.($coupon->duration === 'once' ? ' (first payment)' : '');
        }
```

- [ ] **Step 2: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Billing/CheckoutReview.php
git commit -m "fix: use currencySymbol() in CheckoutReview Livewire component"
```

---

### Task 12: Fix Blade templates

**Files:**
- Modify: `resources/views/components/marketing/sections/pricing.blade.php`
- Modify: `resources/views/pages/settings/subscription.blade.php`
- Modify: `resources/views/wave/livewire/billing/checkout-review.blade.php`
- Modify: `resources/views/wave/livewire/billing/checkout.blade.php`
- Modify: `resources/views/pages/settings/organization.blade.php`

- [ ] **Step 1: Fix pricing.blade.php (line 63)**

Old:
```blade
{{ $plan->currency }}<span x-text="billing == 'Monthly' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span>
```
New:
```blade
{{ currencySymbol($plan->currency) }}<span x-text="billing == 'Monthly' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span>
```

- [ ] **Step 2: Fix subscription.blade.php (lines 74, 81)**

Line 74 -- old:
```blade
{{ $plan->currency }}{{ number_format($totalPrice, 0) }}
```
new:
```blade
{{ currencySymbol($plan->currency) }}{{ number_format($totalPrice, 0) }}
```

Line 81 -- old:
```blade
{{ $plan->currency }}{{ number_format($unitPrice, 0) }}/seat &times; {{ $seats }} seats
```
new:
```blade
{{ currencySymbol($plan->currency) }}{{ number_format($unitPrice, 0) }}/seat &times; {{ $seats }} seats
```

- [ ] **Step 3: Fix checkout-review.blade.php (7 occurrences)**

Replace all 7 instances of `{{ $plan->currency }}` with `{{ currencySymbol($plan->currency) }}` on lines 38, 42, 63, 154, 208, 215, 223.

Use find-and-replace: `{{ $plan->currency }}` -> `{{ currencySymbol($plan->currency) }}`

- [ ] **Step 4: Fix checkout.blade.php (line 84)**

Old:
```blade
{{ $plan->currency }}<span x-text="billing_cycle_selected == 'month' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span>
```
New:
```blade
{{ currencySymbol($plan->currency) }}<span x-text="billing_cycle_selected == 'month' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span>
```

- [ ] **Step 5: Fix organization.blade.php (lines 720, 732)**

Line 720 -- old:
```blade
currency: '{{ $plan->currency }}',
```
new:
```blade
currency: '{{ currencySymbol($plan->currency) }}',
```

Line 732 -- old:
```blade
{{ $plan->currency }}{{ number_format($pricePerSeat, 2) }}/{{ $cycleLabel }} per seat
```
new:
```blade
{{ currencySymbol($plan->currency) }}{{ number_format($pricePerSeat, 2) }}/{{ $cycleLabel }} per seat
```

- [ ] **Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/components/marketing/sections/pricing.blade.php \
       resources/views/pages/settings/subscription.blade.php \
       resources/views/wave/livewire/billing/checkout-review.blade.php \
       resources/views/wave/livewire/billing/checkout.blade.php \
       resources/views/pages/settings/organization.blade.php
git commit -m "fix: use currencySymbol() in all Blade templates"
```

---

### Task 13: Run full test suite and fix any breakage

- [ ] **Step 1: Run all tests**

Run: `php artisan test --compact`

- [ ] **Step 2: Fix any failing tests**

If any tests assert hardcoded `$` in formatted currency amounts, update them to use `currencySymbol('usd')` or just `'$'` (since the default factory currency is `usd`, the output is still `$`).

Common files that may need updates:
- `tests/Feature/BillingResourcesTest.php`
- `tests/Feature/FilamentBillingResourcesTest.php`
- `tests/Feature/CouponModelsTest.php`

- [ ] **Step 3: Run pint and commit any test fixes**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "test: fix tests for currencySymbol() changes"
```
