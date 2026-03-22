<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
            'amount_remaining' => 'integer',
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_date' => 'datetime',
            'paid_at' => 'datetime',
            'line_items' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * The polymorphic billable entity (User or Organization).
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The subscription associated with this invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * The transactions for this invoice.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The coupon redemptions applied to this invoice.
     */
    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
