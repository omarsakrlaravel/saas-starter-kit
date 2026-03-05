<?php

use App\Providers\AppServiceProvider;
use App\Http\Middleware\AccountStatusMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Lab404\Impersonate\ImpersonateServiceProvider::class,
        \Wave\WaveServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->encryptCookies(except: []);
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);

        $middleware->append(\Filament\Http\Middleware\DisableBladeIconComponents::class);

        $middleware->web(AccountStatusMiddleware::class);
        $middleware->web(\RalphJSmit\Livewire\Urls\Middleware\LivewireUrlsMiddleware::class);
        $middleware->web(\Wave\Http\Middleware\HandleOrganizationInvite::class);
        $middleware->web(\Wave\Http\Middleware\TenantAware::class);
        $middleware->appendToGroup('api', AccountStatusMiddleware::class);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
