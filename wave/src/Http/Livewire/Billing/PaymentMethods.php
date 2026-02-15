<?php

namespace Wave\Http\Livewire\Billing;

use Filament\Notifications\Notification;
use Livewire\Component;
use Stripe\StripeClient;
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

        return $user->paymentMethods()
            ->get()
            ->map(function ($pm) use ($defaultId) {
                return [
                    'id' => $pm->stripe_id,
                    'brand' => $pm->brand ?? 'card',
                    'last4' => $pm->last4 ?? '****',
                    'exp_month' => $pm->exp_month ?? '',
                    'exp_year' => $pm->exp_year ?? '',
                    'is_default' => $pm->stripe_id === $defaultId,
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
            $this->markPaymentMethodForCheckoutRedisplay($user, $paymentMethodId);

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
                ->title('Failed to add payment method: '.$e->getMessage())
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

    protected function markPaymentMethodForCheckoutRedisplay($user, string $paymentMethodId): void
    {
        if (! $user->hasStripeId()) {
            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            $stripe->paymentMethods->update($paymentMethodId, [
                'allow_redisplay' => 'always',
            ]);
        } catch (\Throwable) {
            // Keep card add flow non-blocking.
        }
    }

    public function render()
    {
        return view('wave::livewire.billing.payment-methods');
    }
}
