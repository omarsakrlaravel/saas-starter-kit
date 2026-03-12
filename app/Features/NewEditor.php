<?php

namespace App\Features;

use App\Models\FeatureDefinition;
use Illuminate\Support\Lottery;
use Laravel\Pennant\Attributes\Name;

#[Name('new-editor')]
class NewEditor
{
    public function resolve(mixed $scope): bool
    {
        $definition = FeatureDefinition::where('name', 'new-editor')->first();

        $percentage = $definition?->rollout_percentage ?? 0;

        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        return Lottery::odds($percentage, 100)->choose();
    }
}
