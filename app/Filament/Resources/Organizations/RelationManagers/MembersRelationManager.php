<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(url('storage/demo/default.png')),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge(),
                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('pivot.joined_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->options([
                                'member' => 'Member',
                                'owner' => 'Owner',
                            ])
                            ->default('member')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        Select::make('role')
                            ->options([
                                'member' => 'Member',
                                'owner' => 'Owner',
                            ])
                            ->required(),
                    ]),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
