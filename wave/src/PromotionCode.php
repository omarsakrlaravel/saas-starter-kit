<?php

namespace Wave;

use Database\Factories\PromotionCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionCode extends Model
{
    use HasFactory;

    protected static function newFactory(): PromotionCodeFactory
    {
        return PromotionCodeFactory::new();
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
            'active' => 'boolean',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'first_time_transaction' => 'boolean',
            'minimum_amount' => 'integer',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The coupon this promotion code belongs to.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The redemptions for this promotion code.
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
