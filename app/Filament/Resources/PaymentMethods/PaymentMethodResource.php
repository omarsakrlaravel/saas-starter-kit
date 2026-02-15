<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use Wave\PaymentMethod;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-wallet-duotone';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Payment Method';

    protected static ?string $pluralModelLabel = 'Payment Methods';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Payment Method Details')
                    ->schema([
                        Placeholder::make('type')
                            ->content(fn (?PaymentMethod $record): string => $record?->type ?? '—'),
                        Placeholder::make('brand')
                            ->content(fn (?PaymentMethod $record): string => $record?->brand ? ucfirst($record->brand) : '—'),
                        Placeholder::make('last4')
                            ->label('Last 4 Digits')
                            ->content(fn (?PaymentMethod $record): string => $record?->last4 ? '****'.$record->last4 : '—'),
                        Placeholder::make('expiration')
                            ->label('Expiration')
                            ->content(fn (?PaymentMethod $record): string => $record?->exp_month
                                ? sprintf('%02d/%d', $record->exp_month, $record->exp_year)
                                : '—'),
                        Placeholder::make('is_default')
                            ->label('Default')
                            ->content(fn (?PaymentMethod $record): string => $record?->is_default ? 'Yes' : 'No'),
                        Placeholder::make('stripe_id')
                            ->label('Stripe ID')
                            ->content(fn (?PaymentMethod $record): string => $record?->stripe_id ?? '—'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Customer')
                    ->description(fn (PaymentMethod $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('brand')
                    ->label('Card')
                    ->formatStateUsing(fn (PaymentMethod $record): string => $record->brand
                        ? ucfirst($record->brand).' ****'.$record->last4
                        : '—'),
                TextColumn::make('exp_month')
                    ->label('Expires')
                    ->formatStateUsing(fn (PaymentMethod $record): string => $record->exp_month
                        ? sprintf('%02d/%d', $record->exp_month, $record->exp_year)
                        : '—'),
                BooleanColumn::make('is_default')
                    ->label('Default'),
            ])
            ->filters([
                SelectFilter::make('type'),
                Filter::make('expiring_soon')
                    ->label('Expiring Soon')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('exp_year', '=', (int) date('Y'))
                        ->where('exp_month', '<=', (int) date('m') + 2)),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
        ];
    }
}
