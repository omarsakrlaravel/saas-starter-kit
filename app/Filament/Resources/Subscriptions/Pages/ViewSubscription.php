<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PlanChangeResolver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('change_plan')
                    ->label('Change Plan')
                    ->icon('heroicon-o-arrow-path')
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
                            Notification::make()->title('Selected plan not found.')->danger()->send();

                            return;
                        }

                        $priceId = $data['cycle'] === 'year' ? $plan->yearly_price_id : $plan->monthly_price_id;

                        if (empty($priceId)) {
                            Notification::make()->title('No price configured for this billing cycle.')->danger()->send();

                            return;
                        }

                        $changeType = app(PlanChangeResolver::class)->resolve($record, $plan, $data['cycle']);

                        if ($changeType === 'same') {
                            Notification::make()->title('Already on this plan and cycle.')->warning()->send();

                            return;
                        }

                        if ($changeType === 'upgrade') {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripeSubscription = $stripe->subscriptions->retrieve($record->stripe_id);
                                $stripe->subscriptions->update($record->stripe_id, [
                                    'items' => [['id' => $stripeSubscription->items->data[0]->id, 'price' => $priceId]],
                                    'proration_behavior' => 'create_prorations',
                                ]);
                            } catch (ApiErrorException $e) {
                                Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                                return;
                            }

                            $record->update([
                                'plan_id' => $plan->id, 'stripe_price' => $priceId, 'cycle' => $data['cycle'],
                                'pending_plan_id' => null, 'pending_cycle' => null, 'pending_change_scheduled_at' => null,
                            ]);

                            Notification::make()->title('Upgraded to '.$plan->name.' ('.$data['cycle'].'ly).')->success()->send();
                        } else {
                            $scheduledAt = $record->next_payment_at ?? $record->ends_at ?? now()->addMonth();
                            $record->update([
                                'pending_plan_id' => $plan->id, 'pending_cycle' => $data['cycle'],
                                'pending_change_scheduled_at' => $scheduledAt,
                            ]);

                            Notification::make()->title('Downgrade scheduled for '.($scheduledAt instanceof \Carbon\Carbon ? $scheduledAt->format('M j, Y') : $scheduledAt).'.')->success()->send();
                        }
                    }),

                Action::make('apply_coupon')
                    ->label('Apply Coupon')
                    ->icon('heroicon-o-ticket')
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

                            $promotionCodes = $stripe->promotionCodes->all(['code' => $data['coupon_code'], 'active' => true, 'limit' => 1]);
                            if (! empty($promotionCodes->data)) {
                                $couponId = $promotionCodes->data[0]->coupon->id;
                            }

                            $stripe->subscriptions->update($record->stripe_id, ['coupon' => $couponId]);
                        } catch (ApiErrorException $e) {
                            Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Coupon "'.$data['coupon_code'].'" applied.')->success()->send();
                    }),

                Action::make('adjust_seats')
                    ->label('Adjust Seats')
                    ->icon('heroicon-o-user-group')
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

                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $stripeSubscription = $stripe->subscriptions->retrieve($record->stripe_id);
                            $stripe->subscriptions->update($record->stripe_id, [
                                'items' => [['id' => $stripeSubscription->items->data[0]->id, 'quantity' => $newQuantity]],
                                'proration_behavior' => $newQuantity > $record->quantity ? 'create_prorations' : 'none',
                            ]);
                        } catch (ApiErrorException $e) {
                            Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                            return;
                        }

                        $record->update(['quantity' => $newQuantity]);
                        Notification::make()->title('Seats updated to '.$newQuantity.'.')->success()->send();
                    }),

                Action::make('cancel_pending_change')
                    ->label('Cancel Pending Change')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => $record->hasPendingChange())
                    ->action(function (Subscription $record): void {
                        $record->cancelPendingChange();
                        Notification::make()->title('Pending plan change cancelled.')->success()->send();
                    }),
            ])
                ->label('Manage')
                ->icon('heroicon-o-cog-6-tooth')
                ->button()
                ->color('gray'),

            ActionGroup::make([
                Action::make('refund_last_payment')
                    ->label('Refund Last Payment')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Full refund for the latest paid invoice. This cannot be undone.')
                    ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id) && in_array($record->stripe_status, ['active', 'trialing', 'past_due']))
                    ->action(function (Subscription $record): void {
                        try {
                            $stripe = new StripeClient(config('services.stripe.secret'));
                            $invoices = $stripe->invoices->all(['subscription' => $record->stripe_id, 'status' => 'paid', 'limit' => 1]);

                            if (empty($invoices->data)) {
                                Notification::make()->title('No paid invoices found.')->warning()->send();

                                return;
                            }

                            $latestInvoice = $invoices->data[0];
                            if (empty($latestInvoice->payment_intent)) {
                                Notification::make()->title('No payment intent found on invoice.')->warning()->send();

                                return;
                            }

                            $stripe->refunds->create(['payment_intent' => $latestInvoice->payment_intent]);
                        } catch (ApiErrorException $e) {
                            Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                            return;
                        }

                        $currency = strtolower((string) ($latestInvoice->currency ?? 'usd'));
                        $amount = number_format($latestInvoice->amount_paid / 100, 2);
                        Notification::make()->title('Refund of '.currencySymbol($currency).$amount.' issued.')->success()->send();
                    }),

                Action::make('cancel_subscription')
                    ->label('Cancel Subscription')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => in_array($record->stripe_status, ['active', 'trialing']))
                    ->action(function (Subscription $record): void {
                        if ($record->stripe_id) {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripe->subscriptions->cancel($record->stripe_id);
                            } catch (ApiErrorException $e) {
                                Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                                return;
                            }
                        }

                        $record->update(['stripe_status' => 'canceled', 'ends_at' => now()]);
                        Notification::make()->title('Subscription canceled.')->success()->send();
                    }),

                Action::make('delete_subscription')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will cancel the subscription in Stripe (if active) and permanently delete the local record. This cannot be undone.')
                    ->action(function (Subscription $record): void {
                        if ($record->stripe_id && in_array($record->stripe_status, ['active', 'trialing', 'past_due'])) {
                            try {
                                $stripe = new StripeClient(config('services.stripe.secret'));
                                $stripe->subscriptions->cancel($record->stripe_id);
                            } catch (ApiErrorException $e) {
                                Notification::make()->title('Stripe error: '.$e->getMessage())->danger()->send();

                                return;
                            }
                        }

                        $record->delete();

                        Notification::make()->title('Subscription deleted.')->success()->send();

                        $this->redirect(SubscriptionResource::getUrl('index'));
                    }),
            ])
                ->label('Danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->button()
                ->color('danger'),

            Action::make('open_in_stripe')
                ->label('Stripe')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Subscription $record): string => 'https://dashboard.stripe.com/subscriptions/'.$record->stripe_id)
                ->openUrlInNewTab()
                ->color('gray')
                ->button()
                ->visible(fn (Subscription $record): bool => ! empty($record->stripe_id)),
        ];
    }
}
