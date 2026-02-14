<?php

namespace Wave;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
        ];
    }

    /**
     * The user (Cashier billable) that owns the subscription.
     * Uses user_id as Cashier expects.
     */
    public function user(): BelongsTo
    {
        $userClass = config('wave.user_model', User::class);

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
}
