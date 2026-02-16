# Checkout Page: Trust Signals + FAQ

## Problem

The checkout review page has significant empty space below the plan details card in the left column (col-span-3). The order summary sidebar (col-span-2) is much taller, creating visual imbalance.

## Solution

Add two sections below the plan card in the left column:

### 1. Trust Signals Row

A horizontal row of 3 items below the plan card, no border:

- Shield icon + "Secure checkout"
- Arrow-counter-clockwise icon + "Cancel anytime"
- Credit-card icon + "No hidden fees"

Style: `text-xs text-zinc-400`, icons `h-4 w-4 text-zinc-300`. Flex row with `gap-6`, centered.

### 2. FAQ Accordion

Bordered card matching plan card style (`rounded-xl border border-zinc-200 bg-white`).

- Header: "Common questions" using `text-xs font-semibold uppercase tracking-wider text-zinc-400`
- Alpine.js accordion: `x-data="{ open: null }"` with click toggles
- Chevron icon rotates on open
- FAQ content from `config('wave.checkout.faq')`

### Config Structure

In `config/wave.php`:

```php
'checkout' => [
    'faq' => [
        ['question' => 'Can I cancel anytime?', 'answer' => 'Yes, you can cancel your subscription at any time. No questions asked.'],
        ['question' => 'How does billing work?', 'answer' => 'You\'ll be charged at the start of each billing cycle. Upgrades are prorated, downgrades take effect at the end of your current period.'],
        ['question' => 'Can I change my plan later?', 'answer' => 'Absolutely. You can upgrade or downgrade your plan at any time from your subscription settings.'],
        ['question' => 'Is my payment information secure?', 'answer' => 'Yes, all payments are processed securely through Stripe. We never store your card details.'],
    ],
],
```

## Files to Modify

1. `wave/resources/views/livewire/billing/checkout-review.blade.php` - Add trust signals + FAQ sections
2. `config/wave.php` - Add `checkout.faq` config array

## Approach

- Inline in Approach A: both sections in the left column below the plan card
- Pure Alpine.js accordion, no Livewire needed for FAQ toggle
- FAQ content configurable via `config/wave.php`
