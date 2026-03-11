<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Wave\Invoice;
use Wave\Plan;
use Wave\Subscription;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $months = collect();
        $labels = collect();

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months->push($date);
            $labels->push($date->format('M Y'));
        }

        $revenueData = $this->getRevenueData($months);

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getRevenueData(Collection $months): array
    {
        if (Schema::hasTable('invoices')) {
            return $this->getInvoiceBasedRevenue($months);
        }

        return $this->getSubscriptionBasedRevenue($months);
    }

    /**
     * Revenue from paid invoices grouped by month.
     */
    protected function getInvoiceBasedRevenue(Collection $months): array
    {
        $invoiceRevenue = Invoice::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [
                now()->subMonths(12)->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->get(['paid_at', 'total'])
            ->groupBy(fn (Invoice $invoice): string => $invoice->paid_at->format('Y-m'))
            ->map(fn (Collection $invoices): float => round($invoices->sum('total') / 100, 2));

        return $months->map(function (Carbon $date) use ($invoiceRevenue): float {
            return (float) ($invoiceRevenue->get($date->format('Y-m')) ?? 0);
        })->toArray();
    }

    /**
     * Estimate revenue based on active subscriptions per month.
     */
    protected function getSubscriptionBasedRevenue($months): array
    {
        $plans = Plan::all()->keyBy('id');

        return $months->map(function (Carbon $date) use ($plans): float {
            $endOfMonth = $date->copy()->endOfMonth();

            $subscriptions = Subscription::query()
                ->where('created_at', '<=', $endOfMonth)
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->where(function ($q) use ($endOfMonth): void {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $endOfMonth);
                })
                ->get();

            return $subscriptions->sum(function (Subscription $subscription) use ($plans): float {
                $plan = $plans->get($subscription->plan_id);
                if (! $plan) {
                    return 0;
                }

                $quantity = max(1, (int) $subscription->quantity);

                if ($subscription->cycle === 'year') {
                    return ((float) $plan->yearly_price / 12) * $quantity;
                }

                return (float) $plan->monthly_price * $quantity;
            });
        })->toArray();
    }

    protected function getType(): string
    {
        return 'line';
    }
}
