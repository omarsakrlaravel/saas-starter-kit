<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\CreateOrganization;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organizations\RelationManagers\SubscriptionsRelationManager;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
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
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
                            Notification::make()
                                ->title('No active subscription found.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($subscription->stripe_id) {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripe->subscriptions->cancel($subscription->stripe_id);
                            } catch (ApiErrorException $e) {
                                Notification::make()
                                    ->title('Stripe error: '.$e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $subscription->update([
                            'stripe_status' => 'canceled',
                            'ends_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Subscription canceled for '.$record->name.'.')
                            ->success()
                            ->send();
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

                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripeSubscription = $stripe->subscriptions->retrieve($subscription->stripe_id);
                            $stripe->subscriptions->update($subscription->stripe_id, [
                                'items' => [
                                    [
                                        'id' => $stripeSubscription->items->data[0]->id,
                                        'quantity' => $newQuantity,
                                    ],
                                ],
                                'proration_behavior' => $newQuantity > $subscription->quantity
                                    ? 'create_prorations'
                                    : 'none',
                            ]);
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $subscription->update(['quantity' => $newQuantity]);

                        Notification::make()
                            ->title('Seats updated to '.$newQuantity.' for '.$record->name.'.')
                            ->success()
                            ->send();
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
