<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Wave\Subscription;

class PlanDistributionWidget extends ChartWidget
{
    protected ?string $heading = 'Plan Distribution';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $distribution = Subscription::query()
            ->where('stripe_status', 'active')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->select('plans.name', DB::raw('COUNT(*) as count'))
            ->groupBy('plans.name')
            ->orderByDesc('count')
            ->get();

        $colors = [
            'rgb(59, 130, 246)',   // blue
            'rgb(16, 185, 129)',   // emerald
            'rgb(245, 158, 11)',   // amber
            'rgb(239, 68, 68)',    // red
            'rgb(139, 92, 246)',   // violet
            'rgb(236, 72, 153)',   // pink
            'rgb(20, 184, 166)',   // teal
            'rgb(249, 115, 22)',   // orange
        ];

        $backgroundColors = $distribution->values()->map(
            fn ($item, $index) => $colors[$index % count($colors)]
        )->toArray();

        return [
            'datasets' => [
                [
                    'data' => $distribution->pluck('count')->toArray(),
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $distribution->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
