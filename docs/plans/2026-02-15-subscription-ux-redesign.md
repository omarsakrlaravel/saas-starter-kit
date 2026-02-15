# Subscription UX Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Split the monolithic subscription page into 3 focused pages (overview, change plan, payment methods), fix plan change logic bugs, and add in-app cancel/resume subscription.

**Architecture:** The existing subscription Folio page becomes a clean overview. Two new Folio sub-pages handle "Change Plan" and "Payment Methods". The existing `billing.checkout` Livewire component is reused on the change plan page with bug fixes. A new `billing.payment-methods` Livewire component handles Stripe payment methods via Cashier APIs. Cancel/resume subscription is added to the existing `billing.update` Livewire component.

**Tech Stack:** Laravel Folio pages, Livewire 3 components, Laravel Cashier Stripe (payment methods, cancel/resume), Alpine.js for interactivity, Stripe.js for card element, Filament modals + notifications.

---

### Task 1: Fix PlanChangeResolver — Add `cycle_change` Return Type

**Files:**
- Modify: `wave/src/Services/PlanChangeResolver.php:21-27`
- Test: `tests/Feature/PlanChangeResolverTest.php`

**Step 1: Update PlanChangeResolver to return `cycle_change` for same-plan-different-cycle**

In `wave/src/Services/PlanChangeResolver.php`, change the same-plan block (lines 26-27) from:

```php
// Same plan, different cycle
if ((int) $currentPlanId === (int) $newPlan->id) {
    return $newCycle === 'year' ? 'upgrade' : 'downgrade';
}
```

to:

```php
// Same plan, different cycle
if ((int) $currentPlanId === (int) $newPlan->id) {
    return 'cycle_change';
}
```

Also update the PHPDoc return type on `resolve()` from `'upgrade'|'downgrade'|'same'` to `'upgrade'|'downgrade'|'same'|'cycle_change'`.

**Step 2: Update PlanChangeResolverTest**

In `tests/Feature/PlanChangeResolverTest.php`, find any existing tests that assert `'upgrade'` or `'downgrade'` for same-plan-different-cycle cases, and update them to assert `'cycle_change'` instead.

Add a test if one doesn't exist:

```php
it('returns cycle_change for same plan different cycle', function () {
    $plan = \Wave\Plan::factory()->create([
        'monthly_price' => 29,
        'yearly_price' => 290,
    ]);
    $subscription = \Wave\Subscription::factory()->create([
        'plan_id' => $plan->id,
        'cycle' => 'year',
    ]);

    $resolver = new \Wave\Services\PlanChangeResolver();
    expect($resolver->resolve($subscription, $plan, 'month'))->toBe('cycle_change');
    expect($resolver->resolve($subscription, $plan, 'year'))->toBe('same');
});
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=PlanChangeResolver`
Expected: All tests pass.

**Step 4: Commit**

```
feat: add cycle_change return type to PlanChangeResolver
```

---

### Task 2: Fix Checkout Component — Default Cycle + Handle cycle_change

**Files:**
- Modify: `wave/src/Http/Livewire/Billing/Checkout.php:40-48` (mount method)
- Modify: `wave/src/Http/Livewire/Billing/Checkout.php:241-284` (switchPlan method)

**Step 1: Fix mount() to default cycle to current subscription cycle**

In `Checkout.php`, in the `mount()` method, after the existing `$this->change` block:

```php
if ($this->change) {
    $this->userSubscription = auth()->user()->latestSubscription();
    $this->userPlan = $this->userSubscription?->plan;
}
```

Add after it (before `$this->initializeSeatQuantity()`):

```php
if ($this->change && $this->userSubscription) {
    $this->billing_cycle_selected = $this->userSubscription->cycle;
}
```

This ensures the toggle shows the user's actual billing cycle when they open the change plan page.

**Step 2: Handle `cycle_change` in switchPlan()**

In `Checkout.php`, in the `switchPlan()` method, update the logic that handles change types. Currently:

