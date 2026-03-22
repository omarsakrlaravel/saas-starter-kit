<?php

namespace App\Livewire\Billing;

use App\Livewire\Billing\Concerns\EnsuresBillingContextAccess;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanChangeResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use Stripe\StripeClient;

class Checkout extends Component
{
    use EnsuresBillingContextAccess;

    public $billing_cycle_available = 'month'; // month, year, or both;

    public $billing_cycle_selected = 'month';

    public $change = false;

    public $userSubscription = null;

    public $userPlan = null;

    public $seat_quantity = 1;

    public $minimum_seat_quantity = 1;

    public $maximum_seat_quantity = 100;

    public $coupon_code = '';

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount()
    {
        $this->updateCycleBasedOnPlans();

        if ($this->change) {
            $this->userSubscription = auth()->user()->latestSubscription();
            $this->userPlan = $this->userSubscription?->plan;
        }

        if ($this->change && $this->userSubscription) {
            $this->billing_cycle_selected = $this->userSubscription->cycle;
        }

        $this->initializeSeatQuantity();
    }

    protected function initializeSeatQuantity(): void
    {
        $billingContext = auth()->user()->getBillingContext();

        if ($billingContext['type'] !== 'organization') {
            $this->seat_quantity = 1;
            $this->minimum_seat_quantity = 1;

            return;
        }

        $organization = Organization::find($billingContext['id']);
        $minimumSeatQuantity = max((int) ($organization?->occupiedSeatCount() ?? 0), 1);

        $this->minimum_seat_quantity = $minimumSeatQuantity;
        if ($this->change && $this->userSubscription) {
            $this->seat_quantity = max((int) $this->userSubscription->quantity, $minimumSeatQuantity);

            return;
        }

        $this->seat_quantity = max((int) $this->seat_quantity, $minimumSeatQuantity);
    }

    protected function resolveSeatQuantity(): int
    {
        $validated = $this->validate([
            'seat_quantity' => 'required|integer|min:'.$this->minimum_seat_quantity.'|max:'.$this->maximum_seat_quantity,
        ], [
            'seat_quantity.min' => 'Seat quantity must cover all occupied seats.',
        ]);

        return (int) $validated['seat_quantity'];
    }

