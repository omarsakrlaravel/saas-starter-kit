<?php

namespace App\Filament\Resources\Users;

use App\Enums\AccountStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\ActivityRelationManager;
use App\Filament\Resources\Users\RelationManagers\ApiKeysRelationManager;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Users\RelationManagers\OrganizationsRelationManager;
use App\Filament\Resources\Users\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Resources\Users\RelationManagers\SubscriptionsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-user-duotone';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('name', 'admin'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('User Details')
                            ->description('Basic user account information')
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
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(2),
                Group::make()
                    ->schema([
                        Section::make('Status & Access')
                            ->description('Roles, verification, and trial settings')
                            ->schema([
                                Select::make('roles')
                                    ->multiple()
                                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('name', '!=', 'admin'))
                                    ->preload()
                                    ->searchable(),
                                Toggle::make('verified'),
                                DateTimePicker::make('email_verified_at'),
                                DateTimePicker::make('trial_ends_at'),
                                TextInput::make('verification_code')
                                    ->maxLength(191),
                            ]),
                        Section::make('Account Status')
                            ->description('Current account status (change via table actions)')
                            ->schema([
                                Placeholder::make('status_display')
                                    ->label('Status')
                                    ->content(fn (?User $record): string => $record?->statusDisplay() ?? AccountStatus::Active->label()),
                                Placeholder::make('status_reason_display')
                                    ->label('Reason')
                                    ->content(fn (?User $record): string => $record?->status_reason ?? '—')
                                    ->visible(fn (?User $record): bool => filled($record?->status_reason)),
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
                    ->defaultImageUrl(url('storage/demo/default.png')),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (AccountStatus $state): string => match ($state) {
                        AccountStatus::Active => 'success',
                        AccountStatus::Restricted => 'warning',
                        AccountStatus::Suspended => 'danger',
                    })
                    ->formatStateUsing(fn (AccountStatus $state): string => $state->label()),
                TextColumn::make('status_reason')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('subscription_status')
                    ->label('Subscription Status')
                    ->options([
                        'subscribed' => 'Subscribed',
                        'trial' => 'Trial',
                        'expired' => 'Expired',
                        'none' => 'None',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'subscribed' => $query->whereHas('subscriptions', fn (Builder $q) => $q->where('stripe_status', 'active')),
                            'trial' => $query->whereHas('subscriptions', fn (Builder $q) => $q->where('stripe_status', 'trialing')),
                            'expired' => $query->whereHas('subscriptions', fn (Builder $q) => $q->where('stripe_status', 'canceled')),
                            'none' => $query->whereDoesntHave('subscriptions'),
                            default => $query,
                        };
                    }),
                Filter::make('has_organization')
                    ->label('Has Organization')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('organizations')),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => ! $record->isSuspended())
                    ->requiresConfirmation()
                    ->modalDescription('This will change the user account status. Restricted users can access billing and support. Suspended users are fully blocked.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->required()
                            ->maxLength(1000),
                        Select::make('status')
                            ->options([
                                'restricted' => 'Restrict (billing/warning)',
                                'suspended' => 'Suspend (full block)',
                            ])
                            ->required()
                            ->default('suspended'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $targetStatus = AccountStatus::from($data['status']);

                        $record->recordStatusTransition(
                            toStatus: $targetStatus,
                            reason: $data['reason'],
                            appliedById: auth()->id(),
                        );

                        Notification::make()
                            ->title($record->name.' is now '.$targetStatus->label().'.')
                            ->success()
                            ->send();
                    }),
                Action::make('unsuspend')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->isRestricted() || $record->isSuspended())
                    ->requiresConfirmation()
                    ->modalDescription('This will restore the user to active status.')
                    ->action(function (User $record): void {
                        $record->recordStatusTransition(
                            toStatus: AccountStatus::Active,
                            reason: 'Unsuspended by admin',
                            appliedById: auth()->id(),
                        );

                        Notification::make()
                            ->title($record->name.' has been restored to active status.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('Impersonate')
                    ->url(fn ($record) => route('impersonate', $record))
                    ->visible(fn ($record) => auth()->user()->id !== $record->id),
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
            SubscriptionsRelationManager::class,
            InvoicesRelationManager::class,
            OrganizationsRelationManager::class,
            ActivityRelationManager::class,
            ApiKeysRelationManager::class,
            StatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
