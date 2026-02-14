<?php
    use function Laravel\Folio\{middleware, name};
    name('subscription.welcome');
    middleware(['auth', 'verified']);
?>

@php
    $plan = auth()->user()->plan();
    $subscription = auth()->user()->latestSubscription();
    $interval = auth()->user()->planInterval();
    $features = $plan ? (is_array($plan->features) ? $plan->features : explode(',', $plan->features ?? '')) : [];
@endphp

<x-layouts.app>
    <x-app.container class="space-y-6">

        {{-- Hero --}}
        <div class="flex flex-col items-center pt-4 text-center">
            <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/40">
                <x-phosphor-check-circle-duotone class="h-9 w-9 text-emerald-600 dark:text-emerald-400" />
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-zinc-100">You're all set!</h1>
            <p class="mt-2 max-w-md text-base text-zinc-500 dark:text-zinc-400">
                Your subscription is now active. You have full access to everything in your plan.
            </p>
        </div>

        @if($plan)
            {{-- Plan summary --}}
            <div class="mx-auto w-full max-w-lg overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-5 py-3.5 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                            <x-phosphor-crown-duotone class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan->name }} Plan</h3>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $interval }} billing</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Active
                    </span>
                </div>

                @if(!empty($features) && $features[0] !== '')
                    <div class="px-5 py-4">
                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($features as $feature)
                                <li class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    <x-phosphor-check-circle-duotone class="h-4 w-4 flex-shrink-0 text-emerald-500" />
                                    <span>{{ trim($feature) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- Next steps --}}
        <div class="mx-auto w-full max-w-lg">
            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Next steps</h4>
            <div class="space-y-2">
                <a href="{{ route('dashboard') }}" wire:navigate class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3.5 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/80">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                        <x-phosphor-house-duotone class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Go to Dashboard</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Start using your new plan features</p>
                    </div>
                    <x-phosphor-caret-right class="h-4 w-4 text-zinc-400 transition-transform group-hover:translate-x-0.5" />
                </a>
                <a href="{{ route('settings.subscription') }}" wire:navigate class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3.5 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/80">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30">
                        <x-phosphor-credit-card-duotone class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Manage Subscription</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">View billing details and payment history</p>
                    </div>
                    <x-phosphor-caret-right class="h-4 w-4 text-zinc-400 transition-transform group-hover:translate-x-0.5" />
                </a>
                <a href="{{ route('settings.profile') }}" wire:navigate class="group flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3.5 transition-colors hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/80">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                        <x-phosphor-user-circle-duotone class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Complete Your Profile</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Add your details and personalize your account</p>
                    </div>
                    <x-phosphor-caret-right class="h-4 w-4 text-zinc-400 transition-transform group-hover:translate-x-0.5" />
                </a>
            </div>
        </div>

    </x-app.container>

    <x-slot name="javascript">
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
        <script>
            confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
        </script>
    </x-slot>
</x-layouts.app>
