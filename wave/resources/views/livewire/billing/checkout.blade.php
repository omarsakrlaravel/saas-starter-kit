<section>
    <div x-data="{
            billing_cycle_available: @entangle('billing_cycle_available'),
            billing_cycle_selected: @entangle('billing_cycle_selected').live,
            seat_quantity: @entangle('seat_quantity'),
            minimum_seat_quantity: @entangle('minimum_seat_quantity'),
            toggleButtonClicked(el, month_or_year){
                this.toggleRepositionMarker(el);
                this.billing_cycle_selected = month_or_year;
            },
            toggleRepositionMarker(toggleButton){
                this.$refs.marker.style.width=toggleButton.offsetWidth + 'px';
                this.$refs.marker.style.height=toggleButton.offsetHeight + 'px';
                this.$refs.marker.style.left=toggleButton.offsetLeft + 'px';
            },
            fullScreenLoader: false,
            fullScreenLoaderMessage: 'Loading'
        }"
        @loader-show.window="fullScreenLoader = true"
        @loader-hide.window="fullScreenLoader = false"
        @loader-message.window="fullScreenLoaderMessage = event.detail.message"
        class="flex w-full items-start justify-center rounded-xl">
        <div class="flex w-full flex-col flex-wrap {{ $change ? '' : 'mx-auto lg:max-w-4xl' }}">
            <x-billing.billing_cycle_toggle />

            <div class="space-y-4">
                {{-- Info banner for plan change mode --}}
                @if($change)
                <div class="flex items-start gap-2.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <x-phosphor-info-duotone class="mt-0.5 h-4 w-4 flex-shrink-0 text-zinc-400" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="font-medium text-zinc-600 dark:text-zinc-300">Upgrades</span> apply immediately and are prorated.
                            <span class="font-medium text-zinc-600 dark:text-zinc-300">Downgrades</span> take effect at the end of your current billing period (monthly or yearly).
                        </p>
                    </div>
                @endif

                {{-- Plan Cards --}}
                @foreach($plans as $plan)
                    @php
                        $features = is_array($plan->features) ? $plan->features : explode(',', $plan->features);
                        $isCurrentPlan = $change && $userPlan && (int) $plan->id === (int) $userPlan->id;
                        $currentSubscriptionCycle = $userSubscription?->cycle;
                        $changeType = $change ? $this->getChangeType($plan) : null;
                        $isPendingTarget = $change && $userSubscription && $userSubscription->hasPendingChange()
                            && (int) $userSubscription->pending_plan_id === (int) $plan->id
                            && $this->billing_cycle_selected === $userSubscription->pending_cycle;
                    @endphp
                    <div
                        x-show="(billing_cycle_selected == 'month' && '{{ $plan->monthly_price_id }}' != '') || (billing_cycle_selected == 'year' && '{{ $plan->yearly_price_id }}' != '')"
                        class="w-full"
                    >
                        <div @class([
                            'group relative overflow-hidden rounded-xl border transition-all duration-200',
                            'border-emerald-200 bg-emerald-50/40 shadow-sm shadow-emerald-100/50 dark:border-emerald-800/60 dark:bg-emerald-950/20 dark:shadow-none' => $isCurrentPlan,
                            'border-amber-200 bg-amber-50/30 shadow-sm shadow-amber-100/50 dark:border-amber-800/60 dark:bg-amber-950/20 dark:shadow-none' => $isPendingTarget && !$isCurrentPlan,
                            'border-zinc-200 bg-white shadow-sm hover:shadow-md hover:border-zinc-300 dark:border-zinc-700/80 dark:bg-zinc-800 dark:hover:border-zinc-600' => !$isCurrentPlan && !$isPendingTarget,
                        ])>
                            <div class="p-5 lg:p-6">
                                {{-- Header row: plan name + badge --}}
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</h3>
                                        @if($plan->description)
                                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                                        @endif
                                    </div>

                                    @if($isCurrentPlan)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">
                                            <x-phosphor-check-circle-fill class="h-3 w-3" />
                                            Current · {{ $userSubscription->cycle === 'year' ? 'Yearly' : 'Monthly' }}
                                        </span>
                                    @elseif($isPendingTarget)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                            <x-phosphor-clock-countdown-fill class="h-3 w-3" />
                                            Scheduled
                                        </span>
                                    @endif
                                </div>

                                {{-- Price --}}
                                <div class="mt-5 flex items-baseline gap-1.5">
                                    <span class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $plan->currency }}<span x-text="billing_cycle_selected == 'month' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span></span>
                                    <span class="text-sm font-medium text-zinc-400 dark:text-zinc-500">/<span x-text="billing_cycle_selected == 'month' ? 'mo' : 'yr'"></span></span>
                                </div>

                                @if($isCurrentPlan && $userSubscription)
                                    <p class="mt-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        Billed {{ $userSubscription->cycle === 'year' ? 'yearly' : 'monthly' }}
                                    </p>
                                @endif

                                @if((int) $plan->trial_days > 0)
                                    <p class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        <x-phosphor-sparkle-fill class="h-3 w-3" />
                                        {{ (int) $plan->trial_days }} day free trial
                                    </p>
                                @endif

                                {{-- Features --}}
                                @if(!empty($features) && $features[0] !== '')
                                    <ul class="mt-6 space-y-2.5">
                                        @foreach($features as $feature)
                                            <li class="flex items-center gap-2.5 text-sm text-zinc-600 dark:text-zinc-300">
                                                <span @class([
                                                    'flex h-5 w-5 shrink-0 items-center justify-center rounded-full',
                                                    'bg-emerald-100 dark:bg-emerald-900/40' => $isCurrentPlan,
                                                    'bg-zinc-100 dark:bg-zinc-700/60' => !$isCurrentPlan,
                                                ])>
                                                    <x-phosphor-check-bold class="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                                                </span>
                                                {{ trim($feature) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-700/40 lg:px-6">
                                @if($change)
                                    @if($isPendingTarget)
                                        {{-- Pending target: show scheduled badge --}}
                                        <button disabled class="w-full cursor-not-allowed rounded-lg border border-amber-200 bg-amber-50 py-2.5 text-sm font-semibold text-amber-600 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400">
                                            Scheduled Change
                                        </button>
                                    @elseif($isCurrentPlan && $currentSubscriptionCycle)
                                        {{-- Same plan + same cycle --}}
                                        <button x-cloak x-show="billing_cycle_selected === '{{ $currentSubscriptionCycle }}'" disabled class="w-full cursor-not-allowed rounded-lg border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-semibold text-emerald-600 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            Your Current Plan
                                        </button>
                                        <div x-cloak x-show="billing_cycle_selected !== '{{ $currentSubscriptionCycle }}'">
                                            <x-filament::modal width="lg" id="change-cycle-modal-{{ $plan->id }}">
                                                <x-slot name="trigger">
                                                    <button :class="billing_cycle_selected === 'year'
                                                        ? 'w-full rounded-lg bg-zinc-900 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-zinc-800 hover:shadow-md dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white'
                                                        : 'w-full rounded-lg border border-zinc-200 bg-white py-2.5 text-sm font-semibold text-zinc-600 shadow-sm transition-all hover:border-zinc-300 hover:bg-zinc-50 hover:shadow-md dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:bg-zinc-700'">
                                                        <span x-text="billing_cycle_selected === 'year' ? 'Upgrade to Yearly Billing' : 'Switch to Monthly Billing'"></span>
                                                    </button>
                                                </x-slot>
                                                <div class="relative flex w-full flex-col items-center justify-center">
                                                    <div class="flex h-12 w-12 items-center justify-center rounded-full" :class="billing_cycle_selected === 'year' ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-amber-100 dark:bg-amber-900/40'">
                                                        <x-phosphor-arrows-clockwise-duotone class="h-6 w-6" x-bind:class="billing_cycle_selected === 'year' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'" />
                                                    </div>
                                                    <div class="mb-5 mt-3 text-center">
                                                        <h3 x-show="billing_cycle_selected === 'year'" class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Upgrade to Yearly Billing</h3>
                                                        <h3 x-show="billing_cycle_selected === 'month'" class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Switch to Monthly Billing</h3>
                                                        <p x-show="billing_cycle_selected === 'year'" class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                            This upgrade takes effect immediately. You'll be charged a prorated amount for the annual plan.
                                                        </p>
                                                        <p x-show="billing_cycle_selected === 'month'" class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                            This change will take effect at the end of your current yearly billing period. You'll continue on yearly billing until then.
                                                        </p>
                                                    </div>
                                                    <div class="flex w-full items-center gap-3">
                                                        <x-button x-on:click="$dispatch('close-modal', { id: 'change-cycle-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                        <x-button x-cloak x-show="billing_cycle_selected === 'year'" wire:click="switchPlan('{{ $plan->id }}', 'year')" color="success" class="w-1/2">Upgrade Now</x-button>
                                                        <x-button x-cloak x-show="billing_cycle_selected === 'month'" wire:click="switchPlan('{{ $plan->id }}', 'month')" color="warning" class="w-1/2">Schedule Switch</x-button>
                                                    </div>
                                                </div>
                                            </x-filament::modal>
                                        </div>
                                    @elseif($changeType === 'same')
                                        <button disabled class="w-full cursor-not-allowed rounded-lg border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-semibold text-emerald-600 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
                                            Your Current Plan
                                        </button>
                                    @elseif($changeType === 'upgrade')
                                        {{-- Upgrade CTA --}}
                                        <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
                                            <x-slot name="trigger">
                                                <button class="w-full rounded-lg bg-zinc-900 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-zinc-800 hover:shadow-md dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                                                    Upgrade to {{ $plan->name }}
                                                </button>
                                            </x-slot>
                                            <div class="relative flex w-full flex-col items-center justify-center">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                                                    <x-phosphor-arrow-circle-up-duotone class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                                </div>
                                                <div class="mb-5 mt-3 text-center">
                                                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Upgrade to {{ $plan->name }}</h3>
                                                    <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                        This upgrade takes effect immediately. You'll be charged a prorated amount for the remainder of your billing period.
                                                    </p>
                                                </div>
                                                <div class="flex w-full items-center gap-3">
                                                    <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                    <x-button wire:click="switchPlan('{{ $plan->id }}')" color="success" class="w-1/2">Confirm Upgrade</x-button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    @elseif($changeType === 'downgrade')
                                        {{-- Downgrade CTA --}}
                                        <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
                                            <x-slot name="trigger">
                                                <button class="w-full rounded-lg border border-zinc-200 bg-white py-2.5 text-sm font-semibold text-zinc-600 shadow-sm transition-all hover:border-zinc-300 hover:bg-zinc-50 hover:shadow-md dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:bg-zinc-700">
                                                    Downgrade to {{ $plan->name }}
                                                </button>
                                            </x-slot>
                                            <div class="relative flex w-full flex-col items-center justify-center">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                                                    <x-phosphor-arrow-circle-down-duotone class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                                                </div>
                                                <div class="mb-5 mt-3 text-center">
                                                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Downgrade to {{ $plan->name }}</h3>
                                                    <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                        This change will take effect at the end of your current billing period. You'll continue to have access to your current plan until then.
                                                    </p>
                                                </div>
                                                <div class="flex w-full items-center gap-3">
                                                    <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                    <x-button wire:click="switchPlan('{{ $plan->id }}')" color="warning" class="w-1/2">Schedule Downgrade</x-button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    @elseif($changeType === 'cycle_change')
                                        {{-- Cycle change CTA --}}
                                        @php
                                            $cycleLabel = $this->billing_cycle_selected === 'year' ? 'Yearly' : 'Monthly';
                                            $isImmediateChange = $this->billing_cycle_selected === 'year';
                                        @endphp
                                        <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
                                            <x-slot name="trigger">
                                                <button @class([
                                                    'w-full rounded-lg py-2.5 text-sm font-semibold shadow-sm transition-all hover:shadow-md',
                                                    'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white' => $isImmediateChange,
                                                    'border border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:border-zinc-500 dark:hover:bg-zinc-700' => !$isImmediateChange,
                                                ])>
                                                    @if($isImmediateChange)
                                                        Upgrade to {{ $cycleLabel }} Billing
                                                    @else
                                                        Switch to {{ $cycleLabel }} Billing
                                                    @endif
                                                </button>
                                            </x-slot>
                                            <div class="relative flex w-full flex-col items-center justify-center">
                                                <div @class([
                                                    'flex h-12 w-12 items-center justify-center rounded-full',
                                                    'bg-emerald-100 dark:bg-emerald-900/40' => $isImmediateChange,
                                                    'bg-amber-100 dark:bg-amber-900/40' => !$isImmediateChange,
                                                ])>
                                                    <x-phosphor-arrows-clockwise-duotone @class([
                                                        'h-6 w-6',
                                                        'text-emerald-600 dark:text-emerald-400' => $isImmediateChange,
                                                        'text-amber-600 dark:text-amber-400' => !$isImmediateChange,
                                                    ]) />
                                                </div>
                                                <div class="mb-5 mt-3 text-center">
                                                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                                        @if($isImmediateChange)
                                                            Upgrade to {{ $cycleLabel }} Billing
                                                        @else
                                                            Switch to {{ $cycleLabel }} Billing
                                                        @endif
                                                    </h3>
                                                    <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                        @if($isImmediateChange)
                                                            This upgrade takes effect immediately. You'll be charged a prorated amount for the annual plan.
                                                        @else
                                                            This change will take effect at the end of your current yearly billing period. You'll continue on yearly billing until then.
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="flex w-full items-center gap-3">
                                                    <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                    <x-button wire:click="switchPlan('{{ $plan->id }}')" :color="$isImmediateChange ? 'success' : 'warning'" class="w-1/2">
                                                        {{ $isImmediateChange ? 'Upgrade Now' : 'Schedule Switch' }}
                                                    </x-button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    @endif
                                @else
                                    <a href="{{ route('settings.subscription.checkout', ['plan' => $plan->id, 'cycle' => $this->billing_cycle_selected]) }}"
                                        x-bind:href="'{{ route('settings.subscription.checkout') }}?plan={{ $plan->id }}&cycle=' + billing_cycle_selected"
                                        class="block w-full cursor-pointer rounded-lg bg-zinc-900 py-2.5 text-center text-sm font-semibold text-white shadow-sm transition-all hover:bg-zinc-800 hover:shadow-md dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                                        Subscribe to {{ $plan->name }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Full screen loader --}}
        <div x-show="fullScreenLoader" class="fixed inset-0 z-[999999999] flex items-center justify-center">
            <div class="absolute inset-0 z-10 bg-black/50"></div>
            <div class="relative z-20 flex items-center rounded-full bg-black/30 px-3.5 py-2">
                <svg class="h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <p x-text="fullScreenLoaderMessage" class="ml-2 font-medium text-white"></p>
            </div>
        </div>
    </div>
</section>
