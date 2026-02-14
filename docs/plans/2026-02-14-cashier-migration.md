# Full Laravel Cashier Migration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete the migration from the old custom Stripe integration to Laravel Cashier, removing all dead columns, workarounds, and direct Stripe SDK calls.

**Architecture:** Replace all direct StripeClient usage with Cashier methods. Use Cashier's built-in WebhookController. Drop dead database columns. Use `stripe_status` and `quantity` as single source of truth. Keep custom `billable_type`/`billable_id`/`plan_id`/`cycle` columns for Wave-specific features.

**Tech Stack:** Laravel 12, Laravel Cashier 16, Stripe PHP SDK 17, Pest 4

---

### Task 1: Database Migration — Drop Dead Columns

**Files:**
- Create: `database/migrations/2026_02_14_000003_remove_old_billing_columns_from_subscriptions.php`

**Step 1: Create the migration**

```bash
php artisan make:migration remove_old_billing_columns_from_subscriptions --no-interaction
```

Then replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop unique index that references vendor columns
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('subscriptions');
            if (isset($indexes['subscriptions_vendor_slug_vendor_subscription_id_unique'])) {
                $table->dropUnique(['vendor_slug', 'vendor_subscription_id']);
            }

            $columns = Schema::getColumnListing('subscriptions');

            $dropColumns = array_intersect($columns, [
                'vendor_slug',
                'vendor_product_id',
                'vendor_transaction_id',
                'vendor_customer_id',
                'vendor_subscription_id',
                'status',
                'seats',
                'cancel_url',
                'update_url',
                'cancelled_at',
            ]);

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('vendor_slug')->nullable()->after('plan_id');
            $table->string('vendor_product_id')->nullable()->after('vendor_slug');
            $table->string('vendor_transaction_id')->nullable()->after('vendor_product_id');
            $table->string('vendor_customer_id')->nullable()->after('vendor_transaction_id');
            $table->string('vendor_subscription_id')->nullable()->after('vendor_customer_id');
            $table->string('status')->default('active')->after('vendor_subscription_id');
            $table->integer('seats')->default(1)->after('status');
            $table->string('cancel_url')->nullable();
            $table->string('update_url')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unique(['vendor_slug', 'vendor_subscription_id']);
        });
    }
};
```

**Step 2: Run the migration**

```bash
php artisan migrate --no-interaction
```

Expected: Migration runs without errors.

**Step 3: Commit**

```bash
git add database/migrations/2026_02_14_000003_remove_old_billing_columns_from_subscriptions.php
git commit -m "chore: drop dead billing columns from subscriptions table"
```

---

### Task 2: Update Subscription Model

**Files:**
- Modify: `wave/src/Subscription.php`

**Step 1: Rewrite the Subscription model**

Replace the entire file content with:

```php
<?php

