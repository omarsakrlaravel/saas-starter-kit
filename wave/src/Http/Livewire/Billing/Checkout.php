<?php

namespace Wave\Http\Livewire\Billing;

use App\Models\Organization;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;
use Stripe\StripeClient;
use Wave\Actions\Billing\Paddle\AddSubscriptionIdFromTransaction;
use Wave\Http\Livewire\Billing\Concerns\EnsuresBillingContextAccess;
use Wave\Plan;
use Wave\Subscription;

class Checkout extends Component
{
    use EnsuresBillingContextAccess;

    public $billing_cycle_available = 'month'; // month, year, or both;

    public $billing_cycle_selected = 'month';

    public $billing_provider;

    public $paddle_url;

    public $change = false;

    public $userSubscription = null;

    public $userPlan = null;

    public $seat_quantity = 1;

    public $minimum_seat_quantity = 1;

    public $maximum_seat_quantity = 100;

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount()
    {
        $this->billing_provider = config('wave.billing_provider', 'stripe');
        $this->paddle_url = (config('wave.paddle.env') == 'sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
        $this->updateCycleBasedOnPlans();

        if ($this->change) {
            // if we are changing the user plan as opposecd to checking out the first time.
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
            $this->seat_quantity = max((int) $this->userSubscription->seats, $minimumSeatQuantity);

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
        $stripe = new StripeClient(config('wave.stripe.secret_key'));
        $billingContext = auth()->user()->getBillingContext();
        $seatQuantity = $this->resolveSeatQuantity();

        $price_id = $this->billing_cycle_selected == 'month' ? $plan->monthly_price_id : $plan->yearly_price_id ?? null;

        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => [[
                'price' => $price_id,
                'quantity' => $seatQuantity,
            ]],
            'metadata' => [
                'billable_type' => $billingContext['type'],
                'billable_id' => $billingContext['id'],
                'plan_id' => $plan->id,
                'billing_cycle' => $this->billing_cycle_selected,
                'seat_quantity' => (string) $seatQuantity,
            ],
            'mode' => 'subscription',
            'success_url' => url('subscription/welcome'),
            'cancel_url' => url('settings/subscription'),
        ]);

        return redirect()->to($checkout_session->url);
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

    #[On('savePaddleSubscription')]
    public function savePaddleSubscription($transactionId)
    {
        $subscription = app(AddSubscriptionIdFromTransaction::class)($transactionId);
        if (! is_null($subscription)) {
            return redirect()->to('/subscription/welcome');
        }

        $this->js('closeLoader()');
        Notification::make()
            ->title('Unable to obtain subscription information from payment provider.')
            ->danger()
            ->send();
    }

    #[On('verifyPaddleTransaction')]
    public function verifyPaddleTransaction($transactionId)
    {
        $billingContext = auth()->user()->getBillingContext();

        $transaction = null;

        $response = Http::withToken(config('wave.paddle.api_key'))->get($this->paddle_url.'/transactions/'.$transactionId);

        if ($response->successful()) {
            $resBody = json_decode($response->body());
            if (isset($resBody->data->status) && ($resBody->data->status == 'paid' || $resBody->data->status == 'completed' || $resBody->data->status == 'ready')) {
                $transaction = $resBody->data;
            }
        }

        if ($transaction) {
            // Proceed with processing the transaction

            if ($this->billing_cycle_selected == 'month') {
                $plan = Plan::where('monthly_price_id', $transaction->items[0]->price->id)->first();
            } else {
                $plan = Plan::where('yearly_price_id', $transaction->items[0]->price->id)->first();
            }

            if (! isset($plan->id)) {
                $this->js('Paddle.Checkout.close()');
                Notification::make()
                    ->title('Plan Price ID not found. Something went wrong during the checkout process')
                    ->success()
                    ->send();

                return;
            }

            $seatQuantity = max((int) ($transaction->items[0]->quantity ?? $this->seat_quantity), 1);

            Subscription::create([
                'billable_type' => $billingContext['type'],
                'billable_id' => $billingContext['id'],
                'plan_id' => $plan->id,
                'vendor_slug' => 'paddle',
                'vendor_transaction_id' => $transactionId,
                'vendor_customer_id' => $transaction->customer_id,
                'vendor_subscription_id' => $transaction->subscription_id,
                'cycle' => $this->billing_cycle_selected,
                'status' => 'active',
                'seats' => $seatQuantity,
            ]);

            $this->js('savePaddleSubscription("'.$transactionId.'")');

        } else {
            $this->js('Paddle.Checkout.close()');
            Notification::make()
                ->title('Error processing the transaction. Please try again.')
                ->danger()
                ->send();
        }

        // if we got here something went wrong and we need to let the user know.

    }

    public function switchPlan(Plan $plan)
    {
        $subscription = auth()->user()->latestSubscription();

        if (! $subscription) {
            return;
        }

        $price_id = ($this->billing_cycle_selected == 'month') ? $plan->monthly_price_id : $plan->yearly_price_id ?? null;

        $response = Http::withToken(config('wave.paddle.api_key'))->patch(
            $this->paddle_url.'/subscriptions/'.$subscription->vendor_subscription_id,
            [
                'items' => [
                    [
                        'price_id' => $price_id,
                        'quantity' => $subscription->seats,
                    ],
                ],
                'proration_billing_mode' => 'prorated_immediately',
            ]
        );

        if ($response->successful()) {
            $subscription->plan_id = $plan->id;
            $subscription->cycle = $this->billing_cycle_selected;
            $subscription->save();

            return redirect()->to('/settings/subscription')->with(['update' => true]);
        }
    }

    public function render()
    {
        return view('wave::livewire.billing.checkout', [
            'plans' => Plan::getActivePlans(),
        ]);
    }
}
