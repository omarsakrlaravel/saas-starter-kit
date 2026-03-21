<?php

namespace Wave;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Wave\Traits\BelongsToTenant;

class ApiKey extends Model
{
    use BelongsToTenant;

    protected $table = 'api_keys';

    public ?string $plainTextToken = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'key',
        'last_used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public static function findByIncomingToken(?string $token): ?self
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        if (str_contains($token, '|')) {
            [$id, $plainTextToken] = explode('|', $token, 2);

            if (! ctype_digit($id) || $plainTextToken === '') {
                return null;
            }

            $apiKey = static::query()->find((int) $id);

            if (! $apiKey instanceof self || ! $apiKey->usesHashedTokenStorage()) {
                return null;
            }

            return Hash::check($plainTextToken, $apiKey->key) ? $apiKey : null;
        }

        $apiKey = static::query()->where('key', $token)->first();

        if ($apiKey instanceof self && $apiKey->usesHashedTokenStorage()) {
            return null;
        }

        return $apiKey;
    }

    public function issuePlainTextToken(): string
    {
        $plainTextToken = bin2hex(random_bytes(20));

        $this->key = Hash::make($plainTextToken);
        $this->save();

        $this->plainTextToken = $this->formatIncomingToken($plainTextToken);

        return $this->plainTextToken;
    }

    public function maskedKey(): string
    {
        if ($this->plainTextToken) {
            return substr($this->plainTextToken, 0, 8).'...'.substr($this->plainTextToken, -4);
        }

        if ($this->usesHashedTokenStorage()) {
            return 'Stored securely';
        }

        return substr($this->key, 0, 10).'...'.substr($this->key, -5);
    }

    public function usesHashedTokenStorage(): bool
    {
        return password_get_info($this->key)['algo'] !== null;
    }

    public function scopeLegacyPlainText(Builder $query): Builder
    {
        return $query->where('key', 'not like', '$2y$%')
            ->where('key', 'not like', '$2a$%')
            ->where('key', 'not like', '$2b$%')
            ->where('key', 'not like', '$argon2%');
    }

    private function formatIncomingToken(string $plainTextToken): string
    {
        return "{$this->getKey()}|{$plainTextToken}";
    }
}
