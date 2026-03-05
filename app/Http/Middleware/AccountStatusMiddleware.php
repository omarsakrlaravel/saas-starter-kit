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
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = $request->is(
            'account/restricted',
            'billing*',
            'support*',
            'data-export',
            'livewire*',
            'broadcasting/auth',
        );

        if ($allowed) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $status = $this->effectiveAccountState($user);

        if (! $status['state']) {
            return $next($request);
        }

        if ($status['state']->isRestricted()) {
            if ($request->is(
                'account/restricted',
                'billing*',
                'support*',
                'data-export',
                'livewire*',
                'broadcasting/auth',
            )) {
                return $next($request);
            }

            return redirect()->route('account.restricted');
        }

        $reference = $this->supportReference();

        if ($request->expectsJson()) {
            return $this->jsonBlockedResponse($request, $reference);
        }

        return response()->view('account.suspended', [
            'support_reference' => $reference,
        ], 403);
    }

    /**
     * Resolve effective status based on organization first, then user.
     *
     * @return array{state: ?AccountStatus, source: User|Organization|null}
     */
    private function effectiveAccountState(User $user): array
    {
        $state = null;
        $source = null;

        if ($user->currentOrganization && $user->currentOrganization->isBlockedFromSession()) {
            $state = $user->currentOrganization->status;
            $source = $user->currentOrganization;
        } elseif ($user->isBlockedFromSession()) {
            $state = $user->status;
            $source = $user;
        }

        return [
            'state' => $state,
            'source' => $source,
        ];
    }

    private function jsonBlockedResponse(Request $request, string $supportReference): JsonResponse
    {
        return response()->json([
            'status' => 'forbidden',
            'error_code' => 'account_suspended',
            'support_reference' => $supportReference,
            'message' => 'Account has been suspended.',
            'path' => $request->path(),
        ], 403);
    }

    private function supportReference(): string
    {
        return 'ACCT-'.strtoupper(Str::random(10));
    }
}
