<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('billable_type')
                    ->label('Context')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'user' => 'Personal',
                        'organization' => 'Organization',
                        default => $state,
                    }),
                TextColumn::make('next_payment_at')
                    ->label('Next Payment')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
