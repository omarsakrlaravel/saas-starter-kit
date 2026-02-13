<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Models\Organization;
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

    protected function syncOwnerIfNeeded($record): void
    {
        if ($record->pivot->role !== 'owner') {
            return;
        }

        /** @var Organization $organization */
        $organization = $this->getOwnerRecord();

        if ($organization->owner_user_id === $record->id) {
            return;
        }

        $previousOwnerId = $organization->owner_user_id;

        $organization->update(['owner_user_id' => $record->id]);

        if ($previousOwnerId) {
            $organization->members()->updateExistingPivot($previousOwnerId, [
                'role' => 'member',
            ]);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Adding or removing members here bypasses Stripe seat billing. Use the organization settings page for billing-integrated management.')
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
                    ])
                    ->after(function ($record): void {
                        $this->syncOwnerIfNeeded($record);
                    }),
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
                    ])
                    ->after(function ($record): void {
                        $this->syncOwnerIfNeeded($record);
                    }),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
