<?php

namespace App\Filament\Resources\Coupons\RelationManagers;

use App\Models\CouponRedemption;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Customer'),
                TextColumn::make('discount_amount')
                    ->formatStateUsing(fn (?int $state, CouponRedemption $record): string => currencySymbol($record->coupon?->currency).number_format(($state ?? 0) / 100, 2)),
                TextColumn::make('promotionCode.code')
                    ->label('Promo Code')
                    ->placeholder('—'),
                TextColumn::make('invoice.number')
                    ->label('Invoice')
                    ->placeholder('—'),
                TextColumn::make('redeemed_at')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
