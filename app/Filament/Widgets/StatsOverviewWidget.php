<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Wave\Subscription;
use Wave\User;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            $this->getMrrStat(),
            $this->getActiveSubscribersStat(),
            $this->getNewSignupsStat(),
            $this->getChurnRateStat(),
        ];
    }

    protected function getMrrStat(): Stat
    {
        $activeSubscriptions = Subscription::query()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->with('plan')
            ->get();

        $mrr = $activeSubscriptions->sum(function (Subscription $subscription): float {
            $plan = $subscription->plan;
            if (! $plan) {
                return 0;
            }

            $quantity = max(1, (int) $subscription->quantity);

            if ($subscription->cycle === 'year') {
                return ((float) $plan->yearly_price / 12) * $quantity;
            }

            return (float) $plan->monthly_price * $quantity;
        });

        return Stat::make('MRR', '$'.number_format($mrr, 2))
            ->description('Monthly Recurring Revenue')
            ->descriptionIcon('heroicon-m-currency-dollar')
            ->color('success');
    }

    protected function getActiveSubscribersStat(): Stat
    {
        $activeCount = Subscription::query()
            ->where('stripe_status', 'active')
            ->count();

        $trialingCount = Subscription::query()
            ->where('stripe_status', 'trialing')
            ->count();

        $total = $activeCount + $trialingCount;

        return Stat::make('Active Subscribers', number_format($total))
            ->description("{$activeCount} active, {$trialingCount} trialing")
            ->descriptionIcon('heroicon-m-users')
            ->color('primary');
    }

    protected function getNewSignupsStat(): Stat
    {
        $currentPeriodCount = User::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $previousPeriodCount = User::query()
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        $dailyCounts = User::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count')
            ->toArray();

        $trend = $currentPeriodCount - $previousPeriodCount;
        $trendIcon = $trend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $trendColor = $trend >= 0 ? 'success' : 'danger';
        $trendLabel = abs($trend).' '.($trend >= 0 ? 'increase' : 'decrease').' vs prior 30 days';

        return Stat::make('New Signups', number_format($currentPeriodCount))
            ->description($trendLabel)
            ->descriptionIcon($trendIcon)
            ->chart($dailyCounts)
            ->color($trendColor);
    }

    protected function getChurnRateStat(): Stat
    {
        $activeAtStart = Subscription::query()
            ->where('created_at', '<', now()->subDays(30))
            ->whereIn('stripe_status', ['active', 'trialing', 'canceled', 'past_due'])
            ->count();

        $canceledInPeriod = Subscription::query()
            ->where('stripe_status', 'canceled')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $churnRate = $activeAtStart > 0
            ? round(($canceledInPeriod / $activeAtStart) * 100, 1)
            : 0;

        return Stat::make('Churn Rate', $churnRate.'%')
            ->description('Last 30 days')
            ->descriptionIcon('heroicon-m-arrow-path')
            ->color($churnRate > 5 ? 'danger' : ($churnRate > 2 ? 'warning' : 'success'));
    }
}
