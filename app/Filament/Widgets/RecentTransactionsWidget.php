<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Schema;

class RecentTransactionsWidget extends TableWidget
{
    protected static ?string $heading = 'Recent Transactions';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Schema::hasTable('transactions');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Customer')
                    ->placeholder('--'),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2))
                    ->label('Amount'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'succeeded' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i'),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
