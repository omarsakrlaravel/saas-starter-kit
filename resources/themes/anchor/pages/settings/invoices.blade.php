<?php
    use function Laravel\Folio\{middleware, name};
    middleware(['auth', 'verified']);
    name('settings.invoices');
?>

@php
    $invoices = auth()->user()->billingInvoices();
@endphp

<x-layouts.app>
    <div class="relative">
        <x-app.settings-layout
            title="Invoices"
            description="Your past plan invoices"
        >
            @empty($invoices)
                <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-12 text-center dark:border-zinc-700 dark:bg-zinc-800/30">
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <x-phosphor-invoice-duotone class="h-6 w-6 text-zinc-400" />
                    </div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">No invoices yet</h3>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">When you subscribe to a plan, your invoices will appear here.</p>
                </div>
            @else
                <div
                    x-data="{
                        page: 1,
                        perPage: 10,
                        invoices: {{ Js::from($invoices) }},
                        get totalPages() { return Math.ceil(this.invoices.length / this.perPage) },
                        get paginatedInvoices() { return this.invoices.slice((this.page - 1) * this.perPage, this.page * this.perPage) },
                        get showing() { return { from: (this.page - 1) * this.perPage + 1, to: Math.min(this.page * this.perPage, this.invoices.length), total: this.invoices.length } },
                    }"
                    class="w-full space-y-4"
                >
                    {{-- Invoice list --}}
                    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                            <div class="flex items-center gap-4 bg-white px-5 py-4 transition-colors hover:bg-zinc-50/80 dark:bg-zinc-900 dark:hover:bg-zinc-800/60">
                                {{-- Status icon --}}
                                <div class="hidden shrink-0 sm:block">
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full"
                                        :class="invoice.status_value === 'paid'
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30'
                                            : invoice.status_value === 'open'
                                                ? 'bg-amber-50 dark:bg-amber-900/30'
                                                : 'bg-zinc-100 dark:bg-zinc-800'"
                                    >
                                        <x-phosphor-check-circle-duotone
                                            class="h-5 w-5"
                                            ::class="invoice.status_value === 'paid'
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : invoice.status_value === 'open'
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-zinc-400 dark:text-zinc-500'"
                                        />
                                    </div>
                                </div>

                                {{-- Invoice details --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-2">
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100" x-text="invoice.total"></p>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500" x-text="invoice.reason"></span>
                                    </div>
                                    <template x-if="invoice.line_items && invoice.line_items.length > 0 && !invoice.line_items.some(i => i.is_proration)">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="(item, idx) in invoice.line_items.slice(0, 2)" :key="idx">
                                                <p class="truncate text-xs text-zinc-600 dark:text-zinc-300" x-text="item.description"></p>
                                            </template>
                                            <template x-if="invoice.line_items.length > 2">
                                                <p class="text-xs text-zinc-400 dark:text-zinc-500" x-text="'+' + (invoice.line_items.length - 2) + ' more in PDF'"></p>
                                            </template>
                                        </div>
                                    </template>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span class="flex items-center gap-1">
                                            <x-phosphor-calendar-blank class="h-3.5 w-3.5" />
                                            <span x-text="invoice.created"></span>
                                        </span>
                                        <span class="flex items-center gap-1 font-mono">
                                            <x-phosphor-hash class="h-3.5 w-3.5" />
                                            <span x-text="invoice.number"></span>
                                        </span>
                                    </div>
                                </div>

                                {{-- Status badge --}}
                                <span
                                    class="hidden shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium sm:inline-flex"
                                    :class="invoice.status_value === 'paid'
                                        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-500/30'
                                        : invoice.status_value === 'open'
                                            ? 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/30'
                                            : invoice.status_value === 'uncollectible'
                                                ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-900/30 dark:text-rose-400 dark:ring-rose-500/30'
                                                : 'bg-zinc-100 text-zinc-600 ring-1 ring-inset ring-zinc-500/10 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-500/20'"
                                    x-text="invoice.status"
                                ></span>

                                {{-- Download button --}}
                                <a
                                    :href="invoice.download"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-400 transition-all hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-500 dark:hover:border-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300"
                                    title="Download PDF"
                                >
                                    <x-phosphor-download-simple class="h-4 w-4" />
                                </a>
                            </div>
                        </template>
                    </div>

                    {{-- Pagination --}}
                    <div x-show="totalPages > 1" x-cloak class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Showing <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="showing.from"></span>
                            to <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="showing.to"></span>
                            of <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="showing.total"></span>
                        </p>
                        <div class="flex items-center gap-1">
                            <button
                                @click="page = Math.max(1, page - 1)"
                                :disabled="page === 1"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-zinc-200 text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-zinc-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                            >
                                <x-phosphor-caret-left class="h-4 w-4" />
                            </button>
                            <template x-for="p in totalPages" :key="p">
                                <button
                                    @click="page = p"
                                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-medium transition-colors"
                                    :class="p === page
                                        ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-300'"
                                    x-text="p"
                                ></button>
                            </template>
                            <button
                                @click="page = Math.min(totalPages, page + 1)"
                                :disabled="page === totalPages"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-zinc-200 text-zinc-500 transition-colors hover:bg-zinc-50 hover:text-zinc-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                            >
                                <x-phosphor-caret-right class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @endempty
        </x-app.settings-layout>
    </div>
</x-layouts.app>
