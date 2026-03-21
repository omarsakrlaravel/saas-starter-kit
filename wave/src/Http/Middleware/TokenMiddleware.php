<?php

namespace Wave\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Wave\ApiKey;

class TokenMiddleware
{
    protected Auth $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): mixed
    {
        if ($request->token && strlen((string) $request->token) <= 60) {
            $apiKey = ApiKey::findByIncomingToken((string) $request->token);
            if (! isset($apiKey->id)) {
                return $next($request);
            }

            $user = $apiKey->user;
            if (! $user instanceof User) {
                return $next($request);
            }

            if ($user->isBlockedFromSession()) {
                return response()->json($this->blockedAccountResponse($request), 403);
            }

            JWTAuth::fromUser($user);

            return $next($request);
        }

        $this->auth->authenticate($guard);
        $user = $this->auth->guard($guard)->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isBlockedFromSession()) {
            return response()->json($this->blockedAccountResponse($request), 403);
        }

        return $next($request);
    }

    private function blockedAccountResponse(Request $request): array
    {
        return [
            'status' => 'forbidden',
            'message' => 'Account access is blocked.',
            'error_code' => 'account_blocked',
            'path' => $request->path(),
        ];
    }
}
