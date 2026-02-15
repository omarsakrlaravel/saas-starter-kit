<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AtRiskSubscribersWidget;
use App\Filament\Widgets\PlanDistributionWidget;
use App\Filament\Widgets\RecentTransactionsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use BackedEnum;
use Filament\Panel;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static BackedEnum|string|null $navigationIcon = 'phosphor-house-duotone';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->pages([]);
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            RevenueChartWidget::class,
            PlanDistributionWidget::class,
            RecentTransactionsWidget::class,
            AtRiskSubscribersWidget::class,
        ];
    }
}
