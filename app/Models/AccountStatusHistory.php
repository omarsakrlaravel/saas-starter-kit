<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountStatusHistory extends Model
{
    protected $guarded = [];

    protected $table = 'account_status_histories';

    protected static function booted(): void
    {
        static::addGlobalScope('latest_first', function (Builder $builder): void {
            $builder->orderByDesc('applied_at');
        });
    }

    protected function casts(): array
    {
        return [
            'from_status' => AccountStatus::class,
            'to_status' => AccountStatus::class,
            'expires_at' => 'datetime',
            'applied_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function suspendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function scopeForEntity(Builder $query, Model $entity): Builder
    {
        return $query->where('suspendable_type', $entity->getMorphClass())
            ->where('suspendable_id', $entity->getKey());
    }
}
