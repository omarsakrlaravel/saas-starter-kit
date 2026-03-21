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
            return $table->query(\Wave\ApiKey::query()->where('user_id', auth()->user()->id))
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
                        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-medium">This key is shown only once.</p>
                            <p class="mt-1">Copy it now. After you leave this page, only the name will remain visible.</p>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                                <input type="text" readonly value="{{ $newKey }}" class="w-full rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-xs text-zinc-900">
                                <button type="button" x-on:click="window.navigator.clipboard.writeText(@js($newKey))" class="inline-flex items-center justify-center rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100">
                                    Copy key
                                </button>
                            </div>
                        </div>
                    @endif
                    <form wire:submit="add" class="w-full max-w-lg">
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
