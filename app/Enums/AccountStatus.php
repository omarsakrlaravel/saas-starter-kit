<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';

    case Restricted = 'restricted';

    case Suspended = 'suspended';

    public function isBlocking(): bool
    {
        return match ($this) {
            self::Active => false,
            self::Restricted, self::Suspended => true,
        };
    }

    public function isRestricted(): bool
    {
        return $this === self::Restricted;
    }

    public function isSuspended(): bool
    {
        return $this === self::Suspended;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Restricted => 'Restricted',
            self::Suspended => 'Suspended',
        };
    }
}
