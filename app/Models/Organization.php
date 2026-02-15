<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Wave\Invoice;
use Wave\Subscription;
use Wave\Transaction;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'active',
    ];

    protected static function booted(): void
    {
        static::created(function (Organization $organization): void {
            $organization->ensureOwnerMembership();
        });

        static::updated(function (Organization $organization): void {
            if ($organization->wasChanged('owner_user_id')) {
                $organization->ensureOwnerMembership();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('wave.user_model', User::class), 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(config('wave.user_model', User::class), 'organization_user')
            ->withPivot(['role', 'status', 'invited_by', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'billable');
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'billable');
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->where('stripe_status', 'active')->orderByDesc('created_at')->first();
    }

    public function activeMemberCount(): int
    {
        return $this->members()->wherePivot('status', 'active')->count();
    }

    public function invitedMemberCount(): int
    {
        return $this->members()->wherePivot('status', 'invited')->count();
    }

    public function occupiedSeatCount(): int
    {
        return $this->activeMemberCount() + $this->invitedMemberCount();
    }

    public function availableSeatCount(): ?int
    {
        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return null;
        }

        return max(0, $subscription->quantity - $this->occupiedSeatCount());
    }

    public function hasAvailableSeatForNewInvite(): bool
    {
        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return true;
        }

        return $this->occupiedSeatCount() < $subscription->quantity;
    }

    public function clearMembersBillingCache(?int $planId = null): void
    {
        $this->members()->chunkById(100, function ($members) use ($planId): void {
            foreach ($members as $member) {
                $member->clearUserCache($planId);
            }
        });
    }

    protected function ensureOwnerMembership(): void
    {
        if (empty($this->owner_user_id)) {
            return;
        }

        $this->members()->syncWithoutDetaching([
            $this->owner_user_id => [
                'role' => 'owner',
                'status' => 'active',
                'invited_by' => $this->owner_user_id,
                'invited_at' => now(),
                'joined_at' => now(),
            ],
        ]);
    }
}
