<?php

namespace App\Jobs\Middleware;

use App\Models\Organization;
use App\Models\User;
use Closure;

class EnsureAccountActive
{
    public function handle(object $job, Closure $next): void
    {
        $user = $this->resolveUser($job);
        if ($user instanceof User && $user->isBlockedFromSession()) {
            return;
        }

        $organization = $this->resolveOrganization($job);
        if ($organization instanceof Organization && $organization->isBlockedFromSession()) {
            return;
        }

        $next($job);
    }

    private function resolveUser(object $job): ?User
    {
        if (isset($job->user) && $job->user instanceof User) {
            return $job->user;
        }

        if (! isset($job->user_id)) {
            return $this->resolveUserFromPayload($job);
        }

        $userId = is_array($job->user_id) || is_object($job->user_id) ? null : (int) $job->user_id;
        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }

    private function resolveOrganization(object $job): ?Organization
    {
        if (! isset($job->organization_id)) {
            return $this->resolveOrganizationFromPayload($job);
        }

        $organizationId = is_array($job->organization_id) || is_object($job->organization_id)
            ? null
            : (int) $job->organization_id;

        if (! $organizationId) {
            return null;
        }

        return Organization::find($organizationId);
    }

    private function resolveUserFromPayload(object $job): ?User
    {
        if (! property_exists($job, 'data') || ! is_array($job->data) || ! isset($job->data['user_id'])) {
            return null;
        }

        $userId = is_array($job->data['user_id']) || is_object($job->data['user_id'])
            ? null
            : (int) $job->data['user_id'];

        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }

    private function resolveOrganizationFromPayload(object $job): ?Organization
    {
        if (! property_exists($job, 'data') || ! is_array($job->data) || ! isset($job->data['organization_id'])) {
            return null;
        }

        $organizationId = is_array($job->data['organization_id']) || is_object($job->data['organization_id'])
            ? null
            : (int) $job->data['organization_id'];

        if (! $organizationId) {
            return null;
        }

        return Organization::find($organizationId);
    }
}
