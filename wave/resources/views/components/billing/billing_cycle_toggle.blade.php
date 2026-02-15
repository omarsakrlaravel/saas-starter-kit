<div wire:ignore x-show="billing_cycle_available=='both'"
    x-init="
        setTimeout(function(){
            toggleRepositionMarker($refs.monthly);
            $refs.marker.classList.remove('opacity-0');
            setTimeout(function(){
                $refs.marker.classList.add('duration-300', 'ease-out');
            }, 10);
        }, 1);
    "
    @reposition-interval-marker.window="toggleRepositionMarker($refs.monthly);"
    class="relative mb-5 w-40"
    x-cloak>
    <div x-ref="toggleButtons" class="relative inline-grid h-10 w-full select-none grid-cols-2 items-center justify-center rounded-full bg-white p-1 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
        <button x-ref="monthly" @click="toggleButtonClicked($el, 'month');" type="button"
            :class="{ 'text-white' : billing_cycle_selected == 'month', 'text-zinc-500 dark:text-zinc-400' : billing_cycle_selected != 'month' }"
            class="relative z-20 inline-flex h-8 w-full cursor-pointer items-center justify-center whitespace-nowrap px-3 text-xs font-semibold transition-all">Monthly</button>
        <button x-ref="yearly" @click="toggleButtonClicked($el, 'year');" type="button"
            :class="{ 'text-white' : billing_cycle_selected == 'year', 'text-zinc-500 dark:text-zinc-400' : billing_cycle_selected != 'year' }"
            class="relative z-20 inline-flex h-8 w-full cursor-pointer items-center justify-center whitespace-nowrap rounded-md px-3 text-xs font-semibold transition-all">Yearly</button>
        <div x-ref="marker" class="absolute left-0 z-10 h-full w-1/2 opacity-0" x-cloak>
            <div class="h-full w-full rounded-full bg-zinc-900 shadow-sm dark:bg-zinc-100"></div>
        </div>
    </div>
</div>
