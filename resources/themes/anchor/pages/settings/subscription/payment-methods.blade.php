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