namespace Wave;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'billable_type',
        'billable_id',
        'plan_id',
        'cycle',
        'trial_ends_at',
        'ends_at',
        'last_payment_at',
        'next_payment_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'next_payment_at' => 'datetime',
        ];
    }

    /**
     * The user (Cashier billable) that owns the subscription.
     * Uses user_id as Cashier expects.
     */
    public function user(): BelongsTo
    {
        $userClass = config('wave.user_model', User::class);

        return $this->belongsTo($userClass, 'user_id');
    }

    /**
     * The polymorphic billable entity (User or Organization).
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function clearBillableCache(): void
    {
        if ($this->billable instanceof User) {
            $this->billable->clearUserCache($this->plan_id);
        } elseif ($this->billable instanceof Organization) {
            $this->billable->clearMembersBillingCache($this->plan_id);
        }
    }

    /**
     * The plan that belongs to the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
```

Key changes:
- Removed `cancel()` override — Cashier's native `cancel()` now works (grace period)
- Fixed `user()` to use `user_id` instead of `billable_id`
- Removed all dead columns from `$fillable`
- Removed `cancelled_at` cast

**Step 2: Run tests to check for immediate breakage**

```bash
php artisan test --compact --filter=SubscriptionCancellationTest 2>&1 || true
```

Expected: Tests will fail because they reference old columns. We fix tests in Task 7.

**Step 3: Commit**

```bash
git add wave/src/Subscription.php
git commit -m "refactor: update Subscription model for Cashier — remove cancel override, fix user relationship"
```

---

### Task 3: Update User Model

**Files:**
- Modify: `wave/src/User.php`

**Step 1: Fix `onTrial()` method**

Replace lines 97-112:

```php
public function onTrial(?string $subscription = null): bool
{
    if ($subscription !== null) {
        return $this->cashierOnTrial($subscription);
    }

    if (is_null($this->trial_ends_at)) {
        return false;
    }

    if ($this->subscriber()) {
        return false;
    }

    return $this->trial_ends_at->isFuture();
}
```

**Step 2: Update `subscriber()` to use Cashier's `valid()`**

Replace `subscriber()` method (lines 304-328):

```php
public function subscriber()
{
    if (app()->bound('cache')) {
        try {
            $cacheKey = "user_subscriber_{$this->id}";
            $scopedCacheKey = "user_subscriber_{$this->id}_{$this->getBillingContextCacheSuffix()}";
            $query = function () {
                return $this->getResolvedActiveBillingSubscriptions()
                    ->contains(fn (Subscription $subscription): bool => $subscription->valid());
            };

            if ($this->getBillingContext()['type'] === 'user') {
                return $this->rememberBillingCacheValue(300, $cacheKey, $query);
            }

            return $this->rememberBillingCacheValue(300, $scopedCacheKey, $query);
        } catch (Exception $e) {
            // Fallback to direct query if cache fails
        }
    }

    return $this->getResolvedActiveBillingSubscriptions()
        ->contains(fn (Subscription $subscription): bool => $subscription->valid());
}
```

**Step 3: Update `subscribedToPlan()`**

Replace `subscribedToPlan()` method (lines 330-368):

```php
public function subscribedToPlan($planSlug)
{
    if (app()->bound('cache')) {
        try {
            $cacheKey = "user_plan_{$this->id}_{$planSlug}";
            $scopedCacheKey = "user_plan_{$this->id}_{$this->getBillingContextCacheSuffix()}_{$planSlug}";
            $query = function () use ($planSlug) {
                $plan = Plan::getByName($planSlug);
                if (! $plan) {
                    return false;
                }

                return $this->getResolvedActiveBillingSubscriptions()
                    ->where('plan_id', $plan->id)
                    ->contains(fn (Subscription $subscription): bool => $subscription->valid());
            };

            if ($this->getBillingContext()['type'] === 'user') {
                return $this->rememberBillingCacheValue(300, $cacheKey, $query);
            }

            return $this->rememberBillingCacheValue(300, $scopedCacheKey, $query);
        } catch (Exception $e) {
            // Fallback to direct query if cache fails
        }
    }

    $plan = Plan::getByName($planSlug);
    if (! $plan) {
        return false;
    }

    return $this->getResolvedActiveBillingSubscriptions()
        ->where('plan_id', $plan->id)
        ->contains(fn (Subscription $subscription): bool => $subscription->valid());
}
```

**Step 4: Update `activeBillingSubscription()`**

Replace `activeBillingSubscription()` method (lines 256-262):

```php
public function activeBillingSubscription()
{
    return $this->getResolvedActiveBillingSubscriptions()
        ->filter(fn (Subscription $subscription): bool => $subscription->valid())
        ->sortByDesc('created_at')
        ->first();
}
```

**Step 5: Update `subscription()` HasOne relationship**

Replace `subscription()` method (lines 395-401):

```php
public function subscription(): HasOne
{
    return $this->hasOne(Subscription::class, 'user_id')
        ->where('billable_type', 'user')
        ->orderByDesc('created_at');
}
```

Note: Removed `->where('status', 'active')` — Cashier handles status via `stripe_status`. This relationship is mostly used for accessing the latest subscription record.

**Step 6: Replace custom `invoices()` with Cashier's**

Replace the `invoices()` method (lines 403-431):

```php
public function invoices()
{
    if (! $this->hasStripeId()) {
        return [];
    }

    try {
        $cashierInvoices = $this->invoicesIncludingPending();

        return $cashierInvoices->map(fn ($invoice) => (object) [
            'id' => $invoice->id,
            'created' => $invoice->date()->isoFormat('MMMM Do YYYY, h:mm:ss a'),
            'total' => $invoice->total(),
            'download' => $invoice->invoicePdf(),
        ])->all();
    } catch (\Throwable) {
        return [];
    }
}
```

Note: Rename `invoices()` to avoid collision with Cashier's trait. We need to check if the Blade templates call `$user->invoices()`. If so, we keep this name but prefix with a different name.

Actually, since Cashier's `Billable` trait defines `invoices()`, and our User extends it, we need a different name to avoid infinite recursion. Rename to `billingInvoices()`:

```php
public function billingInvoices()
{
    if (! $this->hasStripeId()) {
        return [];
    }

    try {
        $cashierInvoices = $this->invoicesIncludingPending();

        return $cashierInvoices->map(fn ($invoice) => (object) [
            'id' => $invoice->id,
            'created' => $invoice->date()->isoFormat('MMMM Do YYYY, h:mm:ss a'),
            'total' => $invoice->total(),
            'download' => $invoice->invoicePdf(),
        ])->all();
    } catch (\Throwable) {
        return [];
    }
}
```

Then update the invoices Blade template to call `billingInvoices()` instead of `invoices()`.

Check: `resources/themes/anchor/pages/settings/invoices.blade.php` — find and replace `->invoices()` with `->billingInvoices()`.

**Step 7: Remove unused `StripeClient` import**

Remove `use Stripe\StripeClient;` from the imports (line 24) since we no longer use it directly.

**Step 8: Commit**

```bash
git add wave/src/User.php
git commit -m "refactor: update User model to use Cashier's valid(), fix onTrial, replace invoices"
```

---

### Task 4: Update Invoices Blade Template

**Files:**
- Modify: `resources/themes/anchor/pages/settings/invoices.blade.php`

**Step 1: Replace `invoices()` calls with `billingInvoices()`**

Find all occurrences of `->invoices()` and replace with `->billingInvoices()`.

**Step 2: Commit**

```bash
git add resources/themes/anchor/pages/settings/invoices.blade.php
git commit -m "refactor: rename invoices() to billingInvoices() in template"
```

---

### Task 5: Update Subscription Controller — Cancellation with Grace Period

**Files:**
- Modify: `wave/src/Http/Controllers/SubscriptionController.php`

**Step 1: Rewrite the controller**

Replace the entire file:

```php
<?php

namespace Wave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Wave\Plan;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 0,
            'message' => 'Checkout sessions are created from the subscription page.',
        ], 422);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = auth()->user()->latestSubscription();

        if (! $subscription) {
            return response()->json(['status' => 0, 'message' => 'No active subscription found.'], 422);
        }

        if (! $subscription->valid()) {
            return response()->json(['status' => 0, 'message' => 'No active subscription found.'], 422);
        }

        try {
            // Cashier's cancel() sets cancel_at_period_end on Stripe
            // and sets ends_at to the current period end (grace period)
            $subscription->cancel();
            $subscription->clearBillableCache();

            return response()->json([
                'status' => 1,
                'message' => 'Your subscription has been canceled. You will have access until '.$subscription->ends_at->format('F j, Y').'.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 0, 'message' => 'Failed to cancel the subscription. Please try again later.'], 422);
        }
    }

    public function switchPlans(Request $request): RedirectResponse
    {
        $plan = Plan::find($request->integer('plan_id'));
        $billingCycle = $request->input('billing_cycle', 'month');
        $subscription = $request->user()->latestSubscription();

        if (! isset($plan->id)) {
            return redirect()->back()->with(['message' => 'Could not locate the selected plan.', 'message_type' => 'danger']);
        }

        if (! $subscription || ! $subscription->valid()) {
            return redirect()->back()->with(['message' => 'No active subscription found to update.', 'message_type' => 'danger']);
        }

        $priceId = $billingCycle === 'year' ? $plan->yearly_price_id : $plan->monthly_price_id;
        if (empty($priceId)) {
            return redirect()->back()->with(['message' => 'The selected billing cycle is not available for this plan.', 'message_type' => 'danger']);
        }

        try {
            // Use Cashier's swapAndInvoice for immediate proration
            $subscription->swapAndInvoice($priceId);

            // Update custom Wave columns
            $subscription->update([
                'plan_id' => $plan->id,
                'cycle' => $billingCycle,
            ]);

            $subscription->clearBillableCache();

            return redirect()->back()->with(['message' => 'Successfully switched to the '.$plan->name.' plan.', 'message_type' => 'success']);
        } catch (\Throwable $e) {
            return redirect()->back()->with(['message' => 'Sorry, there was an issue updating your plan.', 'message_type' => 'danger']);
        }
    }

    public function setBillingContext(Request $request): RedirectResponse
    {
        $request->validate([
            'current_organization_id' => 'nullable|integer',
        ]);

        $organizationId = $request->integer('current_organization_id');
        $organizationId = $organizationId > 0 ? $organizationId : null;
        $organizationsEnabled = config('wave.organizations_enabled', true);
        $user = $request->user();

        if (! $organizationsEnabled) {
            $organizationId = null;
        } elseif ($organizationId !== null) {
            $canUseOrganization = $user->organizations()
                ->where('organizations.active', true)
                ->wherePivot('status', 'active')
                ->where('organizations.id', $organizationId)
                ->exists();

            if (! $canUseOrganization) {
                return redirect()->back()->with([
                    'message' => 'You do not belong to that organization.',
                    'message_type' => 'danger',
                ]);
            }
        }

        $user->setBillingContext($organizationId);
        $user->save();

        return redirect()->back()->with([
            'message' => 'Billing context updated successfully.',
            'message_type' => 'success',
        ]);
    }
}
```

Key changes:
- Removed all `StripeClient` and `Carbon` imports
- `cancel()` now uses Cashier's `$subscription->cancel()` which provides grace period
- `switchPlans()` now uses Cashier's `$subscription->swapAndInvoice()`
- Status checks use `$subscription->valid()` instead of checking old `status` column
- Removed `extractPeriodDates()` helper — no longer needed

**Step 2: Commit**

```bash
git add wave/src/Http/Controllers/SubscriptionController.php
git commit -m "refactor: use Cashier cancel/swap in SubscriptionController — grace period support"
```

---

### Task 6: Update Checkout Livewire Component

**Files:**
- Modify: `wave/src/Http/Livewire/Billing/Checkout.php`

**Step 1: Update `switchPlan()` to use Cashier**

Replace the `switchPlan()` method (lines 222-301) and remove `extractPeriodDates()` (lines 306-318):

```php
public function switchPlan(Plan $plan)
{
    $subscription = auth()->user()->latestSubscription();

    if (! $subscription) {
        return;
    }

    if (! $subscription->valid()) {
        Notification::make()
            ->title('No active subscription found to update.')
            ->danger()
            ->send();

        return;
    }

    $priceId = $this->billing_cycle_selected === 'month' ? $plan->monthly_price_id : $plan->yearly_price_id;
    if (empty($priceId)) {
        Notification::make()
            ->title('This billing cycle is not available for the selected plan.')
            ->danger()
            ->send();

        return;
    }

    try {
        // Use Cashier's swapAndInvoice for immediate proration
        $subscription->swapAndInvoice($priceId);

        // Update custom Wave columns
        $subscription->update([
            'plan_id' => $plan->id,
            'cycle' => $this->billing_cycle_selected,
        ]);

        $subscription->clearBillableCache();

        return redirect()->to('/settings/subscription')->with(['update' => true]);
    } catch (\Throwable) {
        Notification::make()
            ->title('Unable to switch plans right now. Please try again.')
            ->danger()
            ->send();
    }
}
```

Also remove the `extractPeriodDates()` method entirely.

Remove unused imports: `Carbon`, `StripeClient`.

**Step 2: Commit**

```bash
git add wave/src/Http/Livewire/Billing/Checkout.php
git commit -m "refactor: use Cashier swapAndInvoice in Checkout component"
```

---

### Task 7: Update UpdateSubscriptionQuantity Action

**Files:**
- Modify: `wave/src/Actions/Billing/Stripe/UpdateSubscriptionQuantity.php`

**Step 1: Rewrite to use Cashier**

```php
<?php

namespace Wave\Actions\Billing\Stripe;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Wave\Subscription;

class UpdateSubscriptionQuantity
{
    /**
     * Update the seat quantity on a subscription.
     *
     * @param  int  $delta  Positive to add seats, negative to remove
     * @param  string|null  $prorationBehavior  Stripe proration behavior. When null: increases use 'always_invoice', decreases use 'none'
     *
     * @throws RuntimeException
     */
    public function __invoke(Subscription $subscription, int $delta, ?string $prorationBehavior = null): ?string
    {
        $newQuantity = $subscription->quantity + $delta;
        $invoicePaymentUrl = null;

        if ($newQuantity < 1) {
            throw new RuntimeException('Subscription must have at least 1 seat.');
        }

        if ($subscription->billable_type !== 'organization') {
            throw new RuntimeException('Seat updates are only available for organization subscriptions.');
        }

        try {
            if ($delta > 0) {
                // For increases: prorate and invoice immediately
                $subscription->updateQuantity($newQuantity);

                // Check if there's an unpaid invoice requiring action
                if (! empty($subscription->stripe_id)) {
                    $stripe = new StripeClient(config('services.stripe.secret'));
                    $stripeSubscription = $stripe->subscriptions->retrieve($subscription->stripe_id, ['expand' => ['latest_invoice']]);

                    $invoice = $stripeSubscription->latest_invoice;
                    if ($invoice && ! empty($invoice->hosted_invoice_url) && ! ($invoice->paid ?? true)) {
                        $invoicePaymentUrl = $invoice->hosted_invoice_url;
                    }
                }
            } else {
                // For decreases: no proration
                $subscription->noProrate()->updateQuantity($newQuantity);
            }
        } catch (CardException $e) {
            throw new RuntimeException('Unable to charge the saved payment method for the additional seats.', 0, $e);
        } catch (ApiErrorException $e) {
            throw new RuntimeException('Failed to update Stripe subscription: '.$e->getMessage(), 0, $e);
        }

        return $invoicePaymentUrl;
    }
}
```

Key changes:
- Uses `$subscription->quantity` instead of `$subscription->seats`
- Uses Cashier's `updateQuantity()` and `noProrate()->updateQuantity()`
- Only uses direct StripeClient for the invoice URL check (Cashier doesn't expose this)

**Step 2: Commit**

```bash
git add wave/src/Actions/Billing/Stripe/UpdateSubscriptionQuantity.php
git commit -m "refactor: use Cashier updateQuantity in seat management"
```

---

### Task 8: Update Stripe Portal Controller

**Files:**
- Modify: `wave/src/Http/Controllers/Billing/Stripe.php`

**Step 1: Simplify the controller**

```php
<?php

