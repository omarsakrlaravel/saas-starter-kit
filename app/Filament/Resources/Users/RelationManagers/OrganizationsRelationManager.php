<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationsRelationManager extends RelationManager
{
    protected static string $relationship = 'organizations';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('pivot.joined_at')
                    ->label('Joined')
                    ->dateTime(),
                BooleanColumn::make('active'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