```php
if ($changeType === 'upgrade') {
    return $this->applyImmediateUpgrade($subscription, $plan, $priceId);
}

return $this->scheduleDowngrade($subscription, $plan);
```

Change to:

```php
if ($changeType === 'upgrade') {
    return $this->applyImmediateUpgrade($subscription, $plan, $priceId);
}

if ($changeType === 'cycle_change') {
    if ($this->billing_cycle_selected === 'year') {
        return $this->applyImmediateUpgrade($subscription, $plan, $priceId);
    }

    return $this->scheduleDowngrade($subscription, $plan);
}

return $this->scheduleDowngrade($subscription, $plan);
```

Month-to-year cycle changes apply immediately (like upgrades). Year-to-month cycle changes are scheduled at end of period (like downgrades).

**Step 3: Run tests**

Run: `php artisan test --compact --filter=Checkout`
Expected: Pass (or fix any failing tests due to new cycle_change type).

**Step 4: Commit**

```
fix: default billing cycle to user's current cycle and handle cycle_change
```

---

### Task 3: Fix Checkout Blade — Cycle-Aware Badge + Cycle Change Buttons

**Files:**
- Modify: `wave/resources/views/livewire/billing/checkout.blade.php:96-228`

**Step 1: Make `$isCurrentPlan` cycle-aware**

In the `@foreach($plans as $plan)` block at line ~99, change:

```php
$isCurrentPlan = $change && $userPlan && $plan->id == $userPlan->id;
```

to:

```php
$isCurrentPlan = $change && $userPlan && $plan->id == $userPlan->id && $this->billing_cycle_selected === $userSubscription?->cycle;
```

**Step 2: Make `$isPendingTarget` cycle-aware**

Change:

```php
$isPendingTarget = $change && $userSubscription && $userSubscription->hasPendingChange()
    && (int) $userSubscription->pending_plan_id === (int) $plan->id;
```

to:

```php
$isPendingTarget = $change && $userSubscription && $userSubscription->hasPendingChange()
    && (int) $userSubscription->pending_plan_id === (int) $plan->id
    && $this->billing_cycle_selected === $userSubscription->pending_cycle;
```

**Step 3: Add cycle_change button and modal**

In the `@if($change)` section of the card footer (after the `@elseif($changeType === 'downgrade')` block, before the final `@endif`), add:

```php
@elseif($changeType === 'cycle_change')
    {{-- Cycle change CTA --}}
    @php
        $cycleLabel = $this->billing_cycle_selected === 'year' ? 'Yearly' : 'Monthly';
        $isImmediateChange = $this->billing_cycle_selected === 'year';
    @endphp
    <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
        <x-slot name="trigger">
            <button @class([
                'w-full rounded-lg py-2.5 text-sm font-semibold shadow-sm transition-colors',
                'bg-emerald-600 text-white hover:bg-emerald-700' => $isImmediateChange,
                'border-2 border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:bg-zinc-700' => !$isImmediateChange,
            ])>
                Switch to {{ $cycleLabel }} Billing
            </button>
        </x-slot>
        <div class="relative flex w-full flex-col items-center justify-center">
            <div @class([
                'flex h-12 w-12 items-center justify-center rounded-full',
                'bg-emerald-100 dark:bg-emerald-900/40' => $isImmediateChange,
                'bg-amber-100 dark:bg-amber-900/40' => !$isImmediateChange,
            ])>
                <x-phosphor-arrows-clockwise-duotone @class([
                    'h-6 w-6',
                    'text-emerald-600 dark:text-emerald-400' => $isImmediateChange,
                    'text-amber-600 dark:text-amber-400' => !$isImmediateChange,
                ]) />
            </div>
            <div class="mb-5 mt-3 text-center">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Switch to {{ $cycleLabel }} Billing</h3>
                <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                    @if($isImmediateChange)
                        This change takes effect immediately. You'll be charged a prorated amount for the annual plan.
                    @else
                        This change will take effect at the end of your current billing period. You'll continue on your current plan until then.
                    @endif
                </p>
            </div>
            <div class="flex w-full items-center gap-3">
                <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                <x-button wire:click="switchPlan('{{ $plan->id }}')" :color="$isImmediateChange ? 'success' : 'warning'" class="w-1/2">
                    {{ $isImmediateChange ? 'Switch Now' : 'Schedule Switch' }}
                </x-button>
            </div>
        </div>
    </x-filament::modal>
```