    public function redirectToStripeCheckout(Plan $plan)
    {
        $user = auth()->user();
        $this->normalizeCheckoutRedisplayablePaymentMethods($user);
        $billingContext = auth()->user()->getBillingContext();
        $seatQuantity = $this->resolveSeatQuantity();
        $priceId = $this->billing_cycle_selected === 'month' ? $plan->monthly_price_id : $plan->yearly_price_id;
        $subscriptionMetadata = [
            'user_id' => (string) $user->id,
            'billable_type' => $billingContext['type'],
            'billable_id' => (string) $billingContext['id'],
            'plan_id' => (string) $plan->id,
            'billing_cycle' => $this->billing_cycle_selected,
            'seat_quantity' => (string) $seatQuantity,
        ];

        if (empty($priceId)) {
            Notification::make()
                ->title('This billing cycle is not available for the selected plan.')
                ->danger()
                ->send();

            return;
        }

        try {
            $checkout = $user->newSubscription('default', $priceId)
                ->quantity($seatQuantity)
                ->withMetadata($subscriptionMetadata);

            if (! empty($plan->trial_days)) {
                $checkout->trialDays((int) $plan->trial_days);
            }

            $discountApplied = false;
            $couponCode = trim((string) $this->coupon_code);

            if ($couponCode !== '') {
                $discountApplied = $this->applyCheckoutDiscount($checkout, $couponCode, $user);

                if (! $discountApplied) {
                    Notification::make()
                        ->title('Coupon code is invalid or inactive.')
                        ->danger()
                        ->send();

                    return;
                }
            } elseif (! empty($plan->stripe_promotion_code)) {
                $discountApplied = $this->applyCheckoutDiscount($checkout, (string) $plan->stripe_promotion_code, $user);
            } elseif (! empty($plan->stripe_coupon_id)) {
                $discountApplied = $this->applyCheckoutDiscount($checkout, (string) $plan->stripe_coupon_id, $user);
            }

            if (! $discountApplied) {
                $checkout->allowPromotionCodes();
            }

            $checkoutSession = $checkout->checkout([
                'success_url' => route('subscription.welcome').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('settings.subscription'),
                // Reuse the customer's saved default card when available.
                'payment_method_collection' => 'if_required',
                'saved_payment_method_options' => [
                    'allow_redisplay_filters' => ['always', 'limited', 'unspecified'],
                ],
                'metadata' => $subscriptionMetadata,
            ]);

            $checkoutUrl = $checkoutSession->url;
            if (! is_string($checkoutUrl) || $checkoutUrl === '') {
                throw new \RuntimeException('Stripe checkout session URL was not returned.');
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            logger()->error('Stripe checkout initialization failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $this->billing_cycle_selected,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Unable to start checkout: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function normalizeCheckoutRedisplayablePaymentMethods($user): void
    {
        if (! $user->hasStripeId()) {
            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $paymentMethods = $stripe->customers->allPaymentMethods($user->stripe_id, [
                'type' => 'card',
                'limit' => 20,
            ]);

            foreach ($paymentMethods->data as $paymentMethod) {
                $paymentMethodId = $paymentMethod->id ?? null;
                $allowRedisplay = $paymentMethod->allow_redisplay ?? null;

                if (! is_string($paymentMethodId) || $paymentMethodId === '' || $allowRedisplay === 'always') {
                    continue;
                }

                $stripe->paymentMethods->update($paymentMethodId, [
                    'allow_redisplay' => 'always',
                ]);
            }
        } catch (\Throwable) {
            // Do not block checkout if normalization fails.
        }
    }

    protected function applyCheckoutDiscount($checkout, string $value, $user): bool
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }

        if (Str::startsWith($normalized, 'promo_')) {
            $checkout->withPromotionCode($normalized);

            return true;
        }

        if (Str::startsWith($normalized, 'coupon_')) {
            $checkout->withCoupon($normalized);

            return true;
        }

        $promotionCode = $user->findActivePromotionCode($normalized);
        if ($promotionCode) {
            $checkout->withPromotionCode($promotionCode->id);

            return true;
        }

        return false;
    }

    public function updateCycleBasedOnPlans()
    {
        $plans = Plan::where('active', 1)->get();
        $hasMonthly = false;
        $hasYearly = false;
        foreach ($plans as $plan) {
            if (! empty($plan->monthly_price_id)) {
                $hasMonthly = true;
            }
            if (! empty($plan->yearly_price_id)) {
                $hasYearly = true;
            }
        }
        if ($hasMonthly && $hasYearly) {
            $this->billing_cycle_available = 'both';
        } elseif ($hasMonthly) {
            $this->billing_cycle_available = 'month';
        } elseif ($hasYearly) {
            $this->billing_cycle_available = 'year';
            $this->billing_cycle_selected = 'year';
        }
    }

    /**
     * Determine the change type for a given plan and currently selected cycle.
     *
     * @return 'upgrade'|'downgrade'|'same'|'cycle_change'
     */
    public function getChangeType(Plan $plan): string
    {
        if (! $this->userSubscription) {
            return 'upgrade';
        }

        return app(PlanChangeResolver::class)->resolve(
            $this->userSubscription,
            $plan,
            $this->billing_cycle_selected,
        );
    }

    public function switchPlan(Plan $plan, ?string $targetCycle = null)
    {
        $subscription = auth()->user()->latestSubscription();
        $selectedCycle = in_array($targetCycle, ['month', 'year'], true)
            ? $targetCycle
            : $this->billing_cycle_selected;

        if (! $subscription) {
            Notification::make()
                ->title('No active subscription found to update.')
                ->danger()
                ->send();

            return;
        }

        if ($subscription->ended()) {
            Notification::make()
                ->title('No active subscription found to update.')
                ->danger()
                ->send();

            return;
        }

        if (! $subscription->valid()) {
            Notification::make()
                ->title('Your subscription has a payment issue. Please update your payment method and try again.')
                ->danger()
                ->send();

            return;
        }

        $changeType = app(PlanChangeResolver::class)->resolve(
            $subscription,
            $plan,
            $selectedCycle,
        );

        if ($changeType === 'same') {
            Notification::make()
                ->title('You are already on this plan and billing cycle.')
                ->warning()
                ->send();

            return;
        }

        $priceId = $selectedCycle === 'month' ? $plan->monthly_price_id : $plan->yearly_price_id;
        if (empty($priceId)) {
            Notification::make()
                ->title('This billing cycle is not available for the selected plan.')
                ->danger()
                ->send();

            return;
        }

        if ($changeType === 'upgrade') {
            return $this->applyImmediateUpgrade($subscription, $plan, $priceId, $selectedCycle);
        }

        if ($changeType === 'cycle_change') {
            if ($selectedCycle === 'year') {
                return $this->applyImmediateUpgrade($subscription, $plan, $priceId, $selectedCycle);
            }

            return $this->scheduleDowngrade($subscription, $plan, $selectedCycle);
        }

        return $this->scheduleDowngrade($subscription, $plan, $selectedCycle);
    }

    protected function applyImmediateUpgrade($subscription, Plan $plan, string $priceId, string $targetCycle)
    {
        $user = auth()->user();

        // Try charging the saved payment method first.
        if ($this->trySwapWithSavedMethod($subscription, $plan, $priceId, $targetCycle)) {
            return redirect()->to('/settings/subscription')->with(['update' => true]);
        }

        // No saved card or payment failed — fall back to Stripe Checkout.
        return $this->redirectToStripeCheckoutForSwap($user, $plan, $priceId, $targetCycle);
    }

    protected function trySwapWithSavedMethod($subscription, Plan $plan, string $priceId, string $targetCycle): bool
    {
        $user = auth()->user();

        if (! $user->hasStripeId() || $user->defaultPaymentMethod() === null) {
            return false;
        }

        try {
            $subscription->errorIfPaymentFails()->swapAndInvoice($priceId);

            $subscription->update([
                'plan_id' => $plan->id,
                'cycle' => $targetCycle,
                'pending_plan_id' => null,
                'pending_cycle' => null,
                'pending_change_scheduled_at' => null,
            ]);

            $subscription->clearBillableCache();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function redirectToStripeCheckoutForSwap($user, Plan $plan, string $priceId, string $targetCycle)
    {
        $this->normalizeCheckoutRedisplayablePaymentMethods($user);
        $billingContext = $user->getBillingContext();
        $seatQuantity = (int) ($user->latestSubscription()?->quantity ?? 1);

        $subscriptionMetadata = [
            'user_id' => (string) $user->id,
            'billable_type' => $billingContext['type'],
            'billable_id' => (string) $billingContext['id'],
            'plan_id' => (string) $plan->id,
            'billing_cycle' => $targetCycle,
            'seat_quantity' => (string) $seatQuantity,
        ];

        try {
            $checkout = $user->newSubscription('default', $priceId)
                ->quantity($seatQuantity)
                ->withMetadata($subscriptionMetadata);

            $checkoutSession = $checkout->checkout([
                'success_url' => route('subscription.welcome').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('settings.subscription'),
                'payment_method_collection' => 'if_required',
                'saved_payment_method_options' => [
                    'allow_redisplay_filters' => ['always', 'limited', 'unspecified'],
                ],
                'metadata' => $subscriptionMetadata,
            ]);

            $checkoutUrl = $checkoutSession->url;
            if (! is_string($checkoutUrl) || $checkoutUrl === '') {
                throw new \RuntimeException('Stripe checkout session URL was not returned.');
            }

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            logger()->error('Stripe checkout for plan upgrade failed', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Unable to upgrade your plan right now. Please try again.')
                ->danger()
                ->send();
        }
    }

    protected function scheduleDowngrade($subscription, Plan $plan, string $targetCycle)
    {
        $scheduledAt = $this->getScheduledChangeDate($subscription);
        $currentCycleLabel = $subscription->cycle === 'year' ? 'yearly' : 'monthly';

        $subscription->update([
            'pending_plan_id' => $plan->id,
            'pending_cycle' => $targetCycle,
            'pending_change_scheduled_at' => $scheduledAt,
        ]);

        Notification::make()
            ->title('Downgrade scheduled')
            ->body('Your plan will change to '.$plan->name.' ('.$targetCycle.'ly) at the end of your current '.$currentCycleLabel.' billing period.')
            ->success()
            ->send();

        return redirect()->to('/settings/subscription')->with(['downgrade_scheduled' => true]);
    }

    protected function getScheduledChangeDate(Subscription $subscription): Carbon
    {
        $scheduledAt = $subscription->next_payment_at ?? $subscription->ends_at;
        if ($scheduledAt !== null) {
            return $scheduledAt;
        }

        return match ($subscription->cycle) {
            'year' => now()->addYear(),
            default => now()->addMonth(),
        };
    }

    public function cancelPendingChange()
    {
        $subscription = auth()->user()->latestSubscription();

        if ($subscription && $subscription->hasPendingChange()) {
            $subscription->cancelPendingChange();

            Notification::make()
                ->title('Scheduled change cancelled')
                ->body('Your plan will remain unchanged.')
                ->success()
                ->send();
        }

        return redirect()->to('/settings/subscription');
    }

    public function render()
    {
        return view('livewire.billing.checkout', [
            'plans' => Plan::getActivePlans(),
        ]);
    }
}
