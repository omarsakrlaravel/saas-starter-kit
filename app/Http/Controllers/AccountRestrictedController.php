<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountRestrictedController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $context = $this->resolveAccountContext($user);
        $expires = $context['source']?->status_expires_at;

        $daysRemaining = null;

        $sourceType = $context['source'] instanceof Organization ? 'organization' : 'user';

        if ($expires instanceof \DateTimeInterface) {
            $daysRemaining = (int) floor(now()->diffInSeconds($expires, false) / 86400);

            if ($daysRemaining < 0) {
                $daysRemaining = 0;
            }
        }

        $actionHint = match (Str::lower($sourceType ?? 'user')) {
            'organization' => 'Ask an organization owner to update account billing status.',
            default => 'Ask an owner or administrator to review account access.',
        };

        return view('account.restricted', [
            'reason' => $context['source']?->status_reason,
            'days_remaining' => $daysRemaining,
            'expires_at' => $expires?->toDateTimeString(),
            'status_source' => $context['source'],
            'source_type' => $sourceType,
            'action_hint' => $actionHint,
        ]);
    }

    /**
     * @return array{state: ?string, source: User|Organization|null}
     */
    private function resolveAccountContext(?User $user): array
    {
        if (! ($user instanceof User)) {
            return [
                'state' => null,
                'source' => null,
            ];
        }

        $organization = $user->currentOrganization;

        if ($organization && $organization->isBlockedFromSession()) {
            return [
                'state' => $organization->status?->value,
                'source' => $organization,
            ];
        }

        if ($user->isBlockedFromSession()) {
            return [
                'state' => $user->status?->value,
                'source' => $user,
            ];
        }

        return [
            'state' => null,
            'source' => $user,
        ];
    }
}
