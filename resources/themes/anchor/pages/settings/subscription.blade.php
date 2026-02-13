<?php
    
    use Filament\Forms\Components\TextInput;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Contracts\HasForms;
    use Filament\Forms\Form;
    use Filament\Notifications\Notification;
    
    middleware(['auth', 'verified']);
    name('settings.subscription');

    new class extends Component
	{
        public function mount(): void
        {
            
        }
    }

?>

<x-layouts.app>
    @volt('settings.subscription') 
        <div class="relative">
            <x-app.settings-layout
                title="Subscriptions"
                description="Your subscription details"
            >
                @php
                    $billingOrganizationsEnabled = config('wave.organizations_enabled', true);
                    $memberOrganizations = $billingOrganizationsEnabled
                        ? auth()->user()->organizations()
                            ->wherePivot('status', 'active')
                            ->where('organizations.active', true)
                            ->orderBy('organizations.name')
                            ->get()
                        : collect();
                @endphp
                <div class="p-4 mb-6 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Billing Context</h3>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Choose where your subscription should be managed.</p>
                    <form class="flex flex-col gap-3 mt-4 sm:flex-row sm:items-end" method="POST" action="{{ route('settings.billing-context') }}">
                        @csrf
                        <label for="current_organization_id" class="w-full">
                            <span class="sr-only">Billing context</span>
                            <select
                                id="current_organization_id"
                                name="current_organization_id"
                                class="w-full rounded-md border border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100"
                            >
                                <option value="">Personal</option>
                                @foreach($memberOrganizations as $organization)
                                    <option value="{{ $organization->id }}" @selected(auth()->user()->current_organization_id == $organization->id)>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-500"
                        >
                            Update Context
                        </button>
                    </form>
                </div>
                @role('admin')
                    <x-app.alert id="no_subscriptions" :dismissable="false" type="info">
                        You are logged in as an admin and have full access. Authenticate with a different user and visit this page to see the subscription checkout process.
                    </x-app.alert>
                @else
                    @subscriber
                        
                        <div class="relative w-full h-auto">                            
                            <x-app.alert id="no_subscriptions" :dismissable="false" type="success">
                                <div class="flex items-center w-full">
                                    <x-phosphor-seal-check-duotone class="flex-shrink-0 mr-1.5 -ml-1.5 w-6 h-6" /> 
                                    <span>You are currently subscribed to the {{ auth()->user()->plan()->name }} {{ auth()->user()->planInterval() }} Plan.</span>
                                </div>
                            </x-app.alert>
                            <p class="my-4">Manage your subscription by clicking below. Edit this page from the following file:  <x-code-inline>resources/themes/anchor/pages/settings/subscription.blade.php</x-code-inline></p>
                            @if (session('update'))
                                <div class="my-4 text-sm text-green-600">Successfully updated your subscription</div>
                            @endif
                            <livewire:billing.update />
                        </div>
                    @endsubscriber

                    @notsubscriber
                        <div class="mb-4">
                            <x-app.alert id="no_subscriptions" :dismissable="false" type="info">
                                <div class="flex items-center space-x-1.5">
                                    <x-phosphor-shopping-bag-open-duotone class="flex-shrink-0 mr-1.5 -ml-1.5 w-6 h-6" />
                                    <span>No active subscriptions found. Please select a plan below.</span>
                                </div>
                            </x-app.alert>
                        </div>
                        <livewire:billing.checkout />
                        <p class="flex items-center mt-3 mb-4">
                            <x-phosphor-shield-check-duotone class="w-4 h-4 mr-1" />
                            <span class="mr-1">Billing is securely managed via </span><strong>{{ ucfirst(config('wave.billing_provider')) }} Payment Platform</strong>.
                        </p>
                    @endnotsubscriber
                @endrole
            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