**Step 4: Run tests**

Run: `php artisan test --compact --filter=Checkout`
Expected: Pass.

**Step 5: Commit**

```
fix: cycle-aware current badge and cycle change buttons in checkout
```

---

### Task 4: Refactor Update Component — Cancel/Resume + Navigation Buttons

**Files:**
- Modify: `wave/src/Http/Livewire/Billing/Update.php`
- Modify: `wave/resources/views/livewire/billing/update.blade.php`

**Step 1: Add cancelSubscription() and resumeSubscription() methods to Update.php**

Add these methods to `wave/src/Http/Livewire/Billing/Update.php`:

```php
public function cancelSubscription(): void
{
    if (! $this->subscription || ! $this->subscription->valid()) {
        Notification::make()
            ->title('No active subscription found.')
            ->danger()
            ->send();

        return;
    }

    $this->subscription->cancel();
    $this->subscription->refresh();
    $this->subscription_ends_at = $this->subscription->ends_at;
    $this->cancellation_scheduled = true;

    Notification::make()
        ->title('Subscription cancelled')
        ->body('Your subscription will remain active until ' . $this->subscription->ends_at->format('F jS, Y') . '.')
        ->success()
        ->send();
}

public function resumeSubscription(): void
{
    if (! $this->subscription || ! $this->subscription->onGracePeriod()) {
        Notification::make()
            ->title('Unable to resume subscription.')
            ->danger()
            ->send();

        return;
    }

    $this->subscription->resume();
    $this->subscription->refresh();
    $this->subscription_ends_at = null;
    $this->cancellation_scheduled = false;

    Notification::make()
        ->title('Subscription resumed')
        ->body('Your subscription is active again.')
        ->success()
        ->send();
}
```

**Step 2: Rewrite update.blade.php with new action buttons**

Replace the full contents of `wave/resources/views/livewire/billing/update.blade.php`:

```blade
<div class="relative w-full space-y-3">
    {{-- Pending downgrade banner --}}
    @if($has_pending_change)
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-900/20">
            <x-phosphor-clock-countdown-duotone class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="flex-1">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                    Scheduled change to {{ $pending_plan_name }} ({{ $pending_cycle_label }})
                </p>
                @if($pending_change_date)
                    <p class="mt-0.5 text-sm text-amber-600 dark:text-amber-400">
                        Takes effect on {{ $pending_change_date }}
                    </p>
                @endif
            </div>
            <button
                wire:click="cancelPendingChange"
                wire:confirm="Are you sure you want to cancel this scheduled change?"
                class="flex-shrink-0 rounded-md px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 transition-colors hover:bg-amber-100 dark:text-amber-300 dark:ring-amber-500/30 dark:hover:bg-amber-900/40"
            >
                Cancel change
            </button>
        </div>
    @endif

    {{-- Cancellation notice --}}
    @if($cancellation_scheduled && ! is_null($subscription_ends_at))
        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-500/30 dark:bg-red-900/20">
            <x-phosphor-warning-duotone class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
            <div class="flex-1">
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                    Your subscription is scheduled to cancel on {{ \Carbon\Carbon::parse($subscription_ends_at)->format('F jS, Y') }}.
                </p>
                <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">
                    You'll continue to have access until then.
                </p>
            </div>
            <button
                wire:click="resumeSubscription"
                class="flex-shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
            >
                Resume Subscription
            </button>
        </div>
    @endif

    {{-- Action buttons --}}
    <div class="flex flex-wrap items-center gap-2">
        <x-button :href="route('settings.subscription.change-plan')" tag="a">
            <x-phosphor-arrows-clockwise class="mr-1.5 h-4 w-4" />
            Change Plan
        </x-button>
        <x-button :href="route('settings.subscription.payment-methods')" tag="a" color="gray">
            <x-phosphor-credit-card class="mr-1.5 h-4 w-4" />
            Payment Methods
        </x-button>
    </div>

    {{-- Cancel subscription link --}}
    @if(! $cancellation_scheduled)
        <div class="pt-1">
            <x-filament::modal width="lg" id="cancel-subscription-modal">
                <x-slot name="trigger">
                    <button type="button" class="text-sm text-zinc-400 underline-offset-2 transition-colors hover:text-red-500 hover:underline dark:text-zinc-500 dark:hover:text-red-400">
                        Cancel subscription
                    </button>
                </x-slot>
                <div class="relative flex w-full flex-col items-center justify-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                        <x-phosphor-warning-duotone class="h-6 w-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="mb-5 mt-3 text-center">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Cancel your subscription?</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                            Your subscription will remain active until the end of your current billing period. After that, you'll lose access to your plan features.
                        </p>
                    </div>
                    <div class="flex w-full items-center gap-3">
                        <x-button x-on:click="$dispatch('close-modal', { id: 'cancel-subscription-modal' })" color="gray" class="w-1/2">Keep My Plan</x-button>
                        <x-button wire:click="cancelSubscription" color="danger" class="w-1/2">Cancel Subscription</x-button>
                    </div>
                </div>
            </x-filament::modal>
        </div>
    @endif
</div>
```

