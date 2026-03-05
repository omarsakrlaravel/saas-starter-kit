<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Wave\ActivityLog;
use Wave\Invoice;
use Wave\Traits\HasProfileKeyValues;
use Wave\User as WaveUser;

class User extends WaveUser
{
    use HasFactory, HasProfileKeyValues, Notifiable, SoftDeletes;

    public $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'verification_code',
        'verified',
        'trial_ends_at',
        'current_organization_id',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'status',
        'status_reason',
        'status_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'status' => AccountStatus::class,
            'notification_preferences' => 'array',
            'social_links' => 'array',
            'privacy_settings' => 'array',
            'status_expires_at' => 'datetime',
            'deletion_scheduled_at' => 'datetime',
        ]);
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
        return $this->isRestricted() || $this->isSuspended() || $this->organizationIsBlocked();
    }

    public function statusDisplay(): string
    {
        return $this->status?->label() ?? AccountStatus::Active->label();
    }

    public function organizationIsBlocked(): bool
    {
        $organization = $this->currentOrganization;

        if ($organization === null) {
            return false;
        }

        return $organization->isRestricted() || $organization->isSuspended();
    }

    public function activeOrganizationOrSelf(): self|Organization
    {
        if ($this->currentOrganization?->isActiveAccount()) {
            return $this->currentOrganization;
        }

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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function localInvoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    protected static function boot()
    {
        parent::boot();

        // Listen for the creating event of the model
        static::creating(function ($user) {
            // Check if the username attribute is empty
            if (empty($user->username)) {
                // Use the name to generate a slugified username
                $username = Str::slug($user->name, '');
                $i = 1;
                while (self::where('username', $username)->exists()) {
                    $username = Str::slug($user->name, '').$i;
                    $i++;
                }
                $user->username = $username;
            }
        });

    }
}
