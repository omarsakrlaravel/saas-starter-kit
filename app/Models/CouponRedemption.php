<?php

namespace App\Models;

use Database\Factories\CouponRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CouponRedemption extends Model
{
    use HasFactory;

    protected static function newFactory(): CouponRedemptionFactory
    {
        return CouponRedemptionFactory::new();
    }

    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * The coupon that was redeemed.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The promotion code used for this redemption.
     */
    public function promotionCode(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class);
    }

    /**
     * The invoice this redemption was applied to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The subscription this redemption was applied to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The polymorphic billable entity (User or Organization).
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
