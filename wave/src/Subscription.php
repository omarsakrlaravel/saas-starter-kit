<?php

namespace Wave;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Subscription extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'billable_type',
        'billable_id',
        'plan_id',
        'vendor_slug',
        'vendor_product_id',
        'vendor_transaction_id',
        'vendor_customer_id',
        'vendor_subscription_id',
        'cycle',
        'status',
        'seats',
        'trial_ends_at',
        'ends_at',
        'last_payment_at',
        'next_payment_at',
        'cancel_url',
        'update_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'next_payment_at' => 'datetime',
        ];
    }

    /**
     * The user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        $userClass = config('wave.user_model', User::class);

        if ((string) $this->billable_type !== 'user') {
            return $this->belongsTo($userClass, 'billable_id')->whereKey(0);
        }

        return $this->belongsTo($userClass, 'billable_id');
    }

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

    public function cancel()
    {
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->save();
    }

    /**
     * The plan that belongs to the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
