<div class="mx-auto w-full max-w-4xl" x-data="{ showCouponInput: false }">

    {{-- Breadcrumb navigation --}}
    <nav class="mb-6 flex items-center gap-2 text-sm">
        <a href="{{ route('settings.subscription') }}" class="text-zinc-400 transition-colors hover:text-zinc-600 dark:text-zinc-500 dark:hover:text-zinc-300" wire:navigate>
            Plans
        </a>
        <x-phosphor-caret-right class="h-3 w-3 text-zinc-300 dark:text-zinc-600" />
        <span class="font-medium text-zinc-900 dark:text-zinc-100">Review order</span>
        <x-phosphor-caret-right class="h-3 w-3 text-zinc-300 dark:text-zinc-600" />
        <span class="text-zinc-300 dark:text-zinc-600">Payment</span>
    </nav>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">

        {{-- LEFT: Plan details --}}
        <div class="lg:col-span-3">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">

                {{-- Plan header --}}
                <div class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ $plan->name }}</h2>
                                <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ $billing_cycle === 'month' ? 'Monthly' : 'Yearly' }}
                                </span>
                            </div>
                            @if($plan->description)
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plan->description }}</p>
                            @endif
                        </div>

                        {{-- Price display --}}
                        <div class="text-right">
                            <div class="flex items-baseline gap-0.5">
                                <span class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $plan->currency }}{{ $this->unitPrice }}</span>
                                <span class="text-sm text-zinc-400 dark:text-zinc-500">/{{ $billing_cycle === 'month' ? 'mo' : 'yr' }}</span>
                            </div>
                            @if($this->effectiveMonthlyPrice)
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">{{ $plan->currency }}{{ $this->effectiveMonthlyPrice }}/mo billed yearly</p>
                            @endif
                        </div>
                    </div>

                    {{-- Trial callout --}}
                    @if((int) $plan->trial_days > 0)
                        <div class="mt-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 dark:border-emerald-800/40 dark:bg-emerald-950/30">
                            <x-phosphor-sparkle-fill class="h-4 w-4 flex-shrink-0 text-emerald-500" />
                            <div>
                                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ (int) $plan->trial_days }}-day free trial</p>
                                <p class="text-xs text-emerald-600/80 dark:text-emerald-400/70">You won't be charged until your trial ends.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Yearly savings badge --}}
                    @if($this->yearlySavings)
                        <div class="mt-4 flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2.5 dark:border-blue-800/40 dark:bg-blue-950/30">
                            <x-phosphor-piggy-bank-duotone class="h-4 w-4 flex-shrink-0 text-blue-500" />
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                You save <span class="font-semibold">{{ $plan->currency }}{{ number_format($this->yearlySavings * $seat_quantity, 0) }}/yr</span> compared to monthly billing.
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Features --}}
                @php
                    $features = is_array($plan->features) ? $plan->features : explode(',', $plan->features ?? '');
                @endphp
                @if(!empty($features) && $features[0] !== '')
                    <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">What's included</h3>
                        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach($features as $feature)
                                <li class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-900/30">
                                        <x-phosphor-check-bold class="h-3 w-3 text-emerald-500" />
                                    </span>
                                    {{ trim($feature) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Trust signals --}}
            <div class="mt-4 flex items-center justify-center gap-6">
                <div class="flex items-center gap-1.5">
                    <x-phosphor-shield-check class="h-4 w-4 text-zinc-300 dark:text-zinc-600" />
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">Secure checkout</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-phosphor-arrow-counter-clockwise class="h-4 w-4 text-zinc-300 dark:text-zinc-600" />
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">Cancel anytime</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <x-phosphor-credit-card class="h-4 w-4 text-zinc-300 dark:text-zinc-600" />
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">No hidden fees</span>
                </div>
            </div>

            {{-- FAQ accordion --}}
            @php
                $faqItems = config('wave.checkout.faq', []);
            @endphp
            @if(count($faqItems) > 0)
                <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" x-data="{ open: null }">
                    <div class="px-5 py-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Common questions</h3>
                    </div>
                    <div class="border-t border-zinc-100 dark:border-zinc-800">
                        @foreach($faqItems as $index => $faq)
                            <div @class(['border-t border-zinc-100 dark:border-zinc-800' => $index > 0])>
                                <button
                                    type="button"
                                    @click="open = open === {{ $index }} ? null : {{ $index }}"
                                    class="flex w-full items-center justify-between px-5 py-3 text-left text-sm text-zinc-700 transition-colors hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-800/50"
                                >
                                    <span>{{ $faq['question'] }}</span>
                                    <x-phosphor-caret-down class="h-3.5 w-3.5 shrink-0 text-zinc-400 transition-transform duration-200 dark:text-zinc-500" ::class="open === {{ $index }} ? 'rotate-180' : ''" />
                                </button>
                                <div
                                    x-show="open === {{ $index }}"
                                    x-collapse
                                    x-cloak
                                >
                                    <p class="px-5 pb-4 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $faq['answer'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT: Order summary --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 lg:sticky lg:top-6">

                <div class="border-b border-zinc-100 bg-zinc-50/80 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Order summary</h3>
                </div>

                <div class="px-5 py-4">
                    <div class="space-y-3">

                        {{-- Plan line item --}}
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $plan->name }}</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $plan->currency }}{{ $this->unitPrice }}</span>
                        </div>

                        {{-- Seat quantity (org billing) --}}
                        @if($this->isOrgBilling)
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-zinc-600 dark:text-zinc-400">Seats</span>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            wire:click="$set('seat_quantity', {{ max($minimum_seat_quantity, $seat_quantity - 1) }})"
                                            @if($seat_quantity <= $minimum_seat_quantity) disabled @endif
                                            class="flex h-5 w-5 items-center justify-center rounded border border-zinc-300 bg-white text-[10px] text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                                        >-</button>
                                        <span class="w-6 text-center text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $seat_quantity }}</span>
                                        <button
                                            type="button"
                                            wire:click="$set('seat_quantity', {{ min($maximum_seat_quantity, $seat_quantity + 1) }})"
                                            @if($seat_quantity >= $maximum_seat_quantity) disabled @endif
                                            class="flex h-5 w-5 items-center justify-center rounded border border-zinc-300 bg-white text-[10px] text-zinc-700 transition-colors hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                                        >+</button>
                                    </div>
                                </div>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">&times; {{ $seat_quantity }}</span>
                            </div>
                            @error('seat_quantity')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        @endif

                        {{-- Applied coupon --}}
                        @if($coupon_applied)
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-1.5">
                                    <x-phosphor-tag-duotone class="h-3.5 w-3.5 text-emerald-500" />
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ $coupon_code }}</span>
                                    <button type="button" wire:click="removeCoupon" class="text-zinc-400 transition-colors hover:text-red-500">
                                        <x-phosphor-x-bold class="h-3 w-3" />
                                    </button>
                                </div>
                                <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ $coupon_description }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Divider --}}
                    <div class="my-4 border-t border-zinc-200 dark:border-zinc-700"></div>

                    {{-- Total / Due today --}}
                    @if((int) $plan->trial_days > 0)
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Due today</span>
                                <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $plan->currency }}0.00</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    Then after {{ (int) $plan->trial_days }} days
                                </span>
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $plan->currency }}{{ number_format($this->totalPrice, 2) }}/{{ $billing_cycle === 'month' ? 'mo' : 'yr' }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Total</span>
                            <div class="text-right">
                                <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ $plan->currency }}{{ number_format($this->totalPrice, 2) }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">/{{ $billing_cycle === 'month' ? 'mo' : 'yr' }}</span>
                            </div>
                        </div>
                    @endif

                    <p class="mt-2 text-[11px] text-zinc-400 dark:text-zinc-500">
                        Tax may apply and will be calculated at payment.
                    </p>
                </div>

                {{-- Coupon input --}}
                @if(!$coupon_applied)
                    <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <button
                            type="button"
                            x-show="!showCouponInput"
                            @click="showCouponInput = true"
                            class="flex items-center gap-1.5 text-sm text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300"
                        >
                            <x-phosphor-tag-duotone class="h-3.5 w-3.5" />
                            Have a coupon code?
                        </button>
                        <div x-show="showCouponInput" x-cloak x-transition.opacity class="flex items-end gap-2">
                            <div class="flex-1">
                                <input
                                    id="coupon_code"
                                    type="text"
                                    wire:model="coupon_code"
                                    wire:keydown.enter="applyCoupon"
                                    placeholder="Enter coupon code"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900 placeholder-zinc-400 transition-colors focus:border-zinc-400 focus:ring-1 focus:ring-zinc-400 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-500 dark:focus:ring-zinc-500"
                                />
                            </div>
                            <button
                                type="button"
                                wire:click="applyCoupon"
                                wire:loading.attr="disabled"
                                class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50 disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            >
                                <span wire:loading.remove wire:target="applyCoupon">Apply</span>
                                <span wire:loading wire:target="applyCoupon">
                                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </span>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Terms & CTA --}}
                <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <label class="mb-4 flex cursor-pointer items-start gap-2.5">
                        <input
                            type="checkbox"
                            wire:model.live="terms_accepted"
                            class="mt-0.5 h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:focus:ring-zinc-400"
                        />
                        <span class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                            I agree to the
                            <a href="/terms" target="_blank" class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 hover:decoration-zinc-500 dark:text-zinc-300 dark:decoration-zinc-600 dark:hover:decoration-zinc-400">Terms of Service</a>
                            and
                            <a href="/privacy" target="_blank" class="font-medium text-zinc-700 underline decoration-zinc-300 underline-offset-2 hover:decoration-zinc-500 dark:text-zinc-300 dark:decoration-zinc-600 dark:hover:decoration-zinc-400">Privacy Policy</a>.
                        </span>
                    </label>

                    <button
                        type="button"
                        wire:click="proceedToPayment"
                        wire:loading.attr="disabled"
                        @if(!$terms_accepted) disabled @endif
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                    >
                        <span wire:loading.remove wire:target="proceedToPayment">
                            @if((int) $plan->trial_days > 0)
                                Start Free Trial
                            @else
                                Continue to Payment
                            @endif
                        </span>
                        <span wire:loading wire:target="proceedToPayment" class="flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Redirecting to Stripe...
                        </span>
                    </button>

                    <p class="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-zinc-400 dark:text-zinc-500">
                        <x-phosphor-lock-simple-fill class="h-3 w-3" />
                        Secure payment via Stripe
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
