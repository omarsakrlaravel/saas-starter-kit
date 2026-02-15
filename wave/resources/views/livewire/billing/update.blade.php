<div class="relative w-full">
    <div class="flex flex-wrap items-center gap-2">
        <x-button :href="route('stripe.portal')" tag="a" class="flex-shrink-0">
            Manage Billing
        </x-button>
        <a href="#available-plans" class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-emerald-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-900/20 dark:hover:text-emerald-300">
            <x-phosphor-arrows-clockwise class="h-4 w-4" />
            Change Plan
        </a>
    </div>

    @if($cancellation_scheduled && ! is_null($subscription_ends_at))
        <p class="mt-3 text-sm text-red-600 dark:text-red-400">
            Your subscription is scheduled to cancel on {{ \Carbon\Carbon::parse($subscription_ends_at)->format('F jS, Y') }}.
            Open the billing portal to resume or update it.
        </p>
    @endif
</div>
