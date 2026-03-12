<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Feature Overrides
        </x-slot>

        <x-slot name="headerEnd">
            {{ $this->setFeatureAction }}
        </x-slot>

        <x-slot name="description">
            Explicit feature flag overrides. Features not listed use default resolution.
        </x-slot>

        {{ $this->table }}
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
