<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'promotionCodes';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('stripe_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                BooleanColumn::make('active'),
                TextColumn::make('times_redeemed'),
                TextColumn::make('max_redemptions')
                    ->placeholder('Unlimited'),
                BooleanColumn::make('first_time_transaction'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(191),
                        Toggle::make('active')
                            ->default(true),
                        TextInput::make('max_redemptions')
                            ->numeric()
                            ->nullable(),
                        Toggle::make('first_time_transaction')
                            ->default(false)
                            ->label('First-time customers only'),
                        TextInput::make('minimum_amount')
                            ->numeric()
                            ->nullable()
                            ->helperText('In cents'),
                        DateTimePicker::make('expires_at')
                            ->nullable(),
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
}
