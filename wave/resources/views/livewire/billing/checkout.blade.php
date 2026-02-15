<section>
    <div x-data="{
            billing_cycle_available: @entangle('billing_cycle_available'),
            billing_cycle_selected: @entangle('billing_cycle_selected'),
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
                {{-- Seat quantity (new checkout + org billing only) --}}
                @if(! $change && auth()->user()->getBillingContext()['type'] === 'organization')
                    <div class="w-full rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Seats</label>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400" x-text="minimum_seat_quantity + ' min'"></span>
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <button
                                type="button"
                                @click="seat_quantity = Math.max(minimum_seat_quantity, seat_quantity - 1)"
                                :disabled="seat_quantity <= minimum_seat_quantity"
                                class="flex h-9 w-9 items-center justify-center rounded-md border border-zinc-300 bg-white text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-700"
                            >
                                -
                            </button>
                            <input
                                x-model.number="seat_quantity"
                                type="number"
                                :min="minimum_seat_quantity"
                                max="{{ $maximum_seat_quantity }}"
                                class="w-28 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-center text-lg font-semibold text-zinc-900 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                            />
                            <button
                                type="button"
                                @click="seat_quantity = Math.min({{ $maximum_seat_quantity }}, seat_quantity + 1)"
                                :disabled="seat_quantity >= {{ $maximum_seat_quantity }}"
                                class="flex h-9 w-9 items-center justify-center rounded-md border border-zinc-300 bg-white text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-700"
                            >
                                +
                            </button>
                        </div>
                        @error('seat_quantity')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                {{-- Coupon code (new checkout only) --}}
                @if(! $change)
                    <div class="w-full rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                        <label for="coupon_code" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Coupon code (optional)</label>
                        <div class="mt-2">
                            <input
                                id="coupon_code"
                                type="text"
                                wire:model.defer="coupon_code"
                                placeholder="SUMMERSALE"
                                class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                            />
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            You can also redeem promotion codes directly in Stripe Checkout.
                        </p>
                    </div>
                @endif

                {{-- Plan Cards --}}
                @foreach($plans as $plan)
                    @php
                        $features = is_array($plan->features) ? $plan->features : explode(',', $plan->features);
                        $isCurrentPlan = $change && $userPlan && $plan->id == $userPlan->id;
                    @endphp
                    <div
                        x-show="(billing_cycle_selected == 'month' && '{{ $plan->monthly_price_id }}' != '') || (billing_cycle_selected == 'year' && '{{ $plan->yearly_price_id }}' != '')"
                        class="w-full"
                    >
                        <div @class([
                            'relative overflow-hidden rounded-xl border-2 bg-white transition-all duration-200 dark:bg-zinc-800',
                            'border-emerald-400 ring-1 ring-emerald-400/20 dark:border-emerald-500 dark:ring-emerald-500/20' => $isCurrentPlan,
                            'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' => ! $isCurrentPlan,
                        ])>
                            {{-- Current plan badge --}}
                            @if($isCurrentPlan)
                                <div class="absolute right-0 top-0">
                                    <div class="flex items-center gap-1 rounded-bl-lg bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">
                                        <x-phosphor-check-circle-fill class="h-3 w-3" />
                                        Current
                                    </div>
                                </div>
                            @endif

                            <div class="p-5 lg:p-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</h3>
                                    @if($plan->description)
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                                    @endif
                                </div>

                                <div class="mt-4 flex items-baseline gap-1">
                                    <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">$<span x-text="billing_cycle_selected == 'month' ? '{{ $plan->monthly_price }}' : '{{ $plan->yearly_price }}'"></span></span>
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">/<span x-text="billing_cycle_selected == 'month' ? 'mo' : 'yr'"></span></span>
                                </div>

                                @if((int) $plan->trial_days > 0)
                                    <p class="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                        {{ (int) $plan->trial_days }} day free trial
                                    </p>
                                @endif

                                @if(!empty($features) && $features[0] !== '')
                                    <ul class="mt-5 space-y-2">
                                        @foreach($features as $feature)
                                            <li class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                                <x-phosphor-check-circle-duotone class="h-4 w-4 flex-shrink-0 text-emerald-500" />
                                                {{ trim($feature) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-700/50 lg:px-6">
                                @if($change)
                                    @if($isCurrentPlan)
                                        {{-- Current plan: disabled on same cycle, switch cycle option --}}
                                        <div x-show="billing_cycle_selected == '{{ $userSubscription->cycle }}'">
                                            <button disabled class="w-full cursor-not-allowed rounded-lg border-2 border-zinc-200 py-2.5 text-sm font-semibold text-zinc-400 dark:border-zinc-600 dark:text-zinc-500">
                                                Your Current Plan
                                            </button>
                                        </div>
                                        <div x-show="billing_cycle_selected != '{{ $userSubscription->cycle }}'" x-cloak>
                                            <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
                                                <x-slot name="trigger">
                                                    <button class="w-full rounded-lg bg-zinc-900 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                                                        Switch to <span x-text="billing_cycle_selected == 'month' ? 'Monthly' : 'Yearly'"></span> Billing
                                                    </button>
                                                </x-slot>
                                                <div class="relative flex w-full flex-col items-center justify-center">
                                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                                                        <x-phosphor-arrows-clockwise-duotone class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                                    </div>
                                                    <div class="mb-5 mt-3 text-center">
                                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Switch Billing Cycle</h3>
                                                        <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                                                            You'll be switched to <span x-text="billing_cycle_selected == 'month' ? 'monthly' : 'yearly'" class="font-medium"></span> billing. The change will be prorated automatically.
                                                        </p>
                                                    </div>
                                                    <div class="flex w-full items-center gap-3">
                                                        <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                        <x-button wire:click="switchPlan('{{ $plan->id }}')" color="info" class="w-1/2">Confirm Switch</x-button>
                                                    </div>
                                                </div>
                                            </x-filament::modal>
                                        </div>
                                    @else
                                        {{-- Different plan: switch CTA --}}
                                        <x-filament::modal width="lg" id="change-plan-modal-{{ $plan->id }}">
                                            <x-slot name="trigger">
                                                <button class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                                                    Switch to {{ $plan->name }}
                                                </button>
                                            </x-slot>
                                            <div class="relative flex w-full flex-col items-center justify-center">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40">
                                                    <x-phosphor-arrows-clockwise-duotone class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <div class="mb-5 mt-3 text-center">
                                                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Switch to {{ $plan->name }}</h3>
                                                    <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">Are you sure you want to change your subscription? The change will be prorated automatically.</p>
                                                </div>
                                                <div class="flex w-full items-center gap-3">
                                                    <x-button x-on:click="$dispatch('close-modal', { id: 'change-plan-modal-{{ $plan->id }}' })" color="secondary" class="w-1/2">Cancel</x-button>
                                                    <x-button wire:click="switchPlan('{{ $plan->id }}')" color="info" class="w-1/2">Yes, Switch Plans</x-button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    @endif
                                @else
                                    <button wire:click="redirectToStripeCheckout('{{ $plan->id }}')" class="w-full cursor-pointer rounded-lg bg-zinc-900 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white">
                                        Subscribe to {{ $plan->name }}
                                    </button>
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
