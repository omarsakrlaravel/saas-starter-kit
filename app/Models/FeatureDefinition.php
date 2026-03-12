<?php

namespace App\Models;

use App\Enums\FeatureFlagType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureDefinition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => FeatureFlagType::class,
            'is_active' => 'boolean',
            'rollout_percentage' => 'integer',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_changed_by');
    }
}
