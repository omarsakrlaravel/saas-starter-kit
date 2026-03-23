<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Actions\Billing\AdjustSubscriptionSeats;
use App\Actions\Billing\ApplyCouponToSubscription;
use App\Actions\Billing\CancelSubscription;
use App\Actions\Billing\ChangeSubscriptionPlan;
use App\Actions\Billing\DeleteSubscription;
use App\Actions\Billing\RefundLastPayment;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
                            ->options(['month' => 'Monthly', 'year' => 'Yearly'])
                            ->required()
                            ->default('month'),
                    ])
                    ->action(fn (Subscription $record, array $data) => $this->notify(
                        app(ChangeSubscriptionPlan::class)->execute($record, (int) $data['plan_id'], $data['cycle'])
                    )),

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
                    ->action(fn (Subscription $record, array $data) => $this->notify(
                        app(ApplyCouponToSubscription::class)->execute($record, $data['coupon_code'])
                    )),

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
                    ->action(fn (Subscription $record, array $data) => $this->notify(
                        app(AdjustSubscriptionSeats::class)->execute($record, (int) $data['quantity'])
                    )),

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
                    ->action(fn (Subscription $record) => $this->notify(
                        app(RefundLastPayment::class)->execute($record)
                    )),

                Action::make('cancel_subscription')
                    ->label('Cancel Subscription')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record): bool => in_array($record->stripe_status, ['active', 'trialing']))
                    ->action(fn (Subscription $record) => $this->notify(
                        app(CancelSubscription::class)->execute($record)
                    )),

                Action::make('delete_subscription')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will cancel in Stripe (if active) and permanently delete the local record.')
                    ->action(function (Subscription $record): void {
                        $result = app(DeleteSubscription::class)->execute($record);
                        $this->notify($result);

                        if ($result->success) {
                            $this->redirect(SubscriptionResource::getUrl('index'));
                        }
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

    private function notify(\App\Actions\Billing\ActionResult $result): void
    {
        Notification::make()
            ->title($result->message)
            ->{$result->success ? 'success' : 'danger'}()
            ->send();
    }
}