**Step 3: Run tests**

Run: `php artisan test --compact --filter=Billing`
Expected: Pass.

**Step 4: Commit**

```
feat: add in-app cancel/resume subscription and navigation buttons
```

---

### Task 5: Refactor Subscription Page — Remove Plan Cards Section

**Files:**
- Modify: `resources/themes/anchor/pages/settings/subscription.blade.php:165-177`

**Step 1: Remove the "Switch Plan Section" from subscription.blade.php**

Remove lines 165-177 (the entire `#available-plans` div and `<livewire:billing.checkout :change="true" />`):

```blade
{{-- Switch Plan Section --}}
<div id="available-plans" class="mt-8">
    <div class="mb-5 flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
            <x-phosphor-arrows-clockwise-duotone class="h-5 w-5 text-zinc-500 dark:text-zinc-400" />
        </div>
        <div>
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Change Plan</h3>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">Upgrades apply immediately. Downgrades take effect at the end of your billing period.</p>
        </div>
    </div>
    <livewire:billing.checkout :change="true" />
</div>
```

Remove this entire block. The plan overview card and `<livewire:billing.update />` stay.

**Step 2: Run tests**

Run: `php artisan test --compact --filter=subscription`
Expected: Pass.

**Step 3: Commit**

```
refactor: remove plan cards from subscription overview page
```

---

### Task 6: Create Change Plan Folio Page

**Files:**
- Create: `resources/themes/anchor/pages/settings/subscription/change-plan.blade.php`

**Step 1: Create the directory and Folio page**

Run: `mkdir -p resources/themes/anchor/pages/settings/subscription`

Create `resources/themes/anchor/pages/settings/subscription/change-plan.blade.php`:

```blade
<?php

    use function Laravel\Folio\{middleware, name};

    middleware(['auth', 'verified']);
    name('settings.subscription.change-plan');

?>

<x-layouts.app>
    <x-app.settings-layout
        title="Change Plan"
        description="Compare plans and switch anytime. Changes are prorated."
    >
        @role('admin')
            <x-app.alert id="admin_subscription_notice" :dismissable="false" type="info">
                You are logged in as an admin and have full access. Authenticate with a different user to see the plan change process.
            </x-app.alert>
        @else
            <div class="mb-6">
                <a href="{{ route('settings.subscription') }}" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                    <x-phosphor-arrow-left class="h-4 w-4" />
                    Back to Subscription
                </a>
            </div>

            @subscriber
                <livewire:billing.checkout :change="true" />
            @endsubscriber

            @notsubscriber
                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <x-phosphor-credit-card-duotone class="h-6 w-6 text-zinc-400" />
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No active subscription</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Choose a plan below to get started.</p>
                </div>
                <livewire:billing.checkout />
            @endnotsubscriber
        @endrole
    </x-app.settings-layout>
</x-layouts.app>
```

