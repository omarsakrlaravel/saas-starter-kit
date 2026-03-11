<?php

namespace App\Features;

use App\Models\Organization;
use Laravel\Pennant\Attributes\Name;

#[Name('ai-reports')]
class AiReports
{
    /**
     * Resolve the feature's initial value.
     *
     * Plan-gated: checks if the organization's subscription plan includes 'ai-reports'
     * in its features array.
     */
    public function resolve(mixed $scope): bool
    {
        if ($scope instanceof Organization) {
            $features = $scope->activeSubscription()?->plan?->features ?? [];

            return in_array('ai-reports', $features);
        }

        return false;
    }
}
