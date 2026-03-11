<?php

namespace App\Filament\Resources\Members;

use App\Enums\FileAccessLevel;
use App\Filament\Clusters\MembersManager;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Wave\Services\FileService;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-users-duotone';

    protected static ?string $cluster = MembersManager::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'members';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Members';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'admin'))
            ->where('id', '!=', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Member Details')
                            ->description('Basic member account information')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(191),
                                TextInput::make('username')
                                    ->required()
                                    ->maxLength(191),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(191),
                                TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $context): bool => $context === 'create'),
                                FileUpload::make('avatar')
                                    ->image()
                                    ->disk('local')
                                    ->directory('avatars')
                                    ->visibility('private')
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, ?User $record) {
                                        if (! $record) {
                                            return null;
                                        }

                                        $fileService = app(FileService::class);

                                        $oldFile = $record->avatarFile;
                                        if ($oldFile) {
                                            $fileService->delete($oldFile);
                                            $record->unsetRelation('avatarFile');
                                        }

                                        $uploadedFile = new \Illuminate\Http\UploadedFile(
                                            $file->getRealPath(),
                                            $file->getClientOriginalName(),
                                            $file->getMimeType(),
                                        );

                                        $fileRecord = $fileService->store(
                                            file: $uploadedFile,
                                            user: $record,
                                            directory: 'avatars',
                                            accessLevel: FileAccessLevel::AppPublic,
                                            fileable: $record,
                                        );

                                        return $fileRecord->uuid;
                                    })
                                    ->getUploadedFileUsing(function (?User $record): ?array {
                                        if (! $record) {
                                            return null;
                                        }

                                        $file = $record->avatarFile;
                                        if (! $file) {
                                            return null;
                                        }

                                        $fileService = app(FileService::class);

                                        return [
                                            'name' => $file->original_name,
                                            'size' => $file->size_bytes,
                                            'type' => $file->mime_type,
                                            'url' => $fileService->signedUrl($file),
                                        ];
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->description('Verification settings')
                            ->schema([
                                Toggle::make('verified'),
                                DateTimePicker::make('email_verified_at'),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(url('storage/demo/default.png'))
                    ->getStateUsing(fn (User $record): string => $record->avatar()),
                TextColumn::make('username')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
