<div class="space-y-6">
    {{-- Payment method list --}}
    @if(count($this->paymentMethods) > 0)
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach($this->paymentMethods as $method)
                <div class="flex items-center gap-4 bg-white px-5 py-4 dark:bg-zinc-900">
                    {{-- Card icon --}}
                    <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                        <x-phosphor-credit-card-duotone class="h-5 w-5 text-zinc-500 dark:text-zinc-400" />
                    </div>

                    {{-- Card details --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold capitalize text-zinc-900 dark:text-zinc-100">
                                {{ $method['brand'] }}
                            </p>
                            <span class="font-mono text-sm text-zinc-500 dark:text-zinc-400">
                                &bull;&bull;&bull;&bull; {{ $method['last4'] }}
                            </span>
                            @if($method['is_default'])
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/30">
                                    Default
                                </span>
                            @endif
                        </div>
                        @if($method['exp_month'] && $method['exp_year'])
                            <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                Expires {{ str_pad($method['exp_month'], 2, '0', STR_PAD_LEFT) }}/{{ $method['exp_year'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1">
                        @if(! $method['is_default'])
                            <x-filament::modal width="md" :id="'set-default-payment-method-'.$method['id']">
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1.5 text-xs font-medium text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"
                                    >
                                        Set default
                                    </button>
                                </x-slot>
                                <div class="space-y-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Set this card as your default payment method?</h3>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Future charges will use this card first.</p>
                                    </div>
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button
                                            type="button"
                                            color="gray"
                                            x-on:click="$dispatch('close-modal', { id: 'set-default-payment-method-{{ $method['id'] }}' })"
                                        >
                                            Cancel
                                        </x-button>
                                        <x-button
                                            type="button"
                                            x-on:click="$wire.setDefaultPaymentMethod('{{ $method['id'] }}'); $dispatch('close-modal', { id: 'set-default-payment-method-{{ $method['id'] }}' })"
                                        >
                                            Set default
                                        </x-button>
                                    </div>
                                </div>
                            </x-filament::modal>

                            <x-filament::modal width="md" :id="'remove-payment-method-'.$method['id']">
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20 dark:hover:text-red-300"
                                    >
                                        Remove
                                    </button>
                                </x-slot>
                                <div class="space-y-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Remove this payment method?</h3>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">You can add it again later if needed.</p>
                                    </div>
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button
                                            type="button"
                                            color="gray"
                                            x-on:click="$dispatch('close-modal', { id: 'remove-payment-method-{{ $method['id'] }}' })"
                                        >
                                            Cancel
                                        </x-button>
                                        <x-button
                                            type="button"
                                            color="danger"
                                            x-on:click="$wire.deletePaymentMethod('{{ $method['id'] }}'); $dispatch('close-modal', { id: 'remove-payment-method-{{ $method['id'] }}' })"
                                        >
                                            Remove
                                        </x-button>
                                    </div>
                                </div>
                            </x-filament::modal>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-8 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <x-phosphor-credit-card-duotone class="h-6 w-6 text-zinc-400" />
            </div>
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No payment methods</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Add a payment method to manage your subscription billing.</p>
        </div>
    @endif

    {{-- Add payment method --}}
    @if($showAddForm)
        <div
            x-data="{
                stripe: null,
                cardElement: null,
                cardError: '',
                processing: false,
                init() {
                    this.stripe = Stripe('{{ config('cashier.key') }}');
                    const elements = this.stripe.elements();
                    this.cardElement = elements.create('card', {
                        style: {
                            base: {
                                fontSize: '16px',
                                color: document.documentElement.classList.contains('dark') ? '#e4e4e7' : '#18181b',
                                '::placeholder': { color: '#a1a1aa' },
                            },
                        },
                    });
                    this.cardElement.mount(this.$refs.cardElement);
                    this.cardElement.on('change', (event) => {
                        this.cardError = event.error ? event.error.message : '';
                    });
                },
                async submitCard() {
                    this.processing = true;
                    this.cardError = '';

                    const { setupIntent, error } = await this.stripe.confirmCardSetup(
                        '{{ $setupIntentClientSecret }}',
                        { payment_method: { card: this.cardElement } }
                    );

                    if (error) {
                        this.cardError = error.message;
                        this.processing = false;
                        return;
                    }

                    $wire.addPaymentMethod(setupIntent.payment_method);
                    this.processing = false;
                }
            }"
            class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900"
        >
            <h4 class="mb-4 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Add a new card</h4>

            <div x-ref="cardElement" class="rounded-lg border border-zinc-300 bg-white px-4 py-3 dark:border-zinc-600 dark:bg-zinc-800"></div>

            <template x-if="cardError">
                <p class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="cardError"></p>
            </template>

            <div class="mt-4 flex items-center gap-3">
                <x-button @click="submitCard()" ::disabled="processing" color="primary">
                    <span x-show="!processing">Add Card</span>
                    <span x-show="processing" x-cloak>Processing...</span>
                </x-button>
                <x-button wire:click="closeAddForm" color="gray">Cancel</x-button>
            </div>
        </div>
    @else
        <x-button wire:click="openAddForm" color="gray">
            <x-phosphor-plus class="mr-1.5 h-4 w-4" />
            Add Payment Method
        </x-button>
    @endif
</div>
