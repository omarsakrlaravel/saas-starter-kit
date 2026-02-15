<?php

namespace Wave;

use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): PaymentMethodFactory
    {
        return PaymentMethodFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exp_month' => 'integer',
            'exp_year' => 'integer',
            'is_default' => 'boolean',
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
}
