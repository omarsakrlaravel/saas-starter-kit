<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'active',
        'status',
        'status_reason',
        'status_expires_at',
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
            'status' => AccountStatus::class,
            'status_expires_at' => 'datetime',
        ];
    }

    public function isActiveAccount(): bool
    {
        return ! $this->isRestricted() && ! $this->isSuspended();
    }

    public function isRestricted(): bool
    {
        return $this->status?->isRestricted() ?? false;
    }

    public function isSuspended(): bool
    {
        return $this->status?->isSuspended() ?? false;
    }

    public function isBlockedFromSession(): bool
    {
        return $this->isRestricted() || $this->isSuspended();
    }

    public function statusDisplay(): string
    {
        return $this->status?->label() ?? AccountStatus::Active->label();
    }

    public function organizationIsBlocked(): bool
    {
        return $this->isBlockedFromSession();
    }

    public function activeOrganizationOrSelf(): self
    {
        return $this;
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActiveOrRestricted(Builder $query): Builder
    {
        return $query->whereIn('status', [AccountStatus::Active->value, AccountStatus::Restricted->value]);
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(AccountStatusHistory::class, 'suspendable');
    }

    public function recordStatusTransition(AccountStatus $toStatus, ?string $reason = null, ?int $appliedById = null, ?Carbon $expiresAt = null): AccountStatusHistory
    {
        $history = $this->statusHistories()->create([
            'from_status' => $this->status?->value,
            'to_status' => $toStatus->value,
            'reason' => $reason,
            'applied_by' => $appliedById,
            'expires_at' => $expiresAt,
            'applied_at' => now(),
            'reference_code' => 'ACCT-'.strtoupper(Str::random(10)),
        ]);

        $this->update([
            'status' => $toStatus->value,
            'status_reason' => $reason,
            'status_expires_at' => $expiresAt,
        ]);

        return $history;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('saas.user_model', User::class), 'owner_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(config('saas.user_model', User::class), 'organization_user')
            ->withPivot(['role', 'status', 'invited_by', 'invited_at', 'joined_at'])
            ->withTimestamps();
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'billable');
    }

    public function localInvoices(): MorphMany
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
