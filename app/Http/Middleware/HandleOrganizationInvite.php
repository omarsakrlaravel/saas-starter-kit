<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleOrganizationInvite
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->header('X-Livewire')) {
            return $next($request);
        }

        if (auth()->check() && session()->has('org_invite_url')) {
            $url = session()->pull('org_invite_url');
            if (is_string($url)) {
                return redirect($url);
            }
        }

        return $next($request);
    }
}
