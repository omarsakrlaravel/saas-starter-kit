<?php

namespace Wave\Http\Livewire\Billing;

use Livewire\Component;
use Wave\Http\Livewire\Billing\Concerns\EnsuresBillingContextAccess;

class Update extends Component
{
    use EnsuresBillingContextAccess;

    public $cancellation_scheduled = false;

    public $subscription_ends_at;

    public $subscription;

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount()
    {
        $this->subscription = auth()->user()->latestSubscription();
        $this->subscription_ends_at = $this->subscription?->ends_at;
        $this->cancellation_scheduled = ! is_null($this->subscription_ends_at);
    }

    public function render()
    {
        return view('wave::livewire.billing.update');
    }
}
