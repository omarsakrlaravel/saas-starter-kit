<div class="relative w-full space-y-3">
    {{-- Pending downgrade banner --}}
    @if($has_pending_change)
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-900/20">
            <x-phosphor-clock-countdown-duotone class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="flex-1">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                    Scheduled change to {{ $pending_plan_name }} ({{ $pending_cycle_label }})
                </p>
                @if($pending_change_date)
                    <p class="mt-0.5 text-sm text-amber-600 dark:text-amber-400">
                        Takes effect on {{ $pending_change_date }}
                    </p>
                @endif
            </div>
            <x-filament::modal width="md" id="cancel-pending-change-modal">
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="flex-shrink-0 rounded-md px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 transition-colors hover:bg-amber-100 dark:text-amber-300 dark:ring-amber-500/30 dark:hover:bg-amber-900/40"
                    >
                        Cancel change
                    </button>
                </x-slot>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Cancel this scheduled change?</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Your current plan and billing cycle will stay as-is.</p>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <x-button
                            type="button"
                            color="gray"
                            x-on:click="$dispatch('close-modal', { id: 'cancel-pending-change-modal' })"
                        >
                            Keep change
                        </x-button>
                        <x-button
                            type="button"
                            color="danger"
                            x-on:click="$wire.cancelPendingChange(); $dispatch('close-modal', { id: 'cancel-pending-change-modal' })"
                        >
                            Cancel change
                        </x-button>
                    </div>
                </div>
            </x-filament::modal>
        </div>
    @endif

    {{-- Cancellation notice --}}
    @if($cancellation_scheduled && ! is_null($subscription_ends_at))
        <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-500/30 dark:bg-red-900/20">
            <x-phosphor-warning-duotone class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
            <div class="flex-1">
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                    Your subscription is scheduled to cancel on {{ \Carbon\Carbon::parse($subscription_ends_at)->format('F jS, Y') }}.
                </p>
                <p class="mt-0.5 text-sm text-red-600 dark:text-red-400">
                    You'll continue to have access until then.
                </p>
            </div>
            <button
                wire:click="resumeSubscription"
                class="flex-shrink-0 rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600"
            >
                Resume Subscription
            </button>
        </div>
    @endif

    {{-- Action buttons --}}
    <div class="flex flex-wrap items-center gap-2">
        <x-button :href="route('settings.subscription.change-plan')" tag="a">
            <x-phosphor-arrows-clockwise class="mr-1.5 h-4 w-4" />
            Change Plan
        </x-button>
        <x-button :href="route('settings.subscription.payment-methods')" tag="a" color="gray">
            <x-phosphor-credit-card class="mr-1.5 h-4 w-4" />
            Payment Methods
        </x-button>
    </div>

    {{-- Cancel subscription link --}}
    @if(! $cancellation_scheduled)
        <div class="pt-1">
            <x-filament::modal width="lg" id="cancel-subscription-modal">
                <x-slot name="trigger">
                    <button type="button" class="text-sm text-zinc-400 underline-offset-2 transition-colors hover:text-red-500 hover:underline dark:text-zinc-500 dark:hover:text-red-400">
                        Cancel subscription
                    </button>
                </x-slot>
                <div class="relative flex w-full flex-col items-center justify-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                        <x-phosphor-warning-duotone class="h-6 w-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="mb-5 mt-3 text-center">
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Cancel your subscription?</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm text-zinc-500 dark:text-zinc-400">
                            Your subscription will remain active until the end of your current billing period. After that, you'll lose access to your plan features.
                        </p>
                    </div>
                    <div class="flex w-full items-center gap-3">
                        <x-button x-on:click="$dispatch('close-modal', { id: 'cancel-subscription-modal' })" color="gray" class="w-1/2">Keep My Plan</x-button>
                        <x-button wire:click="cancelSubscription" color="danger" class="w-1/2">Cancel Subscription</x-button>
                    </div>
                </div>
            </x-filament::modal>
        </div>
    @endif
</div>
