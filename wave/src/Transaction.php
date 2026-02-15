<?php

namespace Wave;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
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
     * The invoice associated with this transaction.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
