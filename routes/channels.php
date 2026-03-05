<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private.user.{id}', function (User $user, int $id): bool {
    if ($user->isBlockedFromSession()) {
        return false;
    }

    return $user->id === $id;
});

Broadcast::channel('private.organization.{organization}', function (User $user, int $organizationId): bool {
    if ($user->isBlockedFromSession()) {
        return false;
    }

    return (int) $user->current_organization_id === $organizationId;
}, ['guards' => ['web']]);
