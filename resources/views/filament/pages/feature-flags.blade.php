<x-filament-panels::page>
    {{-- Kill Switch Section --}}
    @php
        $killSwitches = $this->getKillSwitches();
    @endphp

    @if ($killSwitches->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-shield-exclamation"
                        class="h-5 w-5 text-danger-500"
                    />
                    Kill Switches
                </div>
            </x-slot>

            <x-slot name="description">
                Kill switches immediately affect all users. Activating a kill switch blocks access to the feature globally.
            </x-slot>

            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($killSwitches as $switch)
                    @php
                        $isActive = $this->isKillSwitchActive($switch);
                    @endphp
                    <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $switch->name }}
                                </span>
                                @if ($isActive)
                                    <x-filament::badge color="danger" size="sm">
                                        ENGAGED
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="success" size="sm">
                                        OFF
                                    </x-filament::badge>
                                @endif
                            </div>
                            @if ($switch->description)
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $switch->description }}
                                </p>
                            @endif
                            @if ($switch->changedBy)
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    Last changed by {{ $switch->changedBy->name }} &middot; {{ $switch->updated_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0">
                            {{ ($this->toggleKillSwitchAction)(['id' => $switch->id, 'active' => $isActive, 'name' => $switch->name]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Feature Flags Table --}}
    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>