**Step 2: Verify route resolves**

Run: `php artisan route:list --name=settings.subscription.change-plan`
Expected: Shows the Folio route.

**Step 3: Commit**

```
feat: create Change Plan Folio sub-page
```

---

### Task 7: Create Payment Methods Livewire Component

**Files:**
- Create: `wave/src/Http/Livewire/Billing/PaymentMethods.php`
- Modify: `wave/src/WaveServiceProvider.php:254-258` (register component)

**Step 1: Create the Livewire component**

Create `wave/src/Http/Livewire/Billing/PaymentMethods.php`:

```php
<?php

namespace Wave\Http\Livewire\Billing;

use Filament\Notifications\Notification;
use Livewire\Component;
use Wave\Http\Livewire\Billing\Concerns\EnsuresBillingContextAccess;

class PaymentMethods extends Component
{
    use EnsuresBillingContextAccess;

    public bool $showAddForm = false;

    public string $setupIntentClientSecret = '';

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount(): void
    {
        //
    }

    public function getPaymentMethodsProperty(): array
    {
        $user = auth()->user();

        if (! $user->hasStripeId()) {
            return [];
        }

        $default = $user->defaultPaymentMethod();
        $defaultId = $default?->id;

        return $user->paymentMethods()->map(function ($pm) use ($defaultId) {
            return [
                'id' => $pm->id,
                'brand' => $pm->card->brand ?? 'card',
                'last4' => $pm->card->last4 ?? '****',
                'exp_month' => $pm->card->exp_month ?? '',
                'exp_year' => $pm->card->exp_year ?? '',
                'is_default' => $pm->id === $defaultId,
            ];
        })->toArray();
    }

    public function openAddForm(): void
    {
        $intent = auth()->user()->createSetupIntent();
        $this->setupIntentClientSecret = $intent->client_secret;
        $this->showAddForm = true;
    }

    public function closeAddForm(): void
    {
        $this->showAddForm = false;
        $this->setupIntentClientSecret = '';
    }

    public function addPaymentMethod(string $paymentMethodId): void
    {
        try {
            $user = auth()->user();
            $user->addPaymentMethod($paymentMethodId);

            if (! $user->hasDefaultPaymentMethod()) {
                $user->updateDefaultPaymentMethod($paymentMethodId);
            }

            $this->showAddForm = false;
            $this->setupIntentClientSecret = '';

            Notification::make()
                ->title('Payment method added')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to add payment method: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function setDefaultPaymentMethod(string $paymentMethodId): void
    {
        try {
            auth()->user()->updateDefaultPaymentMethod($paymentMethodId);

            Notification::make()
                ->title('Default payment method updated')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to update default payment method.')
                ->danger()
                ->send();
        }
    }

    public function deletePaymentMethod(string $paymentMethodId): void
    {
        $user = auth()->user();
        $default = $user->defaultPaymentMethod();

        if ($default && $default->id === $paymentMethodId && $user->subscribed('default')) {
            Notification::make()
                ->title('Cannot remove your default payment method while you have an active subscription.')
                ->danger()
                ->send();

            return;
        }

        try {
            $user->deletePaymentMethod($paymentMethodId);

            Notification::make()
                ->title('Payment method removed')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to remove payment method.')
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('wave::livewire.billing.payment-methods');
    }
}
```

**Step 2: Register the component in WaveServiceProvider**

In `wave/src/WaveServiceProvider.php`, add to `loadLivewireComponents()`:

```php
Livewire::component('billing.payment-methods', PaymentMethods::class);
```

And add the import at the top of the file:

```php
use Wave\Http\Livewire\Billing\PaymentMethods;
```

**Step 3: Commit**

