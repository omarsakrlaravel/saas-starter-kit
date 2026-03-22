<x-filament-widgets::widget class="gap-5 fi-filament-info-widget">
    <section class="flex flex-col gap-5 mb-5 space-x-5 w-full xl:flex-row">
        <x-filament::section class="w-full">
            <div class="flex gap-x-3 items-center w-full">
                <div class="flex-1">
                    <a href="/" rel="noopener noreferrer" target="_blank"><x-logo class="w-auto h-6"></x-logo></a>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">v{{ app()->version() }}</p>
                </div>
                <div class="flex flex-col gap-y-1 items-end">
                    <x-filament::link color="gray" href="#" icon="heroicon-m-book-open" icon-alias="panels::widgets.filament-info.open-documentation-button">
                        Documentation
                    </x-filament::link>
                </div>
            </div>
        </x-filament::section>
        <x-filament::section class="w-full">
            <div class="flex gap-x-3 items-center w-full">
                <div class="flex-1">
                    <h2 class="grid flex-1 text-base font-semibold leading-6 text-gray-950 dark:text-white">Welcome to SaaS Starter Kit</h2>
                </div>
                <x-filament::button color="gray" icon="heroicon-m-arrow-top-right-on-square" icon-alias="panels::widgets.account.logout-button" labeled-from="sm" tag="a" type="submit" href="/" target="_blank">
                    Visit your Site
                </x-filament::button>
            </div>
        </x-filament::section>
    </section>
</x-filament-widgets::widget>
