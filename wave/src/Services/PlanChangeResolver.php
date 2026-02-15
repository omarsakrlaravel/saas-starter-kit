<?php

namespace Wave\Services;

use Wave\Plan;
use Wave\Subscription;

class PlanChangeResolver
{
    /**
     * Determine if switching from the current subscription to the new plan+cycle
     * is an upgrade, downgrade, or same.
     *
     * @return 'upgrade'|'downgrade'|'same'|'cycle_change'
     */
    public function resolve(Subscription $subscription, Plan $newPlan, string $newCycle): string
    {
        $currentPlanId = $subscription->plan_id;
        $currentCycle = $subscription->cycle;

        if ((int) $currentPlanId === (int) $newPlan->id && $currentCycle === $newCycle) {
            return 'same';
        }

        // Same plan, different cycle
        if ((int) $currentPlanId === (int) $newPlan->id) {
            return 'cycle_change';
        }

        // Different plan: compare effective monthly price
        $currentPlan = Plan::find($currentPlanId);
        if (! $currentPlan) {
            return 'upgrade';
        }

        $currentEffective = $this->effectiveMonthlyPrice($currentPlan, $currentCycle);
        $newEffective = $this->effectiveMonthlyPrice($newPlan, $newCycle);

        if ($newEffective > $currentEffective) {
            return 'upgrade';
        }

        if ($newEffective < $currentEffective) {
            return 'downgrade';
        }

        // Equal price: use sort_order (higher = higher tier)
        if ($newPlan->sort_order > $currentPlan->sort_order) {
            return 'upgrade';
        }

        if ($newPlan->sort_order < $currentPlan->sort_order) {
            return 'downgrade';
        }

        return 'same';
    }

    protected function effectiveMonthlyPrice(Plan $plan, string $cycle): float
    {
        if ($cycle === 'year') {
            return (float) ($plan->yearly_price ?? 0) / 12;
        }

        return (float) ($plan->monthly_price ?? 0);
    }
}