```
feat: create PaymentMethods Livewire component
```

---

### Task 8: Create Payment Methods Blade View

**Files:**
- Create: `wave/resources/views/livewire/billing/payment-methods.blade.php`

**Step 1: Create the Blade view**

Create `wave/resources/views/livewire/billing/payment-methods.blade.php`:

```blade
<div class="space-y-6">
    {{-- Payment method list --}}
    @if(count($this->paymentMethods) > 0)
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach($this->paymentMethods as $method)
                <div class="flex items-center gap-4 bg-white px-5 py-4 dark:bg-zinc-900">
                    {{-- Card icon --}}
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <x-phosphor-credit-card-duotone class="h-5 w-5 text-zinc-500 dark:text-zinc-400" />
                    </div>

                    {{-- Card details --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold capitalize text-zinc-900 dark:text-zinc-100">
                                {{ $method['brand'] }}
                            </p>
                            <span class="font-mono text-sm text-zinc-500 dark:text-zinc-400">
                                &bull;&bull;&bull;&bull; {{ $method['last4'] }}
                            </span>
                            @if($method['is_default'])
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/30">
                                    Default
                                </span>
                            @endif
                        </div>
                        @if($method['exp_month'] && $method['exp_year'])
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                Expires {{ str_pad($method['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ $method['exp_year'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1">
                        @if(! $method['is_default'])
                            <button
                                wire:click="setDefaultPaymentMethod('{{ $method['id'] }}')"
                                wire:confirm="Set this card as your default payment method?"
                                class="rounded-md px-2.5 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                            >
                                Set default
                            </button>
                            <button
                                wire:click="deletePaymentMethod('{{ $method['id'] }}')"
                                wire:confirm="Are you sure you want to remove this payment method?"
                                class="rounded-md px-2.5 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                            >
                                Remove
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <x-phosphor-credit-card-duotone class="h-6 w-6 text-zinc-400" />
            </div>
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No payment methods</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Add a payment method to manage your subscription billing.</p>
        </div>
    @endif

    {{-- Add payment method --}}
    @if($showAddForm)
        <div
            x-data="{
                stripe: null,
                cardElement: null,
                cardError: '',
                processing: false,
                init() {
                    this.stripe = Stripe('{{ config('cashier.key') }}');
                    const elements = this.stripe.elements();
                    this.cardElement = elements.create('card', {
                        style: {
                            base: {
                                fontSize: '16px',
                                color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
                                '::placeholder': { color: '#a1a1aa' },
                            },
                        },
                    });
                    this.cardElement.mount(this.$refs.cardElement);
                    this.cardElement.on('change', (event) => {
                        this.cardError = event.error ? event.error.message : '';
                    });
                },
                async submitCard() {
                    this.processing = true;
                    this.cardError = '';

                    const { setupIntent, error } = await this.stripe.confirmCardSetup(
                        '{{ $setupIntentClientSecret }}',
                        { payment_method: { card: this.cardElement } }
                    );

                    if (error) {
                        this.cardError = error.message;
                        this.processing = false;
                        return;
                    }

                    $wire.addPaymentMethod(setupIntent.payment_method);
                    this.processing = false;
                }
            }"
            class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
        >
            <h4 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Add a new card</h4>

            <div x-ref="cardElement" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800"></div>

            <template x-if="cardError">
                <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="cardError"></p>
            </template>

            <div class="mt-4 flex items-center gap-3">
                <x-button @click="submitCard()" ::disabled="processing" color="primary">
                    <span x-show="!processing">Add Card</span>
                    <span x-show="processing" x-cloak>Processing...</span>
                </x-button>
                <x-button wire:click="closeAddForm" color="gray">Cancel</x-button>
            </div>
        </div>
    @else
        <x-button wire:click="openAddForm" color="gray">
            <x-phosphor-plus class="mr-1.5 h-4 w-4" />
            Add Payment Method
        </x-button>
    @endif
</div>
```

**Step 2: Commit**

```
feat: create payment methods Blade view with Stripe Elements
```

