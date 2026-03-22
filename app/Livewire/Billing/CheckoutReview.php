<?php

namespace App\Livewire\Billing;

use App\Livewire\Billing\Concerns\EnsuresBillingContextAccess;
use App\Models\Organization;
use App\Models\Plan;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Livewire\Component;
use Stripe\StripeClient;

class CheckoutReview extends Component
{
    use EnsuresBillingContextAccess;

    public Plan $plan;

    public string $billing_cycle = 'month';

    public string $coupon_code = '';

    public bool $coupon_applied = false;

    public string $coupon_description = '';

    public bool $terms_accepted = false;

    public int $seat_quantity = 1;

    public int $minimum_seat_quantity = 1;

    public int $maximum_seat_quantity = 100;

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount(Plan $plan, string $cycle = 'month'): void
    {
        if (! $plan->active) {
            $this->redirectToSubscription('This plan is no longer available.');

            return;
        }

        $this->billing_cycle = in_array($cycle, ['month', 'year'], true) ? $cycle : 'month';

        $priceId = $this->billing_cycle === 'month' ? $plan->monthly_price_id : $plan->yearly_price_id;
        if (empty($priceId)) {
            $this->redirectToSubscription('This billing cycle is not available for the selected plan.');

            return;
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
        $this->seat_quantity = max($this->seat_quantity, $minimumSeatQuantity);
    }

    public function applyCoupon(): void
    {
        $code = trim($this->coupon_code);

        if ($code === '') {
            $this->coupon_applied = false;
            $this->coupon_description = '';

            return;
        }

        $user = auth()->user();

        if (Str::startsWith($code, 'promo_') || Str::startsWith($code, 'coupon_')) {
            $this->coupon_applied = true;
            $this->coupon_description = 'Discount will be applied at checkout.';

            return;
        }

        $promotionCode = $user->findActivePromotionCode($code);

        if ($promotionCode) {
            $this->coupon_applied = true;
            $this->coupon_description = $this->describePromotionCode($promotionCode);

            return;
        }

        $this->coupon_applied = false;
        $this->coupon_description = '';

        Notification::make()
            ->title('Invalid or expired coupon code.')
            ->danger()
            ->send();
    }

    public function removeCoupon(): void
    {
        $this->coupon_code = '';
        $this->coupon_applied = false;
        $this->coupon_description = '';
    }

    protected function describePromotionCode($promotionCode): string
    {
        $coupon = $promotionCode->coupon ?? null;
        if (! $coupon) {
            return 'Discount will be applied at checkout.';
        }

        if ($coupon->percent_off) {
            return $coupon->percent_off.'% off'.($coupon->duration === 'once' ? ' (first payment)' : '');
        }

        if ($coupon->amount_off) {
            $amount = number_format($coupon->amount_off / 100, 2);

            return currencySymbol($coupon->currency).$amount.' off'.($coupon->duration === 'once' ? ' (first payment)' : '');
        }

        return 'Discount will be applied at checkout.';
    }

    public function proceedToPayment()
    {
        if (! $this->terms_accepted) {
            Notification::make()
                ->title('Please accept the terms and conditions to continue.')
                ->danger()
                ->send();

            return;
        }

        $billingContext = auth()->user()->getBillingContext();
        if ($billingContext['type'] === 'organization') {
            $this->validate([
                'seat_quantity' => 'required|integer|min:'.$this->minimum_seat_quantity.'|max:'.$this->maximum_seat_quantity,
            ], [
                'seat_quantity.min' => 'Seat quantity must cover all occupied seats.',
            ]);
        }

        $user = auth()->user();
        $this->normalizeCheckoutRedisplayablePaymentMethods($user);

        $priceId = $this->billing_cycle === 'month' ? $this->plan->monthly_price_id : $this->plan->yearly_price_id;
        $seatQuantity = (int) $this->seat_quantity;

        $subscriptionMetadata = [
            'user_id' => (string) $user->id,
            'billable_type' => $billingContext['type'],
            'billable_id' => (string) $billingContext['id'],
            'plan_id' => (string) $this->plan->id,
            'billing_cycle' => $this->billing_cycle,
            'seat_quantity' => (string) $seatQuantity,
        ];

        try {
            $checkout = $user->newSubscription('default', $priceId)
                ->quantity($seatQuantity)
                ->withMetadata($subscriptionMetadata);

            if (! empty($this->plan->trial_days)) {
                $checkout->trialDays((int) $this->plan->trial_days);
            }

            $discountApplied = false;
            $couponCode = trim($this->coupon_code);

            if ($couponCode !== '' && $this->coupon_applied) {
                $discountApplied = $this->applyCheckoutDiscount($checkout, $couponCode, $user);
            } elseif (! empty($this->plan->stripe_promotion_code)) {
                $discountApplied = $this->applyCheckoutDiscount($checkout, (string) $this->plan->stripe_promotion_code, $user);
            } elseif (! empty($this->plan->stripe_coupon_id)) {
                $discountApplied = $this->applyCheckoutDiscount($checkout, (string) $this->plan->stripe_coupon_id, $user);
            }

            if (! $discountApplied) {
                $checkout->allowPromotionCodes();
            }

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
            logger()->error('Stripe checkout initialization failed', [
                'user_id' => $user->id,
                'plan_id' => $this->plan->id,
                'billing_cycle' => $this->billing_cycle,
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

    public function getUnitPriceProperty(): string
    {
        return $this->billing_cycle === 'month'
            ? $this->plan->monthly_price
            : $this->plan->yearly_price;
    }

    public function getTotalPriceProperty(): float
    {
        return (float) $this->unitPrice * $this->seat_quantity;
    }

    public function getIsOrgBillingProperty(): bool
    {
        return auth()->user()->getBillingContext()['type'] === 'organization';
    }

    public function getYearlySavingsProperty(): ?float
    {
        if ($this->billing_cycle !== 'year' || empty($this->plan->monthly_price) || empty($this->plan->yearly_price)) {
            return null;
        }

        $monthlyTotal = (float) $this->plan->monthly_price * 12;
        $yearlyTotal = (float) $this->plan->yearly_price;

        if ($monthlyTotal <= $yearlyTotal) {
            return null;
        }

        return $monthlyTotal - $yearlyTotal;
    }

    public function getEffectiveMonthlyPriceProperty(): ?string
    {
        if ($this->billing_cycle !== 'year' || empty($this->plan->yearly_price)) {
            return null;
        }

        return number_format((float) $this->plan->yearly_price / 12, 2);
    }

    protected function redirectToSubscription(string $message): void
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        $this->redirect(route('settings.subscription'));
    }

    public function render()
    {
        return view('wave.livewire.billing.checkout-review');
    }
}
