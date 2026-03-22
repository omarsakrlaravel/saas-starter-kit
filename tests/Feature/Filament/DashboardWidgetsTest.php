<?php

/**
 * Dashboard Widgets Test Suite
 *
 * Tests the analytics dashboard widgets including:
 * - StatsOverviewWidget (MRR, Active Subscribers, New Signups, Churn Rate)
 * - RevenueChartWidget (line chart)
 * - PlanDistributionWidget (doughnut chart)
 * - AtRiskSubscribersWidget (table widget)
 * - DashboardWidget (welcome widget)
 * - Dashboard page widget column configuration
 */

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AtRiskSubscribersWidget;
use App\Filament\Widgets\DashboardWidget;
use App\Filament\Widgets\PlanDistributionWidget;
use App\Filament\Widgets\RecentTransactionsWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->artisan('migrate:fresh');
    $this->seed();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('dashboard page uses 2-column layout', function () {
    $dashboard = new Dashboard();
    expect($dashboard->getColumns())->toBe(2);
});

test('dashboard widget has sort 0', function () {
    $reflection = new ReflectionClass(DashboardWidget::class);
    $sortProperty = $reflection->getProperty('sort');
    expect($sortProperty->getDefaultValue())->toBe(0);
});

test('stats overview widget has sort 1', function () {
    $reflection = new ReflectionClass(StatsOverviewWidget::class);
    $sortProperty = $reflection->getProperty('sort');
    expect($sortProperty->getDefaultValue())->toBe(1);
});

test('stats overview widget calculates MRR from active subscriptions', function () {
    // Clear seeded subscriptions to test in isolation
    Subscription::query()->delete();

    $plan = Plan::find(1); // Basic plan: monthly_price=9, yearly_price=90

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Subscription::create([
        'user_id' => $user1->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_1',
        'stripe_status' => 'active',
        'stripe_price' => $plan->monthly_price_id,
        'quantity' => 1,
        'billable_type' => 'user',
        'billable_id' => $user1->id,
        'plan_id' => $plan->id,
        'cycle' => 'month',
    ]);

    Subscription::create([
        'user_id' => $user2->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_2',
        'stripe_status' => 'active',
        'stripe_price' => $plan->yearly_price_id,
        'quantity' => 2,
        'billable_type' => 'user',
        'billable_id' => $user2->id,
        'plan_id' => $plan->id,
        'cycle' => 'year',
    ]);

    $widget = new StatsOverviewWidget();
    $stats = invade($widget)->getStats();

    // MRR = $9 (monthly sub) + ($90/12 * 2 seats) = $9 + $15 = $24
    $mrrStat = $stats[0];
    expect($mrrStat->getValue())->toBe('$24.00');
});

test('stats overview widget counts active and trialing subscribers', function () {
    // Clear seeded subscriptions
    Subscription::query()->delete();

    $plan = Plan::find(1);

    $activeUser = User::factory()->create();
    $trialingUser = User::factory()->create();
    $canceledUser = User::factory()->create();

    Subscription::create([
        'user_id' => $activeUser->id,
        'type' => 'default',
        'stripe_id' => 'sub_active',
        'stripe_status' => 'active',
        'stripe_price' => $plan->monthly_price_id,
        'quantity' => 1,
        'billable_type' => 'user',
        'billable_id' => $activeUser->id,
        'plan_id' => $plan->id,
        'cycle' => 'month',
    ]);

    Subscription::create([
        'user_id' => $trialingUser->id,
        'type' => 'default',
        'stripe_id' => 'sub_trialing',
        'stripe_status' => 'trialing',
        'stripe_price' => $plan->monthly_price_id,
        'quantity' => 1,
        'billable_type' => 'user',
        'billable_id' => $trialingUser->id,
        'plan_id' => $plan->id,
        'cycle' => 'month',
        'trial_ends_at' => now()->addDays(14),
    ]);

    Subscription::create([
        'user_id' => $canceledUser->id,
        'type' => 'default',
        'stripe_id' => 'sub_canceled',
        'stripe_status' => 'canceled',
        'stripe_price' => $plan->monthly_price_id,
        'quantity' => 1,
        'billable_type' => 'user',
        'billable_id' => $canceledUser->id,
        'plan_id' => $plan->id,
        'cycle' => 'month',
    ]);

    $widget = new StatsOverviewWidget();
    $stats = invade($widget)->getStats();

    $subscribersStat = $stats[1];
    expect($subscribersStat->getValue())->toBe('2')
        ->and($subscribersStat->getDescription())->toBe('1 active, 1 trialing');
});

test('stats overview widget counts new signups in last 30 days', function () {
    $widget = new StatsOverviewWidget();
    $stats = invade($widget)->getStats();

    $signupsStat = $stats[2];
    // Seeder creates users + admin in beforeEach, all within last 30 days
    expect((int) str_replace(',', '', $signupsStat->getValue()))->toBeGreaterThanOrEqual(1);
});

test('stats overview widget calculates churn rate', function () {
    $widget = new StatsOverviewWidget();
    $stats = invade($widget)->getStats();

    $churnStat = $stats[3];
    // Churn rate should be a percentage string
    expect($churnStat->getValue())->toContain('%')
        ->and($churnStat->getDescription())->toBe('Last 30 days');
});

