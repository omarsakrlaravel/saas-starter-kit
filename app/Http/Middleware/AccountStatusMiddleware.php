<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Models\Organization;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AccountStatusMiddleware
{
    private const RESTRICTED_ALLOWED_ROUTES = [
        'account/restricted',
        'auth/*',
        'logout',
        'settings/profile',
        'settings/security',
        'settings/subscription*',
        'settings/export',
        'livewire*',
        'broadcasting/auth',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $status = $this->effectiveAccountState($user);

        if (! $status['state']) {
            return $next($request);
        }

        $reference = $this->supportReference();

        if ($request->expectsJson()) {
            return $this->jsonBlockedResponse($request, $reference, $status['state']);
        }

        if ($status['state']->isRestricted()) {
            if ($request->is(...self::RESTRICTED_ALLOWED_ROUTES)) {
                return $next($request);
            }

            return redirect()->route('account.restricted');
        }

        return response()->view('account.suspended', [
            'support_reference' => $reference,
        ], 403);
    }

    /**
     * Resolve effective status -- worst status wins (suspended > restricted).
     *
     * @return array{state: ?AccountStatus, source: User|Organization|null}
     */
    private function effectiveAccountState(User $user): array
    {
        $userState = $user->status?->isBlocking() ? $user->status : null;
        $orgState = null;

        $organization = $user->currentOrganizationForContext();
        if ($organization?->isBlockedFromSession()) {
            $orgState = $organization->status;
        }

        if ($userState?->isSuspended() || $orgState?->isSuspended()) {
            $suspended = $userState?->isSuspended() ? $user : $organization;

            return ['state' => AccountStatus::Suspended, 'source' => $suspended];
        }

        if ($userState?->isRestricted()) {
            return ['state' => $userState, 'source' => $user];
        }

        if ($orgState?->isRestricted()) {
            return ['state' => $orgState, 'source' => $organization];
        }

        return ['state' => null, 'source' => null];
    }

    private function jsonBlockedResponse(Request $request, string $supportReference, AccountStatus $state): JsonResponse
    {
        return response()->json([
            'status' => 'forbidden',
            'error_code' => 'account_'.$state->value,
            'support_reference' => $supportReference,
            'message' => 'Account has been '.$state->value.'.',
            'path' => $request->path(),
        ], 403);
    }

    private function supportReference(): string
    {
        return 'ACCT-'.strtoupper(Str::random(10));
    }
}
