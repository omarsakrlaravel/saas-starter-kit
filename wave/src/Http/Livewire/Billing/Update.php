<?php

namespace Wave\Http\Livewire\Billing;

use Filament\Notifications\Notification;
use Livewire\Component;
use Wave\Http\Livewire\Billing\Concerns\EnsuresBillingContextAccess;

class Update extends Component
{
    use EnsuresBillingContextAccess;

    public $cancellation_scheduled = false;

    public $subscription_ends_at;

    public $subscription;

    public $has_pending_change = false;

    public $pending_plan_name = '';

    public $pending_cycle_label = '';

    public $pending_change_date = '';

    public function boot(): void
    {
        $this->ensureBillingContextAccess();
    }

    public function mount(): void
    {
        $this->subscription = auth()->user()->latestSubscription();
        $this->subscription_ends_at = $this->subscription?->ends_at;
        $this->cancellation_scheduled = ! is_null($this->subscription_ends_at);

        if ($this->subscription && $this->subscription->hasPendingChange()) {
            $this->has_pending_change = true;
            $pendingPlan = $this->subscription->pendingPlan;
            $this->pending_plan_name = $pendingPlan?->name ?? 'Unknown';
            $this->pending_cycle_label = $this->subscription->pending_cycle === 'year' ? 'Yearly' : 'Monthly';
            $this->pending_change_date = $this->subscription->pending_change_scheduled_at
                ? $this->subscription->pending_change_scheduled_at->format('M j, Y')
                : '';
        }
    }

    public function cancelPendingChange(): void
    {
        if ($this->subscription && $this->subscription->hasPendingChange()) {
            $this->subscription->cancelPendingChange();
            $this->has_pending_change = false;

            Notification::make()
                ->title('Scheduled change cancelled')
                ->body('Your plan will remain unchanged.')
                ->success()
                ->send();
        }
    }

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
            ->body('Your subscription will remain active until '.$this->subscription->ends_at->format('F jS, Y').'.')
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

    public function render()
    {
        return view('wave::livewire.billing.update');
    }
}
