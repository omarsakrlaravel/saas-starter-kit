<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Enums\AccountStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'Status History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_code')
            ->defaultSort('applied_at', 'desc')
            ->columns([
                TextColumn::make('from_status')
                    ->badge()
                    ->color(fn (?AccountStatus $state): string => match ($state) {
                        AccountStatus::Active => 'success',
                        AccountStatus::Restricted => 'warning',
                        AccountStatus::Suspended => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?AccountStatus $state): string => $state?->label() ?? '—'),
                TextColumn::make('to_status')
                    ->badge()
                    ->color(fn (AccountStatus $state): string => match ($state) {
                        AccountStatus::Active => 'success',
                        AccountStatus::Restricted => 'warning',
                        AccountStatus::Suspended => 'danger',
                    })
                    ->formatStateUsing(fn (AccountStatus $state): string => $state->label()),
                TextColumn::make('reason')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('reference_code')
                    ->copyable(),
                TextColumn::make('appliedBy.name')
                    ->label('Applied By')
                    ->default('System'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('applied_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
