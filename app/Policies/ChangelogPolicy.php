<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Wave\Changelog;

class ChangelogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Changelog');
    }

    public function view(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('View:Changelog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Changelog');
    }

    public function update(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('Update:Changelog');
    }

    public function delete(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('Delete:Changelog');
    }

    public function restore(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('Restore:Changelog');
    }

    public function forceDelete(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('ForceDelete:Changelog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Changelog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Changelog');
    }

    public function replicate(AuthUser $authUser, Changelog $changelog): bool
    {
        return $authUser->can('Replicate:Changelog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Changelog');
    }
}
