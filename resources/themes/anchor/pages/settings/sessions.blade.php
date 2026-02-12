<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Wave\ActivityLog;

middleware(['auth', 'verified']);
name('settings.sessions');

new class extends Component
{
    public string $password = '';

    public function with(): array
    {
        return [
            'sessions' => $this->getSessions(),
            'isUsingDatabaseSessions' => config('session.driver') === 'database',
        ];
    }

    public function logoutOtherSessions(): void
    {
        $this->validate([
            'password' => 'required',
        ]);

        if (!Hash::check($this->password, auth()->user()->password)) {
            $this->addError('password', 'The password is incorrect.');
            return;
        }

        $this->password = '';

        DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', session()->getId())
            ->delete();

        ActivityLog::log('sessions_logged_out', 'Logged out all other browser sessions');

        Notification::make()
            ->title('Other browser sessions logged out')
            ->success()
            ->send();
    }

    private function getSessions(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', auth()->id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $agent = $session->user_agent ?? '';

                return (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'is_current' => $session->id === session()->getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'device' => $this->parseDevice($agent),
                    'browser' => $this->parseBrowser($agent),
                ];
            })
            ->toArray();
    }

    private function parseDevice(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') && str_contains($agent, 'Mobile') => 'Android Phone',
            str_contains($agent, 'Android') => 'Android Tablet',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            str_contains($agent, 'CrOS') => 'Chrome OS',
            default => 'Unknown',
        };
    }

    private function parseBrowser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome') && !str_contains($agent, 'Edg/') => 'Chrome',
            str_contains($agent, 'Safari') && !str_contains($agent, 'Chrome') => 'Safari',
            str_contains($agent, 'Firefox') => 'Firefox',
            default => 'Unknown',
        };
    }
};

?>

<x-layouts.app>
    @volt('settings.sessions')
        <div class="relative">
            <x-app.settings-layout
                title="Browser Sessions"
                description="Manage and log out your active sessions on other browsers and devices.">

                <div class="w-full max-w-lg space-y-6">

                    @if(!$isUsingDatabaseSessions)
                        <x-card class="p-6">
                            <div class="flex gap-3">
                                <x-dynamic-component component="phosphor-info-duotone" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Session management requires the database session driver. Update your <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded text-xs">SESSION_DRIVER</code> environment variable to <code class="px-1 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded text-xs">database</code> to enable this feature.
                                </p>
                            </div>
                        </x-card>
                    @else
                        <x-card class="p-6">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">If necessary, you may log out of all of your other browser sessions across all of your devices. Your current session will not be affected.</p>

                            @if(count($sessions) > 0)
                                <div class="space-y-3 mb-6">
                                    @foreach($sessions as $session)
                                        <div class="flex items-center gap-4 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 {{ $session->is_current ? 'bg-green-50/50 dark:bg-green-950/20 border-green-200 dark:border-green-800' : '' }}">
                                            <div class="flex-shrink-0">
                                                @if(in_array($session->device, ['iPhone', 'Android Phone']))
                                                    <x-dynamic-component component="phosphor-device-mobile-duotone" class="w-8 h-8 text-zinc-400 dark:text-zinc-500" />
                                                @elseif(in_array($session->device, ['iPad', 'Android Tablet']))
                                                    <x-dynamic-component component="phosphor-device-tablet-speaker-duotone" class="w-8 h-8 text-zinc-400 dark:text-zinc-500" />
                                                @else
                                                    <x-dynamic-component component="phosphor-desktop-duotone" class="w-8 h-8 text-zinc-400 dark:text-zinc-500" />
                                                @endif
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $session->device }} — {{ $session->browser }}</p>
                                                    @if($session->is_current)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300">This device</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                    {{ $session->ip_address }} — Last active {{ $session->last_active }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">No active sessions found.</p>
                            @endif

                            <div class="space-y-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <div>
                                    <label for="session-password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Confirm Password</label>
                                    <input
                                        type="password"
                                        id="session-password"
                                        wire:model="password"
                                        class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="Enter your password"
                                    >
                                    @error('password')
                                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                <x-button type="button" wire:click="logoutOtherSessions">
                                    Log Out Other Sessions
                                </x-button>
                            </div>
                        </x-card>
                    @endif

                </div>
            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
