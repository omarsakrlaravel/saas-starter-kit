<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('action')
                    ->searchable(),
                TextColumn::make('description'),
                TextColumn::make('ip_address'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
