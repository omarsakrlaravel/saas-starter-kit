<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Organization Overrides
        </x-slot>

        <x-slot name="headerEnd">
            {{ $this->addOverrideAction }}
        </x-slot>

        <x-slot name="description">
            Organizations with explicit feature state overrides. Others use default resolution.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
