<?php

namespace App\Filament\Resources\Plans\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Wave\Subscription;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stripe_id')
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Subscriber')
                    ->description(fn (Subscription $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('user.name')
                    ->label('User')
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
