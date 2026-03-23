<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Subscriptions\RelationManagers\TransactionsRelationManager;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-credit-card-duotone';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Subscription';

    protected static ?string $pluralModelLabel = 'Subscriptions';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Subscription')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                TextEntry::make('billable.name')
                                    ->label('Subscriber'),
                                TextEntry::make('billable_type')
                                    ->label('Type')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                TextEntry::make('plan.name')
                                    ->label('Plan'),
                                TextEntry::make('cycle')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'month' => 'Monthly',
                                        'year' => 'Yearly',
                                        'onetime' => 'One-time',
                                        default => $state,
                                    }),
                                TextEntry::make('quantity')
                                    ->label('Seats'),
                                TextEntry::make('stripe_id')
                                    ->label('Stripe ID')
                                    ->copyable()
                                    ->placeholder('--'),
                                TextEntry::make('stripe_price')
                                    ->label('Price ID')
                                    ->copyable()
                                    ->placeholder('--'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        Section::make('Billing Dates')
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Started')
                                    ->dateTime('M j, Y'),
                                TextEntry::make('trial_ends_at')
                                    ->label('Trial Ends')
                                    ->dateTime('M j, Y')
                                    ->placeholder('No trial'),
                                TextEntry::make('last_payment_at')
                                    ->label('Last Payment')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('--'),
                                TextEntry::make('next_payment_at')
                                    ->label('Next Payment')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('--'),
                                TextEntry::make('ends_at')
                                    ->label('Ends At')
                                    ->dateTime('M j, Y H:i')
                                    ->placeholder('Active'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        Section::make('Pending Plan Change')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                TextEntry::make('pendingPlan.name')
                                    ->label('New Plan'),
                                TextEntry::make('pending_cycle')
                                    ->label('New Cycle')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'month' => 'Monthly',
                                        'year' => 'Yearly',
                                        default => $state,
                                    }),
                                TextEntry::make('pending_change_scheduled_at')
                                    ->label('Scheduled For')
                                    ->dateTime('M j, Y'),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->visible(fn (Subscription $record): bool => $record->hasPendingChange()),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                TextEntry::make('stripe_status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'trialing' => 'warning',
                                        'past_due' => 'danger',
                                        'canceled' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Subscriber')
                    ->description(fn (Subscription $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('plan.name')
                    ->sortable(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'warning',
                        'past_due' => 'danger',
                        'canceled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('cycle')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'month' => 'Monthly',
                        'year' => 'Yearly',
                        'onetime' => 'One-time',
                        default => $state,
                    }),
                TextColumn::make('quantity')
                    ->label('Seats'),
                TextColumn::make('next_payment_at')
                    ->label('Next Payment')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('--'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                        'incomplete' => 'Incomplete',
                    ]),
                SelectFilter::make('cycle')
                    ->options([
                        'month' => 'Monthly',
                        'year' => 'Yearly',
                        'onetime' => 'One-time',
                    ]),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('open_in_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Subscription $record): string => 'https://dashboard.stripe.com/subscriptions/'.$record->stripe_id)
                    ->openUrlInNewTab()
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
