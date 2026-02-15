<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stripe_id')
            ->defaultSort('created_at', 'desc')
            ->columns([
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
                    ->placeholder('—'),
                TextColumn::make('stripe_id')
                    ->label('Stripe ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