test('revenue chart widget returns line type', function () {
    $widget = new RevenueChartWidget();
    expect(invade($widget)->getType())->toBe('line');
});

test('revenue chart widget has correct column span', function () {
    $widget = new RevenueChartWidget();
    expect($widget->getColumnSpan())->toBe(1);
});

test('revenue chart widget returns 12 months of labels', function () {
    $widget = new RevenueChartWidget();
    $data = invade($widget)->getData();

    expect($data['labels'])->toHaveCount(12)
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['data'])->toHaveCount(12);
});

test('revenue chart widget aggregates paid invoices by month', function () {
    Carbon::setTestNow('2026-03-11 12:00:00');

    try {
        Invoice::query()->delete();

        Invoice::factory()->create([
            'billable_type' => 'user',
            'billable_id' => $this->admin->id,
            'status' => 'paid',
            'amount_due' => 1500,
            'amount_paid' => 1500,
            'amount_remaining' => 0,
            'subtotal' => 1500,
            'tax' => 0,
            'total' => 1500,
            'paid_at' => Carbon::parse('2026-03-05 10:00:00'),
        ]);

        Invoice::factory()->create([
            'billable_type' => 'user',
            'billable_id' => $this->admin->id,
            'status' => 'paid',
            'amount_due' => 500,
            'amount_paid' => 500,
            'amount_remaining' => 0,
            'subtotal' => 500,
            'tax' => 0,
            'total' => 500,
            'paid_at' => Carbon::parse('2026-03-08 10:00:00'),
        ]);

        Invoice::factory()->create([
            'billable_type' => 'user',
            'billable_id' => $this->admin->id,
            'status' => 'paid',
            'amount_due' => 1000,
            'amount_paid' => 1000,
            'amount_remaining' => 0,
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
            'paid_at' => Carbon::parse('2026-02-10 10:00:00'),
        ]);

        Invoice::factory()->open()->create([
            'billable_type' => 'user',
            'billable_id' => $this->admin->id,
            'amount_due' => 2500,
            'amount_paid' => 0,
            'amount_remaining' => 2500,
            'subtotal' => 2500,
            'tax' => 0,
            'total' => 2500,
            'paid_at' => null,
        ]);

        $widget = new RevenueChartWidget();
        $data = invade($widget)->getData();
        $labels = collect($data['labels']);
        $revenues = collect($data['datasets'][0]['data']);
        $februaryIndex = $labels->search('Feb 2026');
        $marchIndex = $labels->search('Mar 2026');

        expect($februaryIndex)->not->toBeFalse()
            ->and($marchIndex)->not->toBeFalse()
            ->and($revenues[$februaryIndex])->toBe(10.0)
            ->and($revenues[$marchIndex])->toBe(20.0);
    } finally {
        Carbon::setTestNow();
    }
});

test('plan distribution widget returns doughnut type', function () {
    $widget = new PlanDistributionWidget();
    expect(invade($widget)->getType())->toBe('doughnut');
});

test('plan distribution widget has correct column span', function () {
    $widget = new PlanDistributionWidget();
    expect($widget->getColumnSpan())->toBe(1);
});

test('plan distribution widget groups subscriptions by plan', function () {
    // Clear seeded subscriptions
    Subscription::query()->delete();

    $basicPlan = Plan::find(1);
    $premiumPlan = Plan::find(2);

    $users = User::factory()->count(4)->create();

    // 3 on basic, 1 on premium
    foreach ($users->take(3) as $user) {
        Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_dist_'.$user->id,
            'stripe_status' => 'active',
            'stripe_price' => $basicPlan->monthly_price_id,
            'quantity' => 1,
            'billable_type' => 'user',
            'billable_id' => $user->id,
            'plan_id' => $basicPlan->id,
            'cycle' => 'month',
        ]);
    }

    Subscription::create([
        'user_id' => $users->last()->id,
        'type' => 'default',
        'stripe_id' => 'sub_dist_premium',
        'stripe_status' => 'active',
        'stripe_price' => $premiumPlan->monthly_price_id,
        'quantity' => 1,
        'billable_type' => 'user',
        'billable_id' => $users->last()->id,
        'plan_id' => $premiumPlan->id,
        'cycle' => 'month',
    ]);

    $widget = new PlanDistributionWidget();
    $data = invade($widget)->getData();

    expect($data['labels'])->toContain('Basic')
        ->and($data['labels'])->toContain('Premium')
        ->and(array_sum($data['datasets'][0]['data']))->toBe(4);
});

test('at-risk subscribers widget has correct column span', function () {
    $widget = new AtRiskSubscribersWidget();
    expect($widget->getColumnSpan())->toBe(1);
});

test('dashboard page registers all analytics widgets', function () {
    $dashboard = new Dashboard();
    $widgets = $dashboard->getWidgets();

    expect($widgets)->toContain(StatsOverviewWidget::class)
        ->and($widgets)->toContain(RevenueChartWidget::class)
        ->and($widgets)->toContain(PlanDistributionWidget::class)
        ->and($widgets)->toContain(RecentTransactionsWidget::class)
        ->and($widgets)->toContain(AtRiskSubscribersWidget::class);
});

test('admin can access dashboard with all widgets', function () {
    $this->actingAs($this->admin);

    $this->get('/admin')
        ->assertOk();
});