---

### Task 9: Create Payment Methods Folio Page

**Files:**
- Create: `resources/themes/anchor/pages/settings/subscription/payment-methods.blade.php`

**Step 1: Create the Folio page**

Create `resources/themes/anchor/pages/settings/subscription/payment-methods.blade.php`:

```blade
<?php

    use function Laravel\Folio\{middleware, name};

    middleware(['auth', 'verified']);
    name('settings.subscription.payment-methods');

?>

<x-layouts.app>
    @push('head')
        <script src="https://js.stripe.com/v3/"></script>
    @endpush

    <x-app.settings-layout
        title="Payment Methods"
        description="Manage your saved payment methods"
    >
        <div class="mb-6">
            <a href="{{ route('settings.subscription') }}" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <x-phosphor-arrow-left class="h-4 w-4" />
                Back to Subscription
            </a>
        </div>

        <livewire:billing.payment-methods />
    </x-app.settings-layout>
</x-layouts.app>
```

**Step 2: Verify `@push('head')` is supported in the app layout**

Check `resources/themes/anchor/components/layouts/app.blade.php` (or whichever layout is used) to ensure it has `@stack('head')` in the `<head>` section. If not, add `@stack('head')` before `</head>`.

**Step 3: Verify route resolves**

Run: `php artisan route:list --name=settings.subscription.payment-methods`
Expected: Shows the Folio route.

**Step 4: Commit**

```
feat: create Payment Methods Folio sub-page
```

---

### Task 10: Write Tests

**Files:**
- Create: `tests/Feature/SubscriptionPageTest.php`
- Modify: existing test files if any reference the old subscription page structure

**Step 1: Create test file**

Run: `php artisan make:test SubscriptionPageTest --pest`

Write tests:

```php
<?php

use App\Models\User;
use Wave\Plan;
use Wave\Subscription;

beforeEach(function () {
    $this->plan = Plan::factory()->create([
        'name' => 'Premium',
        'monthly_price' => 29,
        'yearly_price' => 290,
        'monthly_price_id' => 'price_monthly_test',
        'yearly_price_id' => 'price_yearly_test',
        'active' => true,
    ]);
});

it('shows subscription overview for subscribed user', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $this->plan->id,
        'cycle' => 'year',
        'stripe_status' => 'active',
    ]);

    $this->actingAs($user)
        ->get('/settings/subscription')
        ->assertOk()
        ->assertSee('Premium Plan')
        ->assertDontSee('id="available-plans"');
});

it('shows change plan page for subscribed user', function () {
    $user = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $this->plan->id,
        'cycle' => 'year',
        'stripe_status' => 'active',
    ]);

    $this->actingAs($user)
        ->get('/settings/subscription/change-plan')
        ->assertOk()
        ->assertSee('Back to Subscription');
});

it('shows payment methods page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/subscription/payment-methods')
        ->assertOk()
        ->assertSee('Payment Methods');
});

it('requires auth for subscription sub-pages', function () {
    $this->get('/settings/subscription/change-plan')
        ->assertRedirect('/auth/login');

    $this->get('/settings/subscription/payment-methods')
        ->assertRedirect('/auth/login');
});
```

**Step 2: Run tests**

Run: `php artisan test --compact --filter=SubscriptionPage`
Expected: All pass.

**Step 3: Update route datasets if needed**

Check `tests/Datasets/Routes.php` and `tests/Datasets/AuthRoutes.php` — add the new routes if they have route datasets for authenticated pages.

**Step 4: Run full test suite**

Run: `php artisan test --compact`
Expected: All pass. Fix any regressions from the removed plan cards section or changed route references.

**Step 5: Commit**

```
test: add subscription sub-page tests
```

---

### Task 11: Run Pint + Final Verification

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Fix any formatting issues**

Apply any auto-fixes.

**Step 3: Run full test suite**

Run: `php artisan test --compact`
Expected: All pass.

**Step 4: Commit all formatting fixes**

```
style: apply pint formatting
```
