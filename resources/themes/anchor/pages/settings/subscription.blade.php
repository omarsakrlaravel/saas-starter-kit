<?php

    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};

    middleware(['auth', 'verified']);
    name('settings.subscription');

    new class extends Component
    {
        public function mount(): void
        {
            //
        }
    }

?>

<x-layouts.app>
    @volt('settings.subscription')
        <div class="relative">
            <x-app.settings-layout
                title="Subscription"
                description="Manage your plan and billing"
            >
                @role('admin')
                    <x-app.alert id="admin_subscription_notice" :dismissable="false" type="info">
                        You are logged in as an admin and have full access. Authenticate with a different user and visit this page to see the subscription checkout process.
                    </x-app.alert>
                @else
                    @subscriber
                        @php
                            $plan = auth()->user()->plan();
                            $subscription = auth()->user()->latestSubscription();
                            $interval = auth()->user()->planInterval();
                            $features = is_array($plan->features) ? $plan->features : explode(',', $plan->features ?? '');
                            $billingContext = auth()->user()->getBillingContext();
                            $isOrgBilling = $billingContext['type'] === 'organization';
                        @endphp

                        {{-- Plan Overview Card --}}
                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                            {{-- Header --}}
                            <div class="border-b border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40">
                                            <x-phosphor-crown-duotone class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                        </div>
                                        <div>
                                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan->name }} Plan</h3>
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $interval }} billing</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/30">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                </div>
                            </div>

                            {{-- Body --}}
                            <div class="px-5 py-5">
                                {{-- Price --}}
                                <div class="mb-5">
                                    @php
                                        $unitPrice = $subscription->cycle === 'month' ? $plan->monthly_price : $plan->yearly_price;
                                        $seats = ($isOrgBilling && $subscription->quantity) ? $subscription->quantity : 1;
                                        $totalPrice = $unitPrice * $seats;
                                        $cycleLabel = $subscription->cycle === 'month' ? 'month' : 'year';
                                    @endphp
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                                            ${{ number_format($totalPrice, 0) }}
                                        </span>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                            /{{ $cycleLabel }}
                                        </span>
                                    </div>
                                    @if($seats > 1)
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">${{ number_format($unitPrice, 0) }}/seat &times; {{ $seats }} seats</p>
                                    @endif
                                    @if($plan->description)
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                                    @endif
                                </div>

                                {{-- Subscription ID --}}
                                @if($subscription->stripe_id)
                                    <div x-data="{ copied: false }" class="mb-5 flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/40">
                                        <div class="min-w-0">
                                            <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Subscription ID</dt>
                                            <dd class="mt-0.5 truncate font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $subscription->stripe_id }}</dd>
                                        </div>
                                        <button
                                            type="button"
                                            @click="navigator.clipboard.writeText('{{ $subscription->stripe_id }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="ml-3 flex-shrink-0 rounded-md p-1.5 text-zinc-400 transition-colors hover:bg-zinc-200 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                            :title="copied ? 'Copied!' : 'Copy to clipboard'"
                                        >
                                            <x-phosphor-check-bold x-show="copied" x-cloak class="h-4 w-4 text-emerald-500" />
                                            <x-phosphor-copy class="h-4 w-4" x-show="!copied" />
                                        </button>
                                    </div>
                                @endif

                                {{-- Billing Details Grid --}}
                                <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @if($subscription->next_payment_at)
                                        <div class="rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/40">
                                            <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Next billing date</dt>
                                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($subscription->next_payment_at)->format('M j, Y') }}</dd>
                                        </div>
                                    @endif
                                    @if($subscription->last_payment_at)
                                        <div class="rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/40">
                                            <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Last payment</dt>
                                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($subscription->last_payment_at)->format('M j, Y') }}</dd>
                                        </div>
                                    @endif
                                    <div class="rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/40">
                                        <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Billing provider</dt>
                                        <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">Stripe</dd>
                                    </div>
                                    @if($isOrgBilling && $subscription->quantity)
                                        <div class="rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800/40">
                                            <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Seats</dt>
                                            <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $subscription->quantity }}</dd>
                                        </div>
                                    @endif
                                </div>

                                {{-- Features --}}
                                @if(!empty($features) && $features[0] !== '')
                                    <div class="border-t border-zinc-100 pt-4 dark:border-zinc-700/50">
                                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Plan includes</h4>
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

                            {{-- Footer with actions --}}
                            <div class="border-t border-zinc-200 bg-zinc-50 px-5 py-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                                @if (session('update'))
                                    <div class="mb-3 rounded-lg bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                                        Successfully updated your subscription.
                                    </div>
                                @endif
                                @if (session('downgrade_scheduled'))
                                    <div class="mb-3 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                        Downgrade scheduled. Your plan will change at the end of your billing period.
                                    </div>
                                @endif
                                <livewire:billing.update />
                            </div>
                        </div>

                    @endsubscriber

                    @notsubscriber
                        {{-- Empty State --}}
                        <div class="mb-6 rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <x-phosphor-credit-card-duotone class="h-6 w-6 text-zinc-400" />
                            </div>
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No active subscription</h3>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Choose a plan below to get started with all the features you need.</p>
                        </div>

                        {{-- Checkout --}}
                        <livewire:billing.checkout />

                        <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                            <x-phosphor-shield-check-duotone class="h-4 w-4" />
                            <span>Payments securely processed by <strong class="font-medium text-zinc-500 dark:text-zinc-400">Stripe</strong></span>
                        </p>
                    @endnotsubscriber
                @endrole
            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
