<?php

namespace App\Features;

use Illuminate\Support\Lottery;
use Laravel\Pennant\Attributes\Name;

#[Name('new-editor')]
class NewEditor
{
    /**
     * Resolve the feature's initial value.
     *
     * Gradual rollout: 10% of scopes get the new editor. Pennant caches the
     * resolved value per scope in the database, so each organization gets a
     * consistent result after first check.
     */
    public function resolve(mixed $scope): bool
    {
        return Lottery::odds(1, 10)->choose();
    }
}
