<?php

namespace App\Features;

use Laravel\Pennant\Attributes\Name;

#[Name('maintenance-mode')]
class MaintenanceMode
{
    /**
     * Resolve the feature's initial value.
     *
     * Kill switch: default off. Toggled globally via
     * Feature::for(null)->activate('maintenance-mode') in admin,
     * checked via Feature::for(null)->active('maintenance-mode') in code.
     */
    public function resolve(mixed $scope): bool
    {
        return false;
    }
}
