<?php
    use Filament\Forms\Components\TextInput;
    use Livewire\Volt\Component;
    use function Laravel\Folio\{middleware, name};
    use Filament\Forms\Concerns\InteractsWithForms;
    use Filament\Forms\Contracts\HasForms;
    use Filament\Actions\Concerns\InteractsWithActions;
    use Filament\Actions\Contracts\HasActions;
    use Filament\Forms\Form;
    use Filament\Schemas\Schema;
    use Filament\Notifications\Notification;
    use Filament\Tables;
    use Filament\Tables\Table;
    use Filament\Tables\Columns\TextColumn;
    use Filament\Actions\DeleteAction;
    use Filament\Actions\EditAction;

    use Wave\ActivityLog;
    
    middleware(['auth', 'verified']);
    name('settings.api');

	new class extends Component implements HasForms, HasActions, Tables\Contracts\HasTable
	{
        use InteractsWithForms, InteractsWithActions, Tables\Concerns\InteractsWithTable;
        
        public $keys = [];

        public ?array $data = [];
        public ?string $newKey = null;

        public function mount(): void
        {
            $this->form->fill();
            $this->refreshKeys();
        }

        public function form(Schema $schema): Schema
        {
            return $schema
                ->components([
                    TextInput::make('name')
                        ->label('Create a new API key')
                        ->required()
                        ->maxLength(255)
                ])
                ->statePath('data');
        }

        public function add(): void
        {
            $state = $this->form->getState();
            $this->validate();

            $apiKey = auth()->user()->createApiKey($state['name']);
            $this->newKey = $apiKey->plainTextToken;

            ActivityLog::log('api_key_created', 'API key created: '.$state['name'], [
                'key_name' => $state['name'],
            ]);

            Notification::make()
                ->title('Copy your new API key now')
                ->success()
                ->send();

            $this->form->fill();
            $this->refreshKeys();
        }

        public function table(Table $table): Table
        {
            return $table->query(auth()->user()->tokens()->getQuery())
                ->columns([
                    TextColumn::make('name'),
                    TextColumn::make('created_at')->label('Created'),
                    TextColumn::make('last_used_at')
                        ->label('Last Used')
                        ->dateTime()
                        ->placeholder('Never'),
                ])
                ->actions([
                    EditAction::make()
                        ->slideOver()
                        ->modalWidth('md')
                        ->form([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->after(function ($record) {
                            ActivityLog::log('api_key_updated', 'API key updated: '.$record->name, [
                                'key_name' => $record->name,
                            ]);
                        }),
                    DeleteAction::make()
                        ->after(function ($record) {
                            ActivityLog::log('api_key_deleted', 'API key deleted: '.$record->name, [
                                'key_name' => $record->name,
                            ]);
                        }),
                ]);
        }

        public function refreshKeys(): void
        {
            $this->keys = auth()->user()->apiKeys;
        }
	}

?>

<x-layouts.app>
    @volt('settings.api') 
        <div class="relative">
            <x-app.settings-layout
                title="API Keys"
                description="Manage your API Keys"
            >
                <div class="flex flex-col">
                    @if($newKey)
                        <div class="mb-6 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 p-4 text-sm" x-data="{ copied: false }">
                            <div class="flex gap-3">
                                <x-dynamic-component component="phosphor-key-duotone" class="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-blue-900 dark:text-blue-100">Your new API key</p>
                                    <p class="mt-0.5 text-blue-700 dark:text-blue-300">Copy it now. It will not be shown again.</p>
                                    <div class="mt-3 flex items-center gap-2">
                                        <code class="min-w-0 flex-1 truncate rounded-md border border-blue-200 dark:border-blue-700 bg-white dark:bg-blue-950/50 px-3 py-2 font-mono text-xs text-zinc-800 dark:text-zinc-200 select-all">{{ $newKey }}</code>
                                        <button
                                            type="button"
                                            x-on:click="window.navigator.clipboard.writeText(@js($newKey)); copied = true; setTimeout(() => copied = false, 2000)"
                                            class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-md border border-blue-200 dark:border-blue-700 bg-white dark:bg-blue-950/50 px-3 py-2 text-xs font-medium text-blue-700 dark:text-blue-300 transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/50"
                                        >
                                            <template x-if="!copied">
                                                <span class="inline-flex items-center gap-1.5">
                                                    <x-dynamic-component component="phosphor-copy" class="h-3.5 w-3.5" />
                                                    Copy
                                                </span>
                                            </template>
                                            <template x-if="copied">
                                                <span class="inline-flex items-center gap-1.5 text-green-600 dark:text-green-400">
                                                    <x-dynamic-component component="phosphor-check" class="h-3.5 w-3.5" />
                                                    Copied
                                                </span>
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    <form wire:submit="add" class="w-full">
                        {{ $this->form }}
                        <div class="w-full pt-6 text-right">
                            <x-button type="submit">Create New Key</x-button>
                        </div>
                    </form>
                    <hr class="my-8 border-zinc-200">
                    <x-elements.label class="block text-sm font-medium leading-5 text-zinc-700">Current API Keys</x-elements.label>
                    <div class="pt-5">
                        {{ $this->table }}
                    </div>
                </div>
            </x-app.settings-layout>
        </div>
    @endvolt
</x-layouts.app>
