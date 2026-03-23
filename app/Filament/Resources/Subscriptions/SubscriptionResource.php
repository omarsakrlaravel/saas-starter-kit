<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Subscriptions\RelationManagers\TransactionsRelationManager;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanChangeResolver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = 'phosphor-credit-card-duotone';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $modelLabel = 'Subscription';

    protected static ?string $pluralModelLabel = 'Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Subscription')
                    ->schema([
                        Select::make('billable_type')
                            ->label('Billable Type')
                            ->options([
                                'user' => 'User',
                                'organization' => 'Organization',
                            ])
                            ->reactive()
                            ->required(),
                        Select::make('billable_id')
                            ->label('Billable')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search, callable $get) {
                                $type = $get('billable_type');
                                if ($type === 'organization') {
                                    return Organization::where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id');
                                }

                                return User::where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->limit(50)->pluck('name', 'id');
                            })
                            ->getOptionLabelUsing(function ($value, callable $get) {
                                $type = $get('billable_type');
                                if ($type === 'organization') {
                                    return Organization::find($value)?->name;
                                }

                                return User::find($value)?->name;
                            })
                            ->required(),
                        TextInput::make('user_id')
                            ->numeric(),
                        TextInput::make('type')
                            ->default('default')
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Select::make('cycle')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                                'onetime' => 'One time',
                            ])
                            ->required()
                            ->default('month'),
                        TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->default(1),
                        TextInput::make('stripe_id'),
                        Select::make('stripe_status')
                            ->options([
                                'active' => 'Active',
                                'trialing' => 'Trialing',
                                'past_due' => 'Past Due',
                                'canceled' => 'Canceled',
                                'incomplete' => 'Incomplete',
                            ])
                            ->required()
                            ->default('active'),
                        TextInput::make('stripe_price'),
                        DateTimePicker::make('trial_ends_at'),
                        DateTimePicker::make('ends_at'),
                        DateTimePicker::make('last_payment_at'),
                        DateTimePicker::make('next_payment_at'),
                    ])
                    ->columns(2),
                Section::make('Pending Plan Change')
                    ->schema([
                        Select::make('pending_plan_id')
                            ->label('Pending Plan')
                            ->relationship('pendingPlan', 'name')
                            ->preload()
                            ->searchable()
                            ->placeholder('None'),
                        Select::make('pending_cycle')
                            ->label('Pending Cycle')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                            ])
                            ->placeholder('None'),
                        DateTimePicker::make('pending_change_scheduled_at')
                            ->label('Scheduled At'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Subscriber')
                    ->description(fn (Subscription $record): string => ucfirst($record->billable_type))
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHasMorph('billable', '*', function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('plan.name')
                    ->sortable(),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trialing' => 'warning',
                        'past_due' => 'danger',
                        'canceled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('cycle')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'month' => 'Monthly',
                        'year' => 'Yearly',
                        'onetime' => 'One-time',
                        default => $state,
                    }),
                TextColumn::make('next_payment_at')
                    ->label('Next Payment')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Seats')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pendingPlan.name')
                    ->label('Pending Change')
                    ->placeholder('—')
                    ->badge()
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stripe_id')
                    ->label('Stripe Subscription')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stripe_status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'trialing' => 'Trialing',
                        'past_due' => 'Past Due',
                        'canceled' => 'Canceled',
                        'incomplete' => 'Incomplete',
                    ]),
                SelectFilter::make('cycle')
                    ->options([
                        'month' => 'Monthly',
                        'year' => 'Yearly',
                        'onetime' => 'One-time',
                    ]),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name'),
                SelectFilter::make('billable_type')
                    ->options([
                        'user' => 'User',
                        'organization' => 'Organization',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('cancel')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Subscription $record): bool => in_array($record->stripe_status, ['active', 'trialing']))
                    ->action(function (Subscription $record): void {
                        if ($record->stripe_id) {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripe->subscriptions->cancel($record->stripe_id);
                            } catch (ApiErrorException $e) {
                                Notification::make()
                                    ->title('Stripe error: '.$e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        $record->update([
                            'stripe_status' => 'canceled',
                            'ends_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Subscription canceled successfully.')
                            ->success()
                            ->send();
                    }),
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id) && in_array($record->stripe_status, ['active', 'trialing']))
                    ->form([
                        Select::make('plan_id')
                            ->label('New Plan')
                            ->options(fn (): array => Plan::where('active', 1)->pluck('name', 'id')->toArray())
                            ->required(),
                        Select::make('cycle')
                            ->label('Billing Cycle')
                            ->options([
                                'month' => 'Monthly',
                                'year' => 'Yearly',
                            ])
                            ->required()
                            ->default('month'),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        $plan = Plan::find($data['plan_id']);

                        if (! $plan) {
                            Notification::make()
                                ->title('Selected plan not found.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $priceId = $data['cycle'] === 'year' ? $plan->yearly_price_id : $plan->monthly_price_id;

                        if (empty($priceId)) {
                            Notification::make()
                                ->title('The selected plan does not have a price configured for this billing cycle.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $changeType = app(PlanChangeResolver::class)->resolve($record, $plan, $data['cycle']);

                        if ($changeType === 'same') {
                            Notification::make()
                                ->title('Subscription is already on this plan and cycle.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($changeType === 'upgrade') {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripeSubscription = $stripe->subscriptions->retrieve($record->stripe_id);
                                $stripe->subscriptions->update($record->stripe_id, [
                                    'items' => [
                                        [
                                            'id' => $stripeSubscription->items->data[0]->id,
                                            'price' => $priceId,
                                        ],
                                    ],
                                    'proration_behavior' => 'create_prorations',
                                ]);
                            } catch (ApiErrorException $e) {
                                Notification::make()
                                    ->title('Stripe error: '.$e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->update([
                                'plan_id' => $plan->id,
                                'stripe_price' => $priceId,
                                'cycle' => $data['cycle'],
                                'pending_plan_id' => null,
                                'pending_cycle' => null,
                                'pending_change_scheduled_at' => null,
                            ]);

                            Notification::make()
                                ->title('Upgraded to '.$plan->name.' ('.$data['cycle'].'ly) immediately.')
                                ->success()
                                ->send();
                        } else {
                            $scheduledAt = $record->next_payment_at ?? $record->ends_at ?? now()->addMonth();

                            $record->update([
                                'pending_plan_id' => $plan->id,
                                'pending_cycle' => $data['cycle'],
                                'pending_change_scheduled_at' => $scheduledAt,
                            ]);

                            Notification::make()
                                ->title('Downgrade to '.$plan->name.' scheduled for '.($scheduledAt instanceof \Carbon\Carbon ? $scheduledAt->format('M j, Y') : $scheduledAt).'.')
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('cancel_pending_change')
                    ->label('Cancel Pending Change')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => $record->hasPendingChange())
                    ->action(function (Subscription $record): void {
                        $record->cancelPendingChange();

                        Notification::make()
                            ->title('Pending plan change cancelled.')
                            ->success()
                            ->send();
                    }),
                Action::make('apply_coupon')
                    ->label('Apply Coupon')
                    ->icon('heroicon-o-ticket')
                    ->color('info')
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id) && in_array($record->stripe_status, ['active', 'trialing']))
                    ->form([
                        TextInput::make('coupon_code')
                            ->label('Coupon or Promotion Code')
                            ->required()
                            ->placeholder('e.g. SAVE20'),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));

                            $couponId = $data['coupon_code'];

                            $promotionCodes = $stripe->promotionCodes->all([
                                'code' => $data['coupon_code'],
                                'active' => true,
                                'limit' => 1,
                            ]);

                            if (! empty($promotionCodes->data)) {
                                $couponId = $promotionCodes->data[0]->coupon->id;
                            }

                            $stripe->subscriptions->update($record->stripe_id, [
                                'coupon' => $couponId,
                            ]);
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Coupon "'.$data['coupon_code'].'" applied successfully.')
                            ->success()
                            ->send();
                    }),
                Action::make('adjust_seats')
                    ->label('Adjust Seats')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id) && in_array($record->stripe_status, ['active', 'trialing']))
                    ->form([
                        TextInput::make('quantity')
                            ->label('New Seat Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(fn (Subscription $record): int => $record->quantity ?? 1),
                    ])
                    ->action(function (Subscription $record, array $data): void {
                        $newQuantity = (int) $data['quantity'];

                        if ($newQuantity < 1) {
                            Notification::make()
                                ->title('Quantity must be at least 1.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripeSubscription = $stripe->subscriptions->retrieve($record->stripe_id);
                            $stripe->subscriptions->update($record->stripe_id, [
                                'items' => [
                                    [
                                        'id' => $stripeSubscription->items->data[0]->id,
                                        'quantity' => $newQuantity,
                                    ],
                                ],
                                'proration_behavior' => $newQuantity > $record->quantity
                                    ? 'create_prorations'
                                    : 'none',
                            ]);
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['quantity' => $newQuantity]);

                        Notification::make()
                            ->title('Seats updated to '.$newQuantity.'.')
                            ->success()
                            ->send();
                    }),
                Action::make('refund_last_payment')
                    ->label('Refund Last Payment')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will issue a full refund for the latest paid invoice on this subscription via Stripe. This action cannot be undone.')
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id) && in_array($record->stripe_status, ['active', 'trialing', 'past_due']))
                    ->action(function (Subscription $record): void {
                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));

                            $invoices = $stripe->invoices->all([
                                'subscription' => $record->stripe_id,
                                'status' => 'paid',
                                'limit' => 1,
                            ]);

                            if (empty($invoices->data)) {
                                Notification::make()
                                    ->title('No paid invoices found for this subscription.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $latestInvoice = $invoices->data[0];

                            if (empty($latestInvoice->payment_intent)) {
                                Notification::make()
                                    ->title('No payment intent found on the latest invoice.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $stripe->refunds->create([
                                'payment_intent' => $latestInvoice->payment_intent,
                            ]);
                        } catch (ApiErrorException $e) {
                            Notification::make()
                                ->title('Stripe error: '.$e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $currency = strtolower((string) ($latestInvoice->currency ?? 'usd'));
                        $amountFormatted = number_format($latestInvoice->amount_paid / 100, 2);

                        Notification::make()
                            ->title('Refund of '.currencySymbol($currency).$amountFormatted.' issued for invoice '.$latestInvoice->number.'.')
                            ->success()
                            ->send();
                    }),
                Action::make('open_in_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Subscription $record): string => 'https://dashboard.stripe.com/subscriptions/'.$record->stripe_id)
                    ->openUrlInNewTab()
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id)),
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
        return [
            InvoicesRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'edit' => EditSubscription::route('/{record}/edit'),
        ];
    }
}
