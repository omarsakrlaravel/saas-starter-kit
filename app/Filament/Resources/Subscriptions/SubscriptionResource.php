<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Wave\Subscription;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-credit-card-duotone';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Subscription';

    protected static ?string $pluralModelLabel = 'Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Subscription')
                    ->schema([
                        Select::make('billable_type')
                            ->label('Billable Type')
                            ->options([
                                'user' => 'User',
                                'organization' => 'Organization',
                            ])
                            ->required(),
                        TextInput::make('billable_id')
                            ->numeric()
                            ->required(),
                        TextInput::make('user_id')
                            ->numeric(),
                        TextInput::make('type')
                            ->default('default')
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Select::make('cycle')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                                'onetime' => 'One time',
                            ])
                            ->required()
                            ->default('month'),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1),
                        TextInput::make('stripe_id'),
                        Select::make('stripe_status')
                            ->options([
                                'active' => 'Active',
                                'trialing' => 'Trialing',
                                'past_due' => 'Past Due',
                                'canceled' => 'Canceled',
                                'incomplete' => 'Incomplete',
                            ])
                            ->required()
                            ->default('active'),
                        TextInput::make('stripe_price'),
                        DateTimePicker::make('trial_ends_at'),
                        DateTimePicker::make('ends_at'),
                        DateTimePicker::make('last_payment_at'),
                        DateTimePicker::make('next_payment_at'),
                    ])
                    ->columns(2),
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
                TextColumn::make('next_payment_at')
                    ->label('Next Payment')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Seats')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_id')
                    ->label('Stripe Subscription')
                    ->toggleable(isToggledHiddenByDefault: true),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
