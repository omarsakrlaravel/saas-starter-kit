<?php

namespace Wave\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CanManageBilling
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): RedirectResponse|JsonResponse
    {
        if (auth()->check() && auth()->user()->canManageBillingContext()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['status' => 0, 'message' => 'You are not authorized to manage billing for this account.'],
                422
            );
        }

        return redirect()->back()->with([
            'message' => 'You are not authorized to manage billing for this account.',
            'message_type' => 'danger',
        ]);
    }
}
