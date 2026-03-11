<?php

namespace App\Policies;

use App\Enums\FileAccessLevel;
use App\Models\User;
use Wave\File;

class FilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, File $file): bool
    {
        return $this->download($user, $file);
    }

    public function download(User $user, File $file): bool
    {
        if ($file->access_level === FileAccessLevel::AppPublic) {
            return true;
        }

        if ($file->organization_id !== null) {
            return $user->organizations()
                ->where('organizations.id', $file->organization_id)
                ->exists();
        }

        return $file->uploaded_by_user_id === $user->id;
    }

    public function delete(User $user, File $file): bool
    {
        return $file->uploaded_by_user_id === $user->id;
    }

    public function forceDelete(User $user, File $file): bool
    {
        return false;
    }
}
