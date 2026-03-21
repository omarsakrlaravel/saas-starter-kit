<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Wave\Http\Middleware\TenantAware;
use Wave\TenantContext;

test('it sets tenant context from authenticated user current organization', function () {
    $context = new TenantContext();
    $middleware = new TenantAware($context);
    $request = Request::create('/tenant-aware', 'GET');
    $request->setUserResolver(static function (): object {
        return new class()
        {
            public ?int $current_organization_id = 42;
        };
    });

    $response = $middleware->handle($request, static fn (Request $request): Response => new Response('ok'));

    expect($response->getStatusCode())->toBe(200);
    expect($context->get())->toBe(42);
    expect($context->has())->toBeTrue();
});

test('it prefers a validated current organization resolver when available', function () {
    $context = new TenantContext();
    $middleware = new TenantAware($context);
    $request = Request::create('/tenant-aware', 'GET');
    $request->setUserResolver(static function (): object {
        return new class()
        {
            public ?int $current_organization_id = 42;

            public function currentOrganizationIdForContext(): ?int
            {
                return null;
            }
        };
    });

    $response = $middleware->handle($request, static fn (Request $request): Response => new Response('ok'));

    expect($response->getStatusCode())->toBe(200);
    expect($context->get())->toBeNull();
    expect($context->has())->toBeFalse();
});

test('it does not set tenant context when user has no current organization', function () {
    $context = new TenantContext();
    $middleware = new TenantAware($context);
    $request = Request::create('/tenant-aware', 'GET');
    $request->setUserResolver(static function (): object {
        return new class()
        {
            public ?int $current_organization_id = null;
        };
    });

    $response = $middleware->handle($request, static fn (Request $request): Response => new Response('ok'));

    expect($response->getStatusCode())->toBe(200);
    expect($context->get())->toBeNull();
    expect($context->has())->toBeFalse();
});

test('it does not set tenant context for unauthenticated requests', function () {
    $context = new TenantContext();
    $middleware = new TenantAware($context);
    $request = Request::create('/tenant-aware', 'GET');
    $request->setUserResolver(static fn (): null => null);

    $response = $middleware->handle($request, static fn (Request $request): Response => new Response('ok'));

    expect($response->getStatusCode())->toBe(200);
    expect($context->get())->toBeNull();
    expect($context->has())->toBeFalse();
});
