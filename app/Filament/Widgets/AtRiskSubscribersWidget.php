<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AtRiskSubscribersWidget extends TableWidget
{
    protected static ?string $heading = 'At-Risk Subscribers';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Subscription::query()
                    ->where(function (Builder $q): void {
                        $q->where('stripe_status', 'past_due')
                            ->orWhere(function (Builder $q2): void {
                                $q2->where('stripe_status', 'trialing')
                                    ->where('trial_ends_at', '<=', now()->addDays(7))
                                    ->where('trial_ends_at', '>', now());
                            });
                    })
                    ->with(['billable', 'plan'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('billable.name')
                    ->label('Subscriber')
                    ->placeholder('--'),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('--'),
                TextColumn::make('stripe_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'past_due' => 'danger',
                        'trialing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime('M j, Y')
                    ->placeholder('--'),
                TextColumn::make('next_payment_at')
                    ->label('Next Payment')
                    ->dateTime('M j, Y')
                    ->placeholder('--'),
            ])
            ->paginated(false)
            ->defaultSort('created_at', 'desc');
    }
}
