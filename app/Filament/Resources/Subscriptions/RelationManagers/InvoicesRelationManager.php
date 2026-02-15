<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Wave\Invoice;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'localInvoices';

    protected static ?string $title = 'Invoices';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('total')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'open' => 'warning',
                        'void' => 'gray',
                        'uncollectible' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'paid' => 'Paid',
                        'void' => 'Void',
                        'uncollectible' => 'Uncollectible',
                    ]),
            ])
            ->recordActions([
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Invoice $record): ?string => $record->invoice_pdf)
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record): bool => ! empty($record->invoice_pdf)),
            ])
            ->toolbarActions([]);
    }
}
