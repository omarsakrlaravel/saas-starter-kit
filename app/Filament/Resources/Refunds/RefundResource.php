<?php

namespace App\Filament\Resources\Refunds;

use App\Filament\Resources\Refunds\Pages\ListRefunds;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use Wave\Transaction;

class RefundResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-receipt-refund-duotone';

    protected static ?int $navigationSort = 8;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refunds';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Refund Details')
                    ->schema([
                        Placeholder::make('stripe_id')
                            ->label('Transaction ID')
                            ->content(fn (?Transaction $record): string => $record?->stripe_id ?? '—'),
                        Placeholder::make('status')
                            ->content(fn (?Transaction $record): string => $record?->status ? ucfirst(str_replace('_', ' ', $record->status)) : '—'),
                        Placeholder::make('amount')
                            ->label('Original Amount')
                            ->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->amount / 100, 2) : '—'),
                        Placeholder::make('refunded_amount')
                            ->label('Refunded Amount')
                            ->content(fn (?Transaction $record): string => $record ? '$'.number_format($record->refunded_amount / 100, 2) : '—'),
                        Placeholder::make('currency')
                            ->content(fn (?Transaction $record): string => $record?->currency ? strtoupper($record->currency) : '—'),
                        Placeholder::make('created_at')
                            ->label('Refunded At')
                            ->content(fn (?Transaction $record): string => $record?->created_at?->format('M d, Y H:i') ?? '—'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(function (Builder $query): void {
            $query->where('refunded_amount', '>', 0)
                ->orWhereIn('status', ['refunded', 'partially_refunded']);
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stripe_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->limit(20),
                TextColumn::make('billable.name')
                    ->label('Customer')
                    ->description(fn (Transaction $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('amount')
                    ->label('Original')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
                TextColumn::make('refunded_amount')
                    ->label('Refunded')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'refunded' => 'success',
                        'partially_refunded' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Refunded At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                    ]),
            ])
            ->recordActions([
                Action::make('open_in_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Transaction $record): string => 'https://dashboard.stripe.com/payments/'.$record->stripe_id)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
        ];
    }
}
