# Full Laravel Cashier Migration Design

## Goal

Complete the migration from the old custom Stripe integration to Laravel Cashier. Remove all workarounds, dead columns, and direct Stripe SDK calls. Use Cashier's default behavior for trials, grace periods, cancellation, plan switching, and invoices.

## Decisions

- **Grace period on cancel**: Yes. Cashier's `cancel()` sets `cancel_at_period_end` on Stripe and `ends_at` to period end.
- **Database cleanup**: Drop all dead columns now.
- **Single source of truth**: Cashier columns only (`stripe_status`, `quantity`). Drop `status`, `seats`.

## Phase 1: Database Migration

New migration to drop dead columns from `subscriptions` table:

**Drop:**
- `vendor_slug`, `vendor_product_id`, `vendor_transaction_id`, `vendor_customer_id`, `vendor_subscription_id`
- `status` (replaced by `stripe_status`)
- `seats` (replaced by `quantity`)
- `cancel_url`, `update_url` (Paddle concepts)
- `cancelled_at` (Cashier uses `ends_at` + `stripe_status`)

**Keep (custom):**
- `billable_type`, `billable_id` — polymorphic org billing
- `plan_id` — Wave plan mapping
- `cycle` — month/year
- `last_payment_at`, `next_payment_at` — payment cycle tracking

**Keep (Cashier standard):**
- `user_id`, `type`, `stripe_id`, `stripe_status`, `stripe_price`, `quantity`, `trial_ends_at`, `ends_at`

## Phase 2: Subscription Model (`wave/src/Subscription.php`)

- **Remove `cancel()` override** — let Cashier's `cancel()` handle grace periods natively.
- **Fix `user()` relationship** — use `user_id` instead of `billable_id`. Cashier expects this.
- **Update `$fillable`** — remove all dropped columns.
- **Remove `cancelled_at` cast** — column no longer exists.
- Keep `billable()` morphTo, `plan()`, `clearBillableCache()`.

## Phase 3: User Model (`wave/src/User.php`)

### Fix `onTrial()`
Add expiry check: `return $this->trial_ends_at->isFuture()`.

### Replace `subscriber()`
Change from `$subscription->status === 'active'` to `$subscription->valid()`. Cashier's `valid()` returns true for active, trialing, and grace period subscriptions. Works with the existing billing context query for both user and org billing.

### Replace `subscribedToPlan()`
Same approach — use `$subscription->valid()` instead of `status === 'active'`.

### Replace `activeBillingSubscription()`
Filter by `$subscription->valid()` instead of `status === 'active'`.

### Replace custom `invoices()`
Use Cashier's built-in `$user->invoices()` which returns proper Invoice objects. For org billing context, resolve the owner's Stripe customer and fetch invoices from there.

### Fix `subscription()` HasOne
Use `user_id` column. Remove `status` filter — Cashier's `subscription('default')` method handles type filtering.

## Phase 4: Controllers

### `SubscriptionController::cancel()`
Replace direct Stripe SDK call with:
```php
$subscription->cancel(); // Grace period — access until ends_at
```
Cashier handles: setting `cancel_at_period_end` on Stripe, setting `ends_at` to period end, setting `stripe_status` via webhook.

### `SubscriptionController::switchPlans()`
Replace direct Stripe SDK call with:
```php
$subscription->swapAndInvoice($priceId); // Immediate proration
```
Then update custom columns (`plan_id`, `cycle`) locally.

### `Checkout::switchPlan()`
Same approach as SubscriptionController — use Cashier's `swap()`.

### `UpdateSubscriptionQuantity`
Replace direct Stripe SDK call with Cashier's:
```php
$subscription->updateQuantity($newQuantity); // or incrementQuantity/decrementQuantity
```
Handle proration via `noProrate()` chain when removing seats.

### `Stripe::redirect_to_customer_portal()`
Simplify — remove `vendor_customer_id` fallback. Cashier's `redirectToBillingPortal()` works directly with `stripe_id` on user.

## Phase 5: Webhook Handler

### Replace custom webhook controller
Use Cashier's built-in `WebhookController` instead of the custom `StripeWebhook` controller. Register the route manually (since we call `ignoreRoutes()`):

```php
Route::post('stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
```

Cashier's controller handles all standard field syncing (`stripe_status`, `stripe_price`, `quantity`, `trial_ends_at`, `ends_at`).

### Simplify `HandleStripeWebhook` listener
Only handle custom fields that Cashier doesn't know about:

- `checkout.session.completed`: Find subscription by `stripe_id`, set `billable_type`, `billable_id`, `plan_id`, `cycle` from session metadata. Clear billing caches.
- `customer.subscription.updated`: Update `plan_id` and `cycle` if price changed (map via `findPlanByPriceId`). Update `last_payment_at`, `next_payment_at`. Clear caches.
- `customer.subscription.deleted`: Clear caches.

Remove all standard field handling (stripe_status, quantity, etc.) — Cashier does this.

## Phase 6: Cleanup

- Delete `wave/src/Http/Controllers/Billing/Webhooks/StripeWebhook.php`
- Delete `CancelExpiredSubscriptions` command — Cashier handles grace periods; `subscribed()` returns false after `ends_at` passes.
- Remove direct `StripeClient` imports from controllers where Cashier methods replace them.

## Phase 7: Tests

Update all billing-related tests:
- `SubscriptionCancellationTest` — test grace period behavior
- `PlanSwitchingTest` — test via Cashier's swap
- `StripeWebhookTest` — test with Cashier's webhook controller
- `OrganizationBillingContextTest` — verify org billing still works with Cashier columns
- `DataExportTest` — update if it references dropped columns

## Polymorphic Org Billing (Key Architecture Note)

Cashier assumes `user_id` on subscriptions. For organization billing:
- The org **owner** is the Stripe customer (`users.stripe_id`).
- `user_id` on the subscription = owner's user ID.
- `billable_type = 'organization'`, `billable_id = org_id` for custom resolution.
- Org members access the subscription via the billing context query (not Cashier's `subscribed()`).
- The custom `subscriber()` method handles both cases using `valid()`.
