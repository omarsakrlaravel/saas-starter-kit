<?php

namespace Wave\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wave\TenantContext;

class TenantAware
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $organizationId = method_exists($user, 'currentOrganizationIdForContext')
                ? $user->currentOrganizationIdForContext()
                : ($user->current_organization_id ?? null);

            if ($organizationId !== null) {
                $this->context->set($organizationId);
            }
        }

        return $next($request);
    }
}
