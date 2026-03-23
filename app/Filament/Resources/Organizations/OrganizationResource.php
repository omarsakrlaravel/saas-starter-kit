<?php

namespace App\Filament\Resources\Organizations;

use App\Actions\Billing\AdjustSubscriptionSeats;
use App\Actions\Billing\CancelSubscription;
use App\Enums\AccountStatus;
use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\StatusHistoryRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SubscriptionsRelationManager;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-buildings-duotone';

    protected static ?int $navigationSort = 4;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization Details')
                    ->description('Core identity and metadata')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(191),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(191),
                        Toggle::make('active'),
                        Select::make('owner_user_id')
                            ->relationship('owner', 'email')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Account Status')
                    ->description('Current account status (change via table actions)')
                    ->schema([
                        Placeholder::make('status_display')
                            ->label('Status')
                            ->content(fn (?Organization $record): string => $record?->statusDisplay() ?? AccountStatus::Active->label()),
                        Placeholder::make('status_reason_display')
                            ->label('Reason')
                            ->content(fn (?Organization $record): string => $record?->status_reason ?? '—')
                            ->visible(fn (?Organization $record): bool => filled($record?->status_reason)),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members'),
                TextColumn::make('current_plan')
                    ->label('Plan')
                    ->getStateUsing(fn (Organization $record): string => $record->activeSubscription()?->plan?->name ?? '—'),
                TextColumn::make('seats_display')
                    ->label('Seats')
                    ->getStateUsing(function (Organization $record): string {
                        $subscription = $record->activeSubscription();

                        if (! $subscription) {
                            return '—';
                        }

                        return $record->occupiedSeatCount().' / '.$subscription->quantity;
                    }),
                BooleanColumn::make('active')
                    ->sortable(),
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
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(AccountStatus::cases())->mapWithKeys(fn (AccountStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                Filter::make('has_subscription')
                    ->label('Has Subscription')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('subscriptions', fn (Builder $q) => $q->where('stripe_status', 'active'))),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Organization $record): bool => ! $record->isSuspended())
                    ->requiresConfirmation()
                    ->modalDescription('This will change the organization account status. Restricted organizations can access billing and support. Suspended organizations are fully blocked.')
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
                    ->action(function (Organization $record, array $data): void {
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
                    ->visible(fn (Organization $record): bool => $record->isRestricted() || $record->isSuspended())
                    ->requiresConfirmation()
                    ->modalDescription('This will restore the organization to active status.')
                    ->action(function (Organization $record): void {
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
                Action::make('cancel_subscription')
                    ->label('Cancel Subscription')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will cancel the active subscription for this organization via Stripe.')
                    ->visible(fn (Organization $record): bool => $record->activeSubscription() !== null)
                    ->action(function (Organization $record): void {
                        $subscription = $record->activeSubscription();

                        if (! $subscription) {
                            Notification::make()->title('No active subscription found.')->warning()->send();

                            return;
                        }

                        $result = app(CancelSubscription::class)->execute($subscription);
                        Notification::make()->title($result->message)->{$result->success ? 'success' : 'danger'}()->send();
                    }),
                Action::make('manage_seats')
                    ->label('Manage Seats')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->visible(fn (Organization $record): bool => $record->activeSubscription() !== null && ! empty($record->activeSubscription()?->stripe_id))
                    ->form([
                        TextInput::make('quantity')
                            ->label('New Seat Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn (Organization $record): int => $record->activeSubscription()?->quantity ?? 1),
                    ])
                    ->action(function (Organization $record, array $data): void {
                        $subscription = $record->activeSubscription();

                        if (! $subscription) {
                            return;
                        }

                        $newQuantity = (int) $data['quantity'];

                        if ($newQuantity < $record->occupiedSeatCount()) {
                            Notification::make()
                                ->title('Cannot reduce seats below occupied count ('.$record->occupiedSeatCount().').')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = app(AdjustSubscriptionSeats::class)->execute($subscription, $newQuantity);
                        Notification::make()->title($result->message)->{$result->success ? 'success' : 'danger'}()->send();
                    }),
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
            MembersRelationManager::class,
            SubscriptionsRelationManager::class,
            InvoicesRelationManager::class,
            StatusHistoryRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OrganizationFeaturesWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
