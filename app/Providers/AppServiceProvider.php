<?php

namespace App\Providers;

use App\Listeners\ApplySubscriptionMetadata;
use App\Listeners\HandleStripeWebhook;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Listeners\SendWelcomeNotification;
use App\Models\File;
use App\Models\Forms;
use App\Models\Organization;
use App\Policies\FilePolicy;
use Exception;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Vite as BaseVite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Folio\Folio;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cashier configuration
        Cashier::ignoreRoutes();
        Cashier::useCustomerModel(config('saas.user_model', \App\Models\User::class));
        Cashier::useSubscriptionModel(\App\Models\Subscription::class);

        // TenantContext singleton
        $this->app->singleton(\App\Services\TenantContext::class);

        // Intervention Image Manager
        $this->app->singleton('image', function () {
            return new ImageManager(new Driver());
        });

        // Middleware aliases
        $this->app->router->aliasMiddleware('subscribed', \App\Http\Middleware\Subscribed::class);
        $this->app->router->aliasMiddleware('can-manage-billing', \App\Http\Middleware\CanManageBilling::class);
        $this->app->router->aliasMiddleware('handle-org-invite', \App\Http\Middleware\HandleOrganizationInvite::class);

        // Install middleware when no DB connection
        if (! $this->hasDBConnection()) {
            $this->app->router->pushMiddlewareToGroup('web', \App\Http\Middleware\InstallMiddleware::class);
        }

        // Demo mode: override Vite asset helper
        if (config('saas.demo')) {
            $this->app->singleton(BaseVite::class, function ($app) {
                return new \App\Overrides\Vite();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment() == 'production') {
            $this->app['request']->server->set('HTTPS', true);
        }

        $this->setSchemaDefaultLength();

        // Filament component friendly names
        $this->registerFilamentComponentsFriendlyNames();

        // Blade directives
        $this->loadBladeDirectives();

        // Filament colors
        FilamentColor::register([
            'danger' => Color::Red,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'primary' => config('saas.primary_color'),
            'success' => Color::Green,
            'warning' => Color::Amber,
        ]);

        // Imageable validator
        Validator::extend('imageable', function ($attribute, $value, $params, $validator) {
            try {
                $manager = new ImageManager(new Driver());
                $manager->read($value);

                return true;
            } catch (Exception $e) {
                return false;
            }
        });

        // Morph map
        Relation::morphMap([
            'user' => config('saas.user_model', \App\Models\User::class),
            'form' => Forms::class,
            'organization' => Organization::class,
        ]);

        // Anonymous component paths
        Blade::anonymousComponentPath(resource_path('views/components/elements'));

        // Folio pages
        $this->registerFolioDirectory();

        // Feature discovery & scope
        Feature::discover();

        Feature::resolveScopeUsing(function ($driver) {
            $organizationId = app(\App\Services\TenantContext::class)->get();
            if ($organizationId) {
                return Organization::find($organizationId);
            }

            return auth()->user();
        });

        // Gate policy
        Gate::policy(File::class, FilePolicy::class);

        // Event listeners
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(WebhookReceived::class, HandleStripeWebhook::class);
        Event::listen(WebhookHandled::class, ApplySubscriptionMetadata::class);
        Event::listen(Registered::class, SendWelcomeNotification::class);

        // Base64 image validator
        Validator::extend('base64image', function ($attribute, $value, $parameters, $validator) {
            $explode = explode(',', $value);
            $allow = ['png', 'jpg', 'svg', 'jpeg'];
            $format = str_replace(
                [
                    'data:image/',
                    ';',
                    'base64',
                ],
                [
                    '', '', '',
                ],
                $explode[0]
            );

            if (! in_array($format, $allow)) {
                return false;
            }

            if (! preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $explode[1])) {
                return false;
            }

            return true;
        });

        $this->bootRoute();
    }

    protected function registerFilamentComponentsFriendlyNames(): void
    {
        Blade::component('filament::components.dropdown.index', 'dropdown');
        Blade::component('filament::components.dropdown.list.index', 'dropdown.list');
        Blade::component('filament::components.dropdown.list.item', 'dropdown.list.item');
    }

    protected function loadBladeDirectives(): void
    {
        Blade::if('admin', function () {
            return ! auth()->guest() && auth()->user()->isAdmin();
        });

        Blade::if('subscriber', function () {
            return ! auth()->guest() && auth()->user()->subscriber();
        });

        Blade::if('notsubscriber', function () {
            return ! auth()->guest() && ! auth()->user()->subscriber();
        });

        Blade::if('subscribed', function ($plan) {
            return ! auth()->guest() && auth()->user()->subscribedToPlan($plan);
        });

        Blade::if('home', function () {
            return request()->is('/');
        });

        Blade::if('canUseFeature', function (string $feature, int $amount = 1) {
            return ! auth()->guest() && auth()->user()->canUseFeature($feature, $amount);
        });

        Blade::if('featureNearLimit', function (string $feature, float $threshold = 0.8) {
            return ! auth()->guest() && auth()->user()->featureNearLimit($feature, $threshold);
        });

        Blade::if('featureLimitReached', function (string $feature) {
            return ! auth()->guest() && auth()->user()->featureLimitReached($feature);
        });
    }

    protected function registerFolioDirectory(): void
    {
        $path = resource_path('views/pages');
        if (is_dir($path)) {
            Folio::path($path)->middleware([
                '*' => [],
            ]);
        }
    }

    protected function hasDBConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function setSchemaDefaultLength(): void
    {
        try {
            Schema::defaultStringLength(191);
        } catch (Exception $exception) {
        }
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: (request()->header('CF-Connecting-IP') ?? request()->ip()));
        });
    }
}
