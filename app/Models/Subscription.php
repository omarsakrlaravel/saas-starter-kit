<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (empty($subscription->billable_type)) {
                $subscription->billable_type = 'user';
            }
            if (empty($subscription->billable_id) && $subscription->user_id) {
                $subscription->billable_id = $subscription->user_id;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'billable_type',
        'billable_id',
        'plan_id',
        'cycle',
        'trial_ends_at',
        'ends_at',
        'last_payment_at',
        'next_payment_at',
        'pending_plan_id',
        'pending_cycle',
        'pending_change_scheduled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'pending_change_scheduled_at' => 'datetime',
        ];
    }

    /**
     * The user (Cashier billable) that owns the subscription.
     * Uses user_id as Cashier expects.
     */
    public function user(): BelongsTo
    {
        $userClass = config('saas.user_model', User::class);

        return $this->belongsTo($userClass, 'user_id');
    }

    /**
     * The polymorphic billable entity (User or Organization).
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function clearBillableCache(): void
    {
        if ($this->billable instanceof User) {
            $this->billable->clearUserCache($this->plan_id);
        } elseif ($this->billable instanceof Organization) {
            $this->billable->clearMembersBillingCache($this->plan_id);
        }
    }

    /**
     * The plan that belongs to the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * The plan this subscription is scheduled to change to.
     */
    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function hasPendingChange(): bool
    {
        return ! is_null($this->pending_plan_id);
    }

    public function cancelPendingChange(): void
    {
        $this->update([
            'pending_plan_id' => null,
            'pending_cycle' => null,
            'pending_change_scheduled_at' => null,
        ]);
    }

    /**
     * Apply the pending plan change (swap on Stripe without proration).
     * Uses a cache lock to prevent double execution from command + webhook race.
     */
    public function applyPendingChange(): bool
    {
        if (! $this->hasPendingChange()) {
            return false;
        }

        if ($this->pending_change_scheduled_at && $this->pending_change_scheduled_at->isFuture()) {
            return false;
        }

        $lockKey = 'pending_change_lock_'.$this->id;

        return Cache::lock($lockKey, 60)->get(function () {
            $this->refresh();

            if (! $this->hasPendingChange()) {
                return false;
            }

            if (! $this->valid()) {
                $this->cancelPendingChange();

                return false;
            }

            $pendingPlan = Plan::find($this->pending_plan_id);
            if (! $pendingPlan) {
                Log::warning('Pending plan not found, clearing pending change', [
                    'subscription_id' => $this->id,
                    'pending_plan_id' => $this->pending_plan_id,
                ]);
                $this->cancelPendingChange();

                return false;
            }

            $priceId = $this->pending_cycle === 'month'
                ? $pendingPlan->monthly_price_id
                : $pendingPlan->yearly_price_id;

            if (empty($priceId)) {
                Log::warning('Pending plan has no price for the requested cycle', [
                    'subscription_id' => $this->id,
                    'pending_plan_id' => $this->pending_plan_id,
                    'pending_cycle' => $this->pending_cycle,
                ]);
                $this->cancelPendingChange();

                return false;
            }

            try {
                $this->noProrate()->swap($priceId);

                $this->update([
                    'plan_id' => $pendingPlan->id,
                    'cycle' => $this->pending_cycle,
                    'pending_plan_id' => null,
                    'pending_cycle' => null,
                    'pending_change_scheduled_at' => null,
                ]);

                $this->clearBillableCache();

                Log::info('Pending plan change applied', [
                    'subscription_id' => $this->id,
                    'new_plan_id' => $pendingPlan->id,
                    'new_cycle' => $this->cycle,
                ]);

                return true;
            } catch (\Throwable $e) {
                Log::error('Failed to apply pending plan change', [
                    'subscription_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });
    }

    /**
     * The local invoice records for this subscription.
     */
    public function localInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The transactions for this subscription via invoices.
     */
    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Invoice::class);
    }
}
