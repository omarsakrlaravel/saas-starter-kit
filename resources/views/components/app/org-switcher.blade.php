@php
    $user = auth()->user();
    $organizationsEnabled = config('saas.organizations_enabled', true);
    $currentOrg = null;
    $userOrganizations = collect();

    if ($organizationsEnabled && $user) {
        $userOrganizations = $user->organizations()
            ->wherePivot('status', 'active')
            ->where('organizations.active', true)
            ->orderBy('organizations.name')
            ->get();

        if ($user->current_organization_id) {
            $currentOrg = $userOrganizations->firstWhere('id', $user->current_organization_id);
        }
    }

    $showSwitcher = $organizationsEnabled && $userOrganizations->count() > 0;
@endphp

@if($showSwitcher)
    <div x-data="{ open: false }" class="relative px-4 pb-1">
        <button
            @click="open = !open"
            @keydown.escape.window="open = false"
            class="flex items-center justify-between w-full gap-2 px-3 py-2 text-sm rounded-lg transition-colors
                hover:bg-zinc-200/70 dark:hover:bg-zinc-700/60
                {{ $currentOrg ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400' }}"
        >
            <span class="flex items-center gap-2.5 min-w-0">
                @if($currentOrg)
                    <span class="flex items-center justify-center w-6 h-6 rounded-md text-xs font-bold bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 flex-shrink-0">
                        {{ strtoupper(substr($currentOrg->name, 0, 1)) }}
                    </span>
                    <span class="truncate font-medium">{{ $currentOrg->name }}</span>
                @else
                    <span class="flex items-center justify-center w-6 h-6 rounded-md flex-shrink-0">
                        <x-phosphor-user class="w-4 h-4" />
                    </span>
                    <span class="truncate font-medium">Personal Account</span>
                @endif
            </span>
            <x-phosphor-caret-up-down class="w-4 h-4 flex-shrink-0 text-zinc-400" />
        </button>

        {{-- Dropdown --}}
        <div
            x-show="open"
            @click.away="open = false"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute left-4 right-4 z-50 mt-1 origin-top bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-lg overflow-hidden"
            x-cloak
        >
            <div class="px-3 pt-2.5 pb-1.5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Switch context</p>
            </div>

            <div class="px-1.5 pb-1.5">
                {{-- Personal Account --}}
                <form method="POST" action="{{ route('settings.billing-context') }}">
                    @csrf
                    <input type="hidden" name="current_organization_id" value="">
                    <button
                        type="submit"
                        @click="open = false"
                        class="flex items-center gap-2.5 w-full px-2.5 py-2 text-sm rounded-lg transition-colors
                            {{ !$currentOrg ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/60 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                    >
                        <span class="flex items-center justify-center w-6 h-6 rounded-md flex-shrink-0 {{ !$currentOrg ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-600' }}">
                            <x-phosphor-user-bold class="w-3.5 h-3.5" />
                        </span>
                        <span class="truncate">Personal Account</span>
                        @if(!$currentOrg)
                            <x-phosphor-check-bold class="w-3.5 h-3.5 ml-auto flex-shrink-0 text-zinc-900 dark:text-zinc-100" />
                        @endif
                    </button>
                </form>

                {{-- Organizations --}}
                @foreach($userOrganizations as $org)
                    <form method="POST" action="{{ route('settings.billing-context') }}">
                        @csrf
                        <input type="hidden" name="current_organization_id" value="{{ $org->id }}">
                        <button
                            type="submit"
                            @click="open = false"
                            class="flex items-center gap-2.5 w-full px-2.5 py-2 text-sm rounded-lg transition-colors
                                {{ $currentOrg?->id === $org->id ? 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/60 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                        >
                            <span class="flex items-center justify-center w-6 h-6 rounded-md text-xs font-bold flex-shrink-0
                                {{ $currentOrg?->id === $org->id ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-700 dark:text-zinc-300' }}">
                                {{ strtoupper(substr($org->name, 0, 1)) }}
                            </span>
                            <span class="truncate">{{ $org->name }}</span>
                            @if($org->owner_user_id === $user->id)
                                <span class="text-[10px] text-zinc-400 dark:text-zinc-500 flex-shrink-0">Owner</span>
                            @endif
                            @if($currentOrg?->id === $org->id)
                                <x-phosphor-check-bold class="w-3.5 h-3.5 ml-auto flex-shrink-0 text-zinc-900 dark:text-zinc-100" />
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>

            {{-- Footer actions --}}
            <div class="border-t border-zinc-200 dark:border-zinc-700 px-1.5 py-1.5">
                <a
                    href="{{ route('settings.organization') }}"
                    wire:navigate
                    @click="open = false"
                    class="flex items-center gap-2.5 w-full px-2.5 py-2 text-sm rounded-lg text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/60 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
                >
                    <x-phosphor-gear class="w-4 h-4 flex-shrink-0" />
                    <span>Manage Organization</span>
                </a>
            </div>
        </div>
    </div>
@endif
