<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stripe_id')
            ->columns([
                TextColumn::make('stripe_id')
                    ->label('Transaction ID')
                    ->limit(20)
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'succeeded' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        'partially_refunded' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method_brand')
                    ->label('Payment Method')
                    ->formatStateUsing(fn ($record): string => $record->payment_method_brand
                        ? ucfirst($record->payment_method_brand).' ****'.$record->payment_method_last4
                        : '—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
