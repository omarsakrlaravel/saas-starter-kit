<?php
    use Filament\Forms\Components\TextInput;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Contracts\HasForms;
    use Filament\Forms\Form;
    use Filament\Schemas\Schema;
    use Filament\Notifications\Notification;
    use Illuminate\Support\Facades\Hash;
    use PragmaRX\Google2FA\Google2FA;
    use Devdojo\Auth\Actions\TwoFactorAuth\DisableTwoFactorAuthentication;
    use Devdojo\Auth\Actions\TwoFactorAuth\GenerateNewRecoveryCodes;
    use Devdojo\Auth\Actions\TwoFactorAuth\GenerateQrCodeAndSecretKey;
    use Wave\ActivityLog;

    middleware('auth');
    name('settings.security');

	new class extends Component implements HasForms
	{
        use InteractsWithForms;

        public ?array $data = [];

        // 2FA state
        public bool $twoFactorEnabled = false;
        public bool $twoFactorConfirmed = false;
        public string $qrCode = '';
        public string $setupKey = '';
        public string $confirmCode = '';
        public string $twoFactorPassword = '';
        public bool $showingRecoveryCodes = false;
        public array $recoveryCodes = [];

        public function mount(): void
        {
            $this->form->fill();

            $user = auth()->user();
            if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
                $this->twoFactorConfirmed = true;
                $this->twoFactorEnabled = true;
            }
        }

        public function form(Schema $schema): Schema
        {
            return $schema
                ->components([
                    TextInput::make('current_password')
                        ->label('Current Password')
                        ->required()
                        ->currentPassword()
                        ->password()
                        ->revealable(),
                    TextInput::make('password')
                        ->label('New Password')
                        ->required()
                        ->minLength(config('wave.auth.min_password_length'))
                        ->password()
                        ->revealable(),
                    TextInput::make('password_confirmation')
                        ->label('Confirm New Password')
                        ->required()
                        ->password()
                        ->revealable()
                        ->same('password')
                ])
                ->statePath('data');
        }

        public function save(): void
        {
            $state = $this->form->getState();
            $this->validate();

            auth()->user()->forceFill([
                'password' => bcrypt($state['password'])
            ])->save();

            ActivityLog::log(
                'password_changed',
                'Password was successfully changed'
            );

            $this->form->fill();

            Notification::make()
                ->title('Successfully changed password')
                ->success()
                ->send();
        }

        public function enableTwoFactor(): void
        {
            $this->validate([
                'twoFactorPassword' => 'required',
            ]);

            if (!Hash::check($this->twoFactorPassword, auth()->user()->password)) {
                $this->addError('twoFactorPassword', 'The password is incorrect.');
                return;
            }

            $this->twoFactorPassword = '';

            $user = auth()->user();
            $qrGenerator = new GenerateQrCodeAndSecretKey();
            [$this->qrCode, $this->setupKey] = $qrGenerator($user);

            $codesGenerator = new GenerateNewRecoveryCodes();
            $codes = $codesGenerator($user);

            $user->forceFill([
                'two_factor_secret' => encrypt($this->setupKey),
                'two_factor_recovery_codes' => encrypt(json_encode($codes)),
            ])->save();

            $this->twoFactorEnabled = true;

            ActivityLog::log('two_factor_setup_started', 'Two-factor authentication setup initiated');
        }

        public function confirmTwoFactor(): void
        {
            $this->validate([
                'confirmCode' => 'required|digits:6',
            ]);

            $google2fa = new Google2FA();
            $valid = $google2fa->verifyKey($this->setupKey, $this->confirmCode);

            if ($valid) {
                auth()->user()->forceFill([
                    'two_factor_confirmed_at' => now(),
                ])->save();

                $this->twoFactorConfirmed = true;
                $this->confirmCode = '';
                $this->qrCode = '';
                $this->setupKey = '';

                ActivityLog::log('two_factor_enabled', 'Two-factor authentication was enabled');

                Notification::make()
                    ->title('Two-factor authentication enabled')
                    ->success()
                    ->send();
            } else {
                $this->addError('confirmCode', 'Invalid authentication code. Please try again.');
            }
        }

        public function cancelTwoFactorSetup(): void
        {
            auth()->user()->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ])->save();

            $this->twoFactorEnabled = false;
            $this->qrCode = '';
            $this->setupKey = '';
            $this->confirmCode = '';
        }

        public function showRecoveryCodes(): void
        {
            $this->validate([
                'twoFactorPassword' => 'required',
            ]);

            if (!Hash::check($this->twoFactorPassword, auth()->user()->password)) {
                $this->addError('twoFactorPassword', 'The password is incorrect.');
                return;
            }

            $this->twoFactorPassword = '';
            $this->recoveryCodes = json_decode(decrypt(auth()->user()->two_factor_recovery_codes), true);
            $this->showingRecoveryCodes = true;
        }

        public function hideRecoveryCodes(): void
        {
            $this->showingRecoveryCodes = false;
            $this->recoveryCodes = [];
        }

        public function regenerateRecoveryCodes(): void
        {
            $this->validate([
                'twoFactorPassword' => 'required',
            ]);

            if (!Hash::check($this->twoFactorPassword, auth()->user()->password)) {
                $this->addError('twoFactorPassword', 'The password is incorrect.');
                return;
            }

            $this->twoFactorPassword = '';

            $codesGenerator = new GenerateNewRecoveryCodes();
            $codes = $codesGenerator(auth()->user());

            auth()->user()->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode($codes)),
            ])->save();

            $this->recoveryCodes = $codes->toArray();
            $this->showingRecoveryCodes = true;

            ActivityLog::log('two_factor_recovery_codes_regenerated', 'Recovery codes were regenerated');

            Notification::make()
                ->title('Recovery codes regenerated')
                ->body('Your old recovery codes have been invalidated.')
                ->success()
                ->send();
        }

        public function disableTwoFactor(): void
        {
            $this->validate([
                'twoFactorPassword' => 'required',
            ]);

            if (!Hash::check($this->twoFactorPassword, auth()->user()->password)) {
                $this->addError('twoFactorPassword', 'The password is incorrect.');
                return;
            }

            $this->twoFactorPassword = '';

            $disable = new DisableTwoFactorAuthentication();
            $disable(auth()->user());

            $this->twoFactorEnabled = false;
            $this->twoFactorConfirmed = false;
            $this->showingRecoveryCodes = false;
            $this->recoveryCodes = [];

            ActivityLog::log('two_factor_disabled', 'Two-factor authentication was disabled');

            Notification::make()
                ->title('Two-factor authentication disabled')
                ->warning()
                ->send();
        }

	}

