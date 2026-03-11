<?php

namespace Wave;

use App\Enums\FileAccessLevel;
use App\Models\User;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Wave\Traits\BelongsToTenant;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $organization_id
 * @property int $uploaded_by_user_id
 * @property string|null $fileable_type
 * @property int|null $fileable_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property FileAccessLevel $access_level
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class File extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory(): FileFactory
    {
        return FileFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (File $file): void {
            if (empty($file->uuid)) {
                $file->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_level' => FileAccessLevel::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(config('wave.user_model', User::class), 'uploaded_by_user_id');
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to files uploaded by a specific user.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('uploaded_by_user_id', $user->id);
    }

    /**
     * Scope to files accessible by a specific user.
     *
     * Returns files that are app_public, belong to one of the user's organizations, or were uploaded by the user.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('access_level', FileAccessLevel::AppPublic->value)
                ->orWhereIn('organization_id', $user->organizations()->pluck('organizations.id'))
                ->orWhere('uploaded_by_user_id', $user->id);
        });
    }

    public function isPrivate(): bool
    {
        return $this->access_level === FileAccessLevel::Private;
    }

    public function isAppPublic(): bool
    {
        return $this->access_level === FileAccessLevel::AppPublic;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->uploaded_by_user_id === $user->id;
    }
}
