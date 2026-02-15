<?php

namespace App\Filament\Resources\Invoices;

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use UnitEnum;
use Wave\Invoice;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-receipt-duotone';

    protected static ?int $navigationSort = 6;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        Placeholder::make('number')
                            ->content(fn (?Invoice $record): string => $record?->number ?? '—'),
                        Placeholder::make('status')
                            ->content(fn (?Invoice $record): string => $record?->status ? ucfirst($record->status) : '—'),
                        Placeholder::make('currency')
                            ->content(fn (?Invoice $record): string => $record?->currency ? strtoupper($record->currency) : '—'),
                        Placeholder::make('amount_due')
                            ->label('Amount Due')
                            ->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->amount_due / 100, 2) : '—'),
                        Placeholder::make('amount_paid')
                            ->label('Amount Paid')
                            ->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->amount_paid / 100, 2) : '—'),
                        Placeholder::make('total')
                            ->content(fn (?Invoice $record): string => $record ? '$'.number_format($record->total / 100, 2) : '—'),
                        Placeholder::make('period_start')
                            ->label('Period Start')
                            ->content(fn (?Invoice $record): string => $record?->period_start?->format('M d, Y H:i') ?? '—'),
                        Placeholder::make('period_end')
                            ->label('Period End')
                            ->content(fn (?Invoice $record): string => $record?->period_end?->format('M d, Y H:i') ?? '—'),
                        Placeholder::make('due_date')
                            ->label('Due Date')
                            ->content(fn (?Invoice $record): string => $record?->due_date?->format('M d, Y H:i') ?? '—'),
                        Placeholder::make('paid_at')
                            ->label('Paid At')
                            ->content(fn (?Invoice $record): string => $record?->paid_at?->format('M d, Y H:i') ?? '—'),
                    ])
                    ->columns(2),
                Section::make('Links')
                    ->schema([
                        Placeholder::make('hosted_invoice_url')
                            ->label('Hosted Invoice URL')
                            ->content(fn (?Invoice $record): string => $record?->hosted_invoice_url
                                ? '<a href="'.e($record->hosted_invoice_url).'" target="_blank" class="text-primary-600 underline">View Invoice</a>'
                                : '—')
                            ->extraAttributes(['class' => '[&_a]:text-primary-600 [&_a]:underline']),
                        Placeholder::make('invoice_pdf')
                            ->label('Invoice PDF')
                            ->content(fn (?Invoice $record): string => $record?->invoice_pdf
                                ? '<a href="'.e($record->invoice_pdf).'" target="_blank" class="text-primary-600 underline">Download PDF</a>'
                                : '—')
                            ->extraAttributes(['class' => '[&_a]:text-primary-600 [&_a]:underline']),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('billable.name')
                    ->label('Customer')
                    ->description(fn (Invoice $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('total')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state): string => '$'.number_format($state / 100, 2)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'open' => 'warning',
                        'void' => 'gray',
                        'draft' => 'gray',
                        'uncollectible' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('currency')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'paid' => 'Paid',
                        'void' => 'Void',
                        'uncollectible' => 'Uncollectible',
                    ]),
                SelectFilter::make('currency'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('warning')
                    ->visible(fn (Invoice $record): bool => $record->status === 'paid')
                    ->fillForm(fn (Invoice $record): array => [
                        'amount' => number_format($record->total / 100, 2, '.', ''),
                    ])
                    ->schema([
                        TextInput::make('amount')
                            ->label('Refund Amount ($)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $amountInCents = (int) round($data['amount'] * 100);

                        if ($amountInCents > $record->amount_paid) {
                            Notification::make()
                                ->title('Refund amount exceeds amount paid')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripeInvoice = $stripe->invoices->retrieve($record->stripe_id);
                            $stripe->refunds->create([
                                'charge' => $stripeInvoice->charge,
                                'amount' => $amountInCents,
                            ]);

                            $newAmountPaid = $record->amount_paid - $amountInCents;
                            $record->update([
                                'amount_paid' => $newAmountPaid,
                                'amount_remaining' => $record->total - $newAmountPaid,
                            ]);

                            Notification::make()
                                ->title('Refund processed successfully')
                                ->success()
                                ->send();
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('void')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => $record->status === 'open')
                    ->action(function (Invoice $record): void {
                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripe->invoices->voidInvoice($record->stripe_id);

                            $record->update(['status' => 'void']);

                            Notification::make()
                                ->title('Invoice voided successfully')
                                ->success()
                                ->send();
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('mark_uncollectible')
                    ->label('Mark Uncollectible')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => $record->status === 'open')
                    ->action(function (Invoice $record): void {
                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripe->invoices->markUncollectible($record->stripe_id);

                            $record->update(['status' => 'uncollectible']);

                            Notification::make()
                                ->title('Invoice marked as uncollectible')
                                ->success()
                                ->send();
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Invoice $record): ?string => $record->invoice_pdf)
                    ->openUrlInNewTab()
                    ->visible(fn (Invoice $record): bool => ! empty($record->invoice_pdf)),
                Action::make('open_in_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Invoice $record): string => 'https://dashboard.stripe.com/invoices/'.$record->stripe_id)
                    ->openUrlInNewTab(),
                DeleteAction::make(),
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
