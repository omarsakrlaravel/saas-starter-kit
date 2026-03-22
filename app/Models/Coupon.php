<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
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
            'amount_off' => 'integer',
            'percent_off' => 'decimal:2',
            'duration_in_months' => 'integer',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'active' => 'boolean',
            'valid' => 'boolean',
            'redeem_by' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * The promotion codes for this coupon.
     */
    public function promotionCodes(): HasMany
    {
        return $this->hasMany(PromotionCode::class);
    }

    /**
     * The redemptions for this coupon.
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Get a human-readable discount display string.
     */
    public function discountDisplay(): string
    {
        if ($this->percent_off) {
            return rtrim(rtrim(number_format($this->percent_off, 2), '0'), '.').'%';
        }

        if ($this->amount_off) {
            $amount = number_format($this->amount_off / 100, 2);

            return currencySymbol($this->currency).$amount.' off';
        }

        return 'No discount';
    }
}
