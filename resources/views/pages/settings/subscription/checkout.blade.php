<?php

    use App\Models\Plan;
    use function Laravel\Folio\{middleware, name};

    middleware(['auth', 'verified']);
    name('settings.subscription.checkout');

?>

<x-layouts.app>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:py-12">
        @role('admin')
            <x-app.alert id="admin_subscription_notice" :dismissable="false" type="info">
                You are logged in as an admin. Authenticate with a different user to see the checkout process.
            </x-app.alert>
        @else
            @subscriber
                <div class="mx-auto max-w-lg rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">You already have an active subscription</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Visit your subscription settings to manage your plan.</p>
                    <a href="{{ route('settings.subscription') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-900 transition-colors hover:text-zinc-700 dark:text-zinc-100 dark:hover:text-zinc-300">
                        Go to Subscription
                        <x-phosphor-arrow-right class="h-4 w-4" />
                    </a>
                </div>
            @endsubscriber

            @notsubscriber
                @php
                    $planId = request()->query('plan');
                    $cycle = request()->query('cycle', 'month');
                    $plan = $planId ? Plan::where('active', 1)->find($planId) : null;
                @endphp

                @if($plan)
                    <livewire:billing.checkout-review :plan="$plan" :cycle="$cycle" />
                @else
                    <div class="mx-auto max-w-lg rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
                        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <x-phosphor-warning-duotone class="h-6 w-6 text-zinc-400" />
                        </div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No plan selected</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Please choose a plan first.</p>
                        <a href="{{ route('settings.subscription') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-zinc-900 transition-colors hover:text-zinc-700 dark:text-zinc-100 dark:hover:text-zinc-300">
                            View Plans
                            <x-phosphor-arrow-right class="h-4 w-4" />
                        </a>
                    </div>
                @endif
            @endnotsubscriber
        @endrole
    </div>
</x-layouts.app>