namespace Wave\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class Stripe extends Controller
{
    public function redirect_to_customer_portal(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->hasStripeId()) {
            return redirect()->back()->withErrors('No active subscription found.');
        }

        return $user->redirectToBillingPortal(route('settings.subscription'));
    }
}
```

Removed the `vendor_customer_id` fallback — Cashier manages `stripe_id` on users.

**Step 2: Commit**

```bash
git add wave/src/Http/Controllers/Billing/Stripe.php
git commit -m "refactor: simplify Stripe portal controller"
```

---

### Task 9: Replace Webhook Controller with Cashier's

**Files:**
- Modify: `wave/routes/web.php:49-51`
- Delete: `wave/src/Http/Controllers/Billing/Webhooks/StripeWebhook.php`

**Step 1: Update the webhook route**

In `wave/routes/web.php`, replace lines 49-51:

```php
Route::post('stripe/webhook', [\Laravel\Cashier\Http\Controllers\WebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');
```

Cashier's WebhookController already applies signature verification internally.

**Step 2: Delete the custom webhook controller**

Delete `wave/src/Http/Controllers/Billing/Webhooks/StripeWebhook.php`.

**Step 3: Commit**

```bash
git add wave/routes/web.php
git rm wave/src/Http/Controllers/Billing/Webhooks/StripeWebhook.php
git commit -m "refactor: use Cashier's WebhookController instead of custom handler"
```

---

### Task 10: Simplify HandleStripeWebhook Listener

**Files:**
- Modify: `app/Listeners/HandleStripeWebhook.php`

**Step 1: Rewrite to only handle custom fields**

Cashier's WebhookController now handles all standard fields (stripe_status, stripe_price, quantity, trial_ends_at, ends_at). Our listener only needs to set custom Wave fields.

```php
<?php

namespace App\Listeners;

use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Events\WebhookReceived;
use Wave\Plan;
use Wave\Subscription;

class HandleStripeWebhook
{
    /**
     * Handle Stripe webhooks dispatched by Cashier.
     *
     * Cashier's WebhookController handles standard fields (stripe_status,
     * stripe_price, quantity, trial_ends_at, ends_at). This listener only
     * sets custom Wave fields: billable_type, billable_id, plan_id, cycle.
     */
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->handleCheckoutSessionCompleted($event->payload),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->payload),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->payload),
            default => null,
        };
    }

    protected function handleCheckoutSessionCompleted(array $payload): void
    {
        $session = $payload['data']['object'] ?? null;
        if (! is_array($session)) {
            return;
        }

        $sessionId = $session['id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $cacheKey = 'stripe_checkout_session_'.$sessionId;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addHours(24));

        $stripeSubscriptionId = $session['subscription'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $billableType = in_array(($metadata['billable_type'] ?? ''), ['user', 'organization'], true)
            ? $metadata['billable_type']
            : 'user';
        $billableId = (int) ($metadata['billable_id'] ?? 0);
        $planId = (int) ($metadata['plan_id'] ?? 0);
        $billingCycle = ($metadata['billing_cycle'] ?? 'month') === 'year' ? 'year' : 'month';

        if ($billableId <= 0) {
            return;
        }

        // Cashier may have already created the subscription via the
        // customer.subscription.created webhook. Find it and set custom fields.
        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->fill([
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'plan_id' => $planId > 0 ? $planId : $subscription->plan_id,
            'cycle' => $billingCycle,
        ]);
        $subscription->save();
        $subscription->clearBillableCache();
    }

    protected function handleSubscriptionUpdated(array $payload): void
    {
        $stripeSubscription = $payload['data']['object'] ?? null;
        if (! is_array($stripeSubscription)) {
            return;
        }

        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        // Update plan_id and cycle if the price changed
        $priceId = $stripeSubscription['items']['data'][0]['price']['id'] ?? null;
        $updatedPlan = $this->findPlanByPriceId(is_string($priceId) ? $priceId : null);

        if ($updatedPlan) {
            $subscription->plan_id = $updatedPlan['plan_id'];
            $subscription->cycle = $updatedPlan['cycle'];
        }

        // Update payment timestamps
        [$periodStart, $periodEnd] = $this->extractPeriodDates($stripeSubscription);
        if ($periodStart) {
            $subscription->last_payment_at = Carbon::createFromTimestamp((int) $periodStart);
        }
        if ($periodEnd) {
            $subscription->next_payment_at = Carbon::createFromTimestamp((int) $periodEnd);
        }

        $subscription->save();
        $subscription->clearBillableCache();
    }

    protected function handleSubscriptionDeleted(array $payload): void
    {
        $stripeSubscription = $payload['data']['object'] ?? null;
        if (! is_array($stripeSubscription)) {
            return;
        }

        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($stripeSubscriptionId) || $stripeSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeSubscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->clearBillableCache();
    }

    /**
     * @return array{plan_id: int, cycle: string}|null
     */
    protected function findPlanByPriceId(?string $priceId): ?array
    {
        if (! $priceId) {
            return null;
        }

        $monthlyPlan = Plan::query()->where('monthly_price_id', $priceId)->first();
        if ($monthlyPlan) {
            return ['plan_id' => (int) $monthlyPlan->id, 'cycle' => 'month'];
        }

        $yearlyPlan = Plan::query()->where('yearly_price_id', $priceId)->first();
        if ($yearlyPlan) {
            return ['plan_id' => (int) $yearlyPlan->id, 'cycle' => 'year'];
        }

        return null;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function extractPeriodDates(array $stripeSubscription): array
    {
        $periodStart = $stripeSubscription['current_period_start'] ?? null;
        $periodEnd = $stripeSubscription['current_period_end'] ?? null;

        if (! $periodStart && isset($stripeSubscription['items']['data'][0])) {
            $item = $stripeSubscription['items']['data'][0];
            $periodStart = $item['current_period_start'] ?? null;
            $periodEnd = $item['current_period_end'] ?? null;
        }

        return [
            is_numeric($periodStart) ? (int) $periodStart : null,
            is_numeric($periodEnd) ? (int) $periodEnd : null,
        ];
    }
}
```

Key changes:
- Removed `handleSubscriptionCreated` — Cashier's webhook controller creates subscriptions
- Checkout handler only sets custom fields (billable_type, billable_id, plan_id, cycle)
- Subscription updated handler only updates plan_id, cycle, and payment timestamps
- Deleted handler only clears caches — Cashier handles stripe_status and ends_at
- Removed all standard Cashier field handling
- Removed `resolveUserId()` — Cashier sets user_id via its webhook controller

**Step 2: Commit**

```bash
git add app/Listeners/HandleStripeWebhook.php
git commit -m "refactor: simplify webhook listener — Cashier handles standard fields"
```

---

### Task 11: Delete CancelExpiredSubscriptions Command

**Files:**
- Delete: `wave/src/Console/Commands/CancelExpiredSubscriptions.php`

**Step 1: Delete the command**

Cashier handles grace periods natively. When `ends_at` passes, `$subscription->valid()` returns false. No cron job needed.

```bash
git rm wave/src/Console/Commands/CancelExpiredSubscriptions.php
```

Also check if it's registered in the scheduler (`routes/console.php`) and remove the schedule entry if present.

**Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove CancelExpiredSubscriptions — Cashier handles grace periods"
```

---

### Task 12: Update Blade Templates — Replace Old Column References

**Files:**
- Modify: `resources/themes/anchor/pages/settings/subscription.blade.php`
- Modify: `resources/themes/anchor/pages/settings/organization.blade.php`
- Modify: `resources/themes/anchor/pages/settings/export.blade.php`

**Step 1: subscription.blade.php**

Replace all occurrences:
- `$subscription->seats` → `$subscription->quantity`
- `$subscription->vendor_subscription_id` → `$subscription->stripe_id`

**Step 2: organization.blade.php**

Replace all occurrences:
- `$subscription->seats` → `$subscription->quantity`
- `$activeSubscription->seats` → `$activeSubscription->quantity`

**Step 3: export.blade.php**

Replace:
- `'status' => $subscription->status` → `'status' => $subscription->stripe_status`

**Step 4: Commit**

```bash
git add resources/themes/anchor/pages/settings/subscription.blade.php resources/themes/anchor/pages/settings/organization.blade.php resources/themes/anchor/pages/settings/export.blade.php
git commit -m "refactor: update Blade templates to use Cashier column names"
```

---

### Task 13: Update All Tests

**Files:**
- Modify: `tests/Feature/SubscriptionCancellationTest.php`
- Modify: `tests/Feature/StripeWebhookTest.php`
- Modify: `tests/Feature/PlanSwitchingTest.php`
- Modify: `tests/Feature/OrganizationBillingContextTest.php`
- Modify: `tests/Feature/DataExportTest.php`

All tests create subscriptions using old columns (`vendor_slug`, `vendor_subscription_id`, `status`, `seats`). These must be updated to use Cashier columns (`stripe_id`, `stripe_status`, `quantity`).

**Step 1: Create a test helper for subscription creation**

In each test's `beforeEach`, subscription creation should use the new column names. The pattern for creating a test subscription becomes:

```php
$subscription = Subscription::create([
    'user_id' => $this->user->id,
    'type' => 'default',
    'stripe_id' => 'sub_test_'.uniqid(),
    'stripe_status' => 'active',
    'stripe_price' => 'price_monthly_test',
    'quantity' => 1,
    'billable_type' => 'user',
    'billable_id' => $this->user->id,
    'plan_id' => $this->premiumPlan->id,
    'cycle' => 'month',
]);
```

**Step 2: Rewrite SubscriptionCancellationTest.php**

All 4 tests need updating. Key changes:
- Remove `vendor_slug`, `vendor_subscription_id`, `vendor_customer_id`, `status`, `seats` from create calls
- Add `user_id`, `type`, `stripe_id`, `stripe_status`, `stripe_price`, `quantity`
- Status checks change from `->status` to `->stripe_status`
- `'cancelled'` becomes `'canceled'` (Stripe spelling)
- The cancellation test needs to verify Cashier's `canceled()` and `onGracePeriod()` methods work
- `subscriber()` after cancel should still return true during grace period

**Step 3: Rewrite StripeWebhookTest.php**

Same column changes. Also update `cancel()` test to verify `stripe_status` instead of `status`.

**Step 4: Rewrite PlanSwitchingTest.php**

Same column changes. Update the `subscription` relationship test since it no longer filters by `status`.

**Step 5: Rewrite OrganizationBillingContextTest.php**

Same column changes. Update org subscription creation and the cancel endpoint test.

**Step 6: Update DataExportTest.php**

Update the raw DB insert at line 119-132 to use new columns. Update the `status` assertion to use `stripe_status`.

**Step 7: Run all tests**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 8: Commit**

```bash
git add tests/Feature/SubscriptionCancellationTest.php tests/Feature/StripeWebhookTest.php tests/Feature/PlanSwitchingTest.php tests/Feature/OrganizationBillingContextTest.php tests/Feature/DataExportTest.php
git commit -m "test: update all billing tests for Cashier columns"
```

---

### Task 14: Run Pint and Final Verification

**Step 1: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 3: Clear caches**

```bash
php artisan view:clear && php artisan cache:clear
```

**Step 4: Final commit if Pint made changes**

```bash
git add -A
git commit -m "style: apply Pint formatting"
```