?>

<x-layouts.app>
    @volt('settings.security')
        <div class="relative">
            <x-app.settings-layout
                title="Security"
                description="Manage your password and two-factor authentication."
            >
                <div class="w-full max-w-lg space-y-8">

                    {{-- Change Password --}}
                    <x-card class="p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Change Password</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Update your account password.</p>

                        <form wire:submit="save" class="mt-4">
                            {{ $this->form }}
                            <div class="w-full pt-6 text-right">
                                <x-button type="submit">Save</x-button>
                            </div>
                        </form>
                    </x-card>

                    {{-- Two-Factor Authentication --}}
                    <x-card class="p-6">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Two-Factor Authentication</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Add an extra layer of security to your account using a TOTP authenticator app.</p>

                        <div class="mt-4">
                            @if($twoFactorConfirmed)
                                {{-- 2FA is active --}}
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Enabled
                                    </span>
                                </div>

                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">Two-factor authentication is active. You will be prompted for a code from your authenticator app when logging in.</p>

                                @if($showingRecoveryCodes)
                                    <div class="mb-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-2">Recovery Codes</p>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Store these codes in a secure password manager. They can be used to recover access if you lose your authenticator device.</p>
                                        <div class="grid grid-cols-2 gap-1 p-3 font-mono text-sm bg-white dark:bg-zinc-900 rounded border border-zinc-200 dark:border-zinc-700">
                                            @foreach($recoveryCodes as $code)
                                                <div class="text-zinc-700 dark:text-zinc-300">{{ $code }}</div>
                                            @endforeach
                                        </div>
                                        <button wire:click="hideRecoveryCodes" type="button" class="mt-3 text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                            Hide codes
                                        </button>
                                    </div>
                                @endif

                                <div class="space-y-3">
                                    <div>
                                        <label for="2fa-password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Confirm Password</label>
                                        <input
                                            type="password"
                                            id="2fa-password"
                                            wire:model="twoFactorPassword"
                                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            placeholder="Enter your password"
                                        >
                                        @error('twoFactorPassword')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        @if(!$showingRecoveryCodes)
                                            <x-button type="button" wire:click="showRecoveryCodes" size="sm" color="gray">
                                                Show Recovery Codes
                                            </x-button>
                                        @endif
                                        <x-button type="button" wire:click="regenerateRecoveryCodes" size="sm" color="gray">
                                            Regenerate Codes
                                        </x-button>
                                        <x-button type="button" wire:click="disableTwoFactor" size="sm" color="danger">
                                            Disable 2FA
                                        </x-button>
                                    </div>
                                </div>

                            @elseif($twoFactorEnabled)
                                {{-- Setup in progress: show QR code + confirm --}}
                                <div class="space-y-4">
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Scan the QR code below with your authenticator app (Google Authenticator, Authy, etc.), then enter the 6-digit code to confirm.</p>

                                    <div class="flex justify-center p-4 bg-white rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        <img src="data:image/png;base64, {{ $qrCode }}" alt="QR Code" class="w-48 h-48" />
                                    </div>

                                    <div class="text-center">
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">Or enter this setup key manually:</p>
                                        <code class="px-3 py-1.5 text-sm font-mono bg-zinc-100 dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 select-all">{{ $setupKey }}</code>
                                    </div>

                                    <div>
                                        <label for="confirm-code" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Authentication Code</label>
                                        <input
                                            type="text"
                                            id="confirm-code"
                                            wire:model="confirmCode"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            maxlength="6"
                                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-center text-lg tracking-widest"
                                            placeholder="000000"
                                        >
                                        @error('confirmCode')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex gap-2">
                                        <x-button type="button" wire:click="cancelTwoFactorSetup" color="gray">
                                            Cancel
                                        </x-button>
                                        <x-button type="button" wire:click="confirmTwoFactor">
                                            Confirm
                                        </x-button>
                                    </div>
                                </div>

                            @else
                                {{-- 2FA not enabled --}}
                                <div class="p-4 mb-4 bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 rounded-lg">
                                    <div class="flex gap-3">
                                        <x-dynamic-component component="phosphor-shield-check-duotone" class="flex-shrink-0 w-5 h-5 text-blue-600 dark:text-blue-400" />
                                        <p class="text-sm text-blue-800 dark:text-blue-200">When enabled, you'll be prompted for a secure code from your authenticator app during login. This significantly reduces the risk of unauthorized access.</p>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <label for="enable-2fa-password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Confirm Password</label>
                                        <input
                                            type="password"
                                            id="enable-2fa-password"
                                            wire:model="twoFactorPassword"
                                            class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            placeholder="Enter your password"
                                        >
                                        @error('twoFactorPassword')
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <x-button type="button" wire:click="enableTwoFactor">
                                        Enable Two-Factor Authentication
                                    </x-button>
                                </div>
                            @endif
                        </div>
                    </x-card>

                </div>
            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
