<x-filament-panels::page>
    {{-- Kill Switch Section --}}
    <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 dark:border-danger-700 dark:bg-danger-950/20">
        <div class="mb-3 flex items-center gap-2">
            <x-filament::icon
                icon="heroicon-o-shield-exclamation"
                class="h-5 w-5 text-danger-600 dark:text-danger-400"
            />
            <h3 class="text-base font-semibold text-danger-800 dark:text-danger-200">
                Kill Switches
            </h3>
        </div>
        <p class="mb-4 text-sm text-danger-700 dark:text-danger-300">
            Kill switches immediately affect all users. Activating a kill switch means the feature is engaged (users are blocked from the feature).
        </p>

        @php
            $killSwitches = $this->getKillSwitches();
        @endphp

        @if ($killSwitches->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No kill switches defined.</p>
        @else
            <div class="space-y-3">
                @foreach ($killSwitches as $switch)
                    @php
                        $isActive = $this->isKillSwitchActive($switch);
                    @endphp
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ $switch->name }}
                                </span>
                                @if ($isActive)
                                    <span class="inline-flex items-center rounded-full bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-800 dark:bg-danger-900/30 dark:text-danger-300">
                                        ENGAGED
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900/30 dark:text-success-300">
                                        OFF
                                    </span>
                                @endif
                            </div>
                            @if ($switch->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $switch->description }}
                                </p>
                            @endif
                            @if ($switch->changedBy)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    Last changed by {{ $switch->changedBy->name }} &middot; {{ $switch->updated_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                        <div>
                            <button
                                type="button"
                                wire:click="toggleKillSwitch({{ $switch->id }})"
                                wire:confirm="{{ $isActive ? 'Deactivate this kill switch? The feature will become available to users again.' : 'Activate this kill switch? This will immediately block access to this feature for ALL users.' }}"
                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ $isActive ? 'bg-danger-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                                role="switch"
                                aria-checked="{{ $isActive ? 'true' : 'false' }}"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isActive ? 'translate-x-5' : 'translate-x-0' }}"
                                ></span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Feature Flags Table --}}
    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
