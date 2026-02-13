<?php

namespace Wave\Http\Livewire\Billing\Concerns;

use Filament\Notifications\Notification;

trait EnsuresBillingContextAccess
{
    protected function ensureBillingContextAccess(): void
    {
        if (auth()->check() && auth()->user()->canManageBillingContext()) {
            return;
        }

        Notification::make()
            ->title('You are not authorized to manage billing for this account.')
            ->danger()
            ->send();

        $this->redirect(route('settings.subscription'));
    }
}
