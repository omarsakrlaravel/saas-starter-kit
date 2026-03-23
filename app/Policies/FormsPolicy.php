<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Form;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FormsPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Forms');
    }

    public function view(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('View:Forms');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Forms');
    }

    public function update(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('Update:Forms');
    }

    public function delete(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('Delete:Forms');
    }

    public function restore(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('Restore:Forms');
    }

    public function forceDelete(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('ForceDelete:Forms');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Forms');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Forms');
    }

    public function replicate(AuthUser $authUser, Form $forms): bool
    {
        return $authUser->can('Replicate:Forms');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Forms');
    }
}
