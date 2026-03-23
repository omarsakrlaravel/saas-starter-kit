<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-arrows-left-right-duotone';

    protected static ?int $navigationSort = 7;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Placeholder::make('stripe_id')
                            ->label('Transaction ID')
                            ->content(fn (?Transaction $record): string => $record?->stripe_id ?? '—'),
                        Placeholder::make('status')
                            ->content(fn (?Transaction $record): string => $record?->status ? ucfirst($record->status) : '—'),
                        Placeholder::make('amount')
                            ->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->amount / 100, 2) : '—'),
                        Placeholder::make('currency')
                            ->content(fn (?Transaction $record): string => $record?->currency ? strtoupper($record->currency) : '—'),
                        Placeholder::make('refunded_amount')
                            ->label('Refunded Amount')
                            ->content(fn (?Transaction $record): string => $record ? currencySymbol($record->currency).number_format($record->refunded_amount / 100, 2) : '—'),
                        Placeholder::make('description')
                            ->content(fn (?Transaction $record): string => $record?->description ?? '—'),
                    ])
                    ->columns(2),
                Section::make('Payment Method')
                    ->schema([
                        Placeholder::make('payment_method_type')
                            ->label('Type')
                            ->content(fn (?Transaction $record): string => $record?->payment_method_type ?? '—'),
                        Placeholder::make('payment_method_brand')
                            ->label('Brand')
                            ->content(fn (?Transaction $record): string => $record?->payment_method_brand ? ucfirst($record->payment_method_brand) : '—'),
                        Placeholder::make('payment_method_last4')
                            ->label('Last 4')
                            ->content(fn (?Transaction $record): string => $record?->payment_method_last4 ? '****'.$record->payment_method_last4 : '—'),
                    ])
                    ->columns(2),
                Section::make('Failure Details')
                    ->schema([
                        Placeholder::make('failure_code')
                            ->label('Failure Code')
                            ->content(fn (?Transaction $record): string => $record?->failure_code ?? '—'),
                        Placeholder::make('failure_message')
                            ->label('Failure Message')
                            ->content(fn (?Transaction $record): string => $record?->failure_message ?? '—'),
                    ])
                    ->columns(2)
                    ->visible(fn (?Transaction $record): bool => ! empty($record?->failure_code)),
            ]);
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
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('amount')
                    ->formatStateUsing(fn (int $state, Transaction $record): string => currencySymbol($record->currency).number_format($state / 100, 2)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'succeeded' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        'partially_refunded' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method_brand')
                    ->label('Payment Method')
                    ->formatStateUsing(fn (Transaction $record): string => $record->payment_method_brand
                        ? ucfirst($record->payment_method_brand).' ****'.$record->payment_method_last4
                        : '—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                    ]),
                SelectFilter::make('payment_method_brand')
                    ->label('Payment Brand'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refund_full')
                    ->label('Refund Full')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Refund Full Amount')
                    ->modalDescription(fn (Transaction $record): string => 'Are you sure you want to refund '.currencySymbol($record->currency).number_format($record->amount / 100, 2).'? This cannot be undone.')
                    ->visible(fn (Transaction $record): bool => $record->status === 'succeeded' && $record->refunded_amount < $record->amount)
                    ->action(function (Transaction $record): void {
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
                        $refundParams = str_starts_with($record->stripe_id, 'pi_')
                            ? ['payment_intent' => $record->stripe_id]
                            : ['charge' => $record->stripe_id];

                        try {
                            $stripe->refunds->create($refundParams);

                            $record->update([
                                'status' => 'refunded',
                                'refunded_amount' => $record->amount,
                            ]);

                            Notification::make()
                                ->title('Refund successful')
                                ->body(currencySymbol($record->currency).number_format($record->amount / 100, 2).' has been refunded.')
                                ->success()
                                ->send();
                        } catch (\Stripe\Exception\ApiErrorException $e) {
                            Notification::make()
                                ->title('Refund failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('refund_partial')
                    ->label('Partial Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Transaction $record): bool => $record->status === 'succeeded' && $record->refunded_amount < $record->amount)
                    ->modalHeading('Partial Refund')
                    ->modalDescription(fn (Transaction $record): string => 'Original amount: '.currencySymbol($record->currency).number_format($record->amount / 100, 2).'. Already refunded: '.currencySymbol($record->currency).number_format($record->refunded_amount / 100, 2).'.')
                    ->schema(fn (Transaction $record): array => [
                        TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(($record->amount - $record->refunded_amount) / 100)
                            ->step(0.01)
                            ->prefix(currencySymbol($record->currency))
                            ->helperText('Maximum refundable: '.currencySymbol($record->currency).number_format(($record->amount - $record->refunded_amount) / 100, 2)),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        $refundAmountCents = (int) round($data['refund_amount'] * 100);
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
                        $refundParams = str_starts_with($record->stripe_id, 'pi_')
                            ? ['payment_intent' => $record->stripe_id, 'amount' => $refundAmountCents]
                            : ['charge' => $record->stripe_id, 'amount' => $refundAmountCents];

                        try {
                            $stripe->refunds->create($refundParams);

                            $newRefundedAmount = $record->refunded_amount + $refundAmountCents;
                            $newStatus = $newRefundedAmount >= $record->amount ? 'refunded' : 'partially_refunded';

                            $record->update([
                                'status' => $newStatus,
                                'refunded_amount' => $newRefundedAmount,
                            ]);

                            Notification::make()
                                ->title('Partial refund successful')
                                ->body(currencySymbol($record->currency).number_format($refundAmountCents / 100, 2).' has been refunded.')
                                ->success()
                                ->send();
                        } catch (\Stripe\Exception\ApiErrorException $e) {
                            Notification::make()
                                ->title('Refund failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('open_in_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Transaction $record): string => 'https://dashboard.stripe.com/payments/'.$record->stripe_id)
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }
}
