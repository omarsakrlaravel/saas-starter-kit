<div class="relative w-full h-auto">
    <div class="flex items-start space-x-2">
        <x-button :href="route('stripe.portal')" tag="a" class="flex-shrink-0">
            Manage Subscription
        </x-button>
    </div>

    @if($cancellation_scheduled && ! is_null($subscription_ends_at))
        <p class="my-3 text-red-600">
            Your subscription is scheduled to cancel on {{ \Carbon\Carbon::parse($subscription_ends_at)->format('F jS, Y') }}.
            Open the billing portal to resume or update it.
        </p>
    @endif
</div>
