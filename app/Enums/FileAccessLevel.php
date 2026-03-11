<?php

namespace App\Enums;

enum FileAccessLevel: string
{
    case Private = 'private';

    case AppPublic = 'app_public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::AppPublic => 'App Public',
        };
    }
}
