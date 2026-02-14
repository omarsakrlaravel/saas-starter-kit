<?php

namespace Wave\Http\Livewire\Billing;

use App\Models\Organization;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Component;
use Wave\Http\Livewire\Billing\Concerns\EnsuresBillingContextAccess;
use Wave\Plan;

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

    public function switchPlan(Plan $plan)
    {
        $subscription = auth()->user()->latestSubscription();

        if (! $subscription || ! $subscription->valid()) {
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
            $subscription->swapAndInvoice($priceId);

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

    public function render()
    {
        return view('wave::livewire.billing.checkout', [
            'plans' => Plan::getActivePlans(),
        ]);
    }
}
