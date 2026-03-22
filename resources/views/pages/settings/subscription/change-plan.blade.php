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
