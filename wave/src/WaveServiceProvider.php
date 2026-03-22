<?php

namespace Wave;

use App\Models\Forms;
use App\Models\Organization;
use Exception;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Vite as BaseVite;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Laravel\Folio\Folio;
use Livewire\Livewire;
use Wave\Console\Commands\ApplyPendingPlanChanges;
use Wave\Console\Commands\CleanOldActivityLogs;
use Wave\Console\Commands\ProcessScheduledAccountDeletions;
use Wave\Console\Commands\WaveStats;
use Wave\Facades\Wave as WaveFacade;
use Wave\Http\Livewire\Billing\Checkout;
use Wave\Http\Livewire\Billing\CheckoutReview;
use Wave\Http\Livewire\Billing\Update;
use Wave\Http\Middleware\CanManageBilling;
use Wave\Http\Middleware\HandleOrganizationInvite;
use Wave\Http\Middleware\InstallMiddleware;
use Wave\Http\Middleware\Subscribed;
use Wave\Overrides\Vite;

class WaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $loader = AliasLoader::getInstance();
        $loader->alias('Wave', WaveFacade::class);

        $this->app->singleton('wave', function () {
            return new Wave();
        });

        $this->app->singleton(TenantContext::class);

        // Register Intervention Image Manager
        $this->app->singleton('image', function () {
            return new ImageManager(new Driver());
        });

        // Move helper loading to boot method to avoid cache service dependency

        $this->loadLivewireComponents();

        $this->app->router->aliasMiddleware('subscribed', Subscribed::class);
        $this->app->router->aliasMiddleware('can-manage-billing', CanManageBilling::class);
        $this->app->router->aliasMiddleware('handle-org-invite', HandleOrganizationInvite::class);

        if (! $this->hasDBConnection()) {
            $this->app->router->pushMiddlewareToGroup('web', InstallMiddleware::class);
        }

        if (config('wave.demo')) {
            // Overwrite the Vite asset helper so we can use the demo folder as opposed to the build folder
            $this->app->singleton(BaseVite::class, function ($app) {
                // Replace the default Vite instance with the custom one
                return new Vite();
            });
        }
    }

    public function boot(Router $router, Dispatcher $event): void
    {
        $this->registerFilamentComponentsFriendlyNames();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'wave');
        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));
        $this->loadBladeDirectives();
        $this->loadHelpers();

        FilamentColor::register([
            'danger' => Color::Red,
            'gray' => Color::Zinc,
            'info' => Color::Blue,
            'primary' => config('wave.primary_color'),
            'success' => Color::Green,
            'warning' => Color::Amber,
        ]);

        Validator::extend('imageable', function ($attribute, $value, $params, $validator) {
            try {
                $manager = new ImageManager(new Driver());
                $manager->read($value);

                return true;
            } catch (Exception $e) {
                return false;
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                WaveStats::class,
                CleanOldActivityLogs::class,
                ProcessScheduledAccountDeletions::class,
                ApplyPendingPlanChanges::class,
            ]);
        }

        Relation::morphMap([
            'user' => config('wave.user_model', \App\Models\User::class),
            'form' => Forms::class,
            'organization' => Organization::class,
            // Add other mappings as needed
        ]);

        $this->registerWaveFolioDirectory();
        $this->registerWaveComponentDirectory();

        $this->registerThemeViewNamespace();
        $this->registerThemeComponentDirectories();
        $this->registerThemeFolioDirectory();
    }

    protected function loadHelpers(): void
    {
        $helperPattern = __DIR__.'/Helpers/*.php';
        $helpers = [];

        try {
            // Only use cache if it's safe and available
            if ($this->app->bound('cache') && $this->app->make('cache')->getStore()) {
                $helpers = Cache::rememberForever('wave_helpers', function () use ($helperPattern) {
                    // Store only filenames, not absolute paths
                    return array_map('basename', glob($helperPattern));
                });

                // Validate cached filenames (not absolute paths)
                $helpers = array_filter($helpers, function ($filename) {
                    $fullPath = __DIR__.'/Helpers/'.$filename;

                    return file_exists($fullPath);
                });

                // If no valid helpers remain (e.g., after deployment), repopulate
                if (empty($helpers)) {
                    Cache::forget('wave_helpers');
                    $helpers = array_map('basename', glob($helperPattern));
                    Cache::forever('wave_helpers', $helpers);
                }
            } else {
                // Fallback: load directly without cache
                $helpers = array_map('basename', glob($helperPattern));
            }

        } catch (\Throwable $e) {
            // Fallback to direct loading if cache fails
            $helpers = array_map('basename', glob($helperPattern));
        }

        // Require each helper safely
        foreach ($helpers as $filename) {
            $fullPath = __DIR__.'/Helpers/'.$filename;
            if (file_exists($fullPath)) {
                require_once $fullPath;
            }
        }
    }

    protected function loadMiddleware()
    {
        foreach (glob(__DIR__.'/Http/Middleware/*.php') as $filename) {
            require_once $filename;
        }
    }

    protected function loadBladeDirectives()
    {

        // app()->afterResolving('blade.compiler', function (BladeCompiler $bladeCompiler) {
        // @admin directives
        Blade::if('admin', function () {
            return ! auth()->guest() && auth()->user()->isAdmin();
        });

        // @subscriber directives
        Blade::if('subscriber', function () {
            return ! auth()->guest() && auth()->user()->subscriber();
        });

        // @notsubscriber directives
        Blade::if('notsubscriber', function () {
            return ! auth()->guest() && ! auth()->user()->subscriber();
        });

        // Subscribed Directives
        Blade::if('subscribed', function ($plan) {
            return ! auth()->guest() && auth()->user()->subscribedToPlan($plan);
        });

        // home directives
        Blade::if('home', function () {
            return request()->is('/');
        });

        // @canUseFeature directives - check if user can use more of a feature
        Blade::if('canUseFeature', function (string $feature, int $amount = 1) {
            return ! auth()->guest() && auth()->user()->canUseFeature($feature, $amount);
        });

        // @featureNearLimit directives - check if user is approaching limit
        Blade::if('featureNearLimit', function (string $feature, float $threshold = 0.8) {
            return ! auth()->guest() && auth()->user()->featureNearLimit($feature, $threshold);
        });

        // @featureLimitReached directives - check if limit has been reached
        Blade::if('featureLimitReached', function (string $feature) {
            return ! auth()->guest() && auth()->user()->featureLimitReached($feature);
        });

    }

    protected function registerFilamentComponentsFriendlyNames()
    {
        // Blade::component('filament::components.avatar', 'avatar');
        Blade::component('filament::components.dropdown.index', 'dropdown');
        Blade::component('filament::components.dropdown.list.index', 'dropdown.list');
        Blade::component('filament::components.dropdown.list.item', 'dropdown.list.item');
    }

    protected function registerWaveFolioDirectory()
    {
        if (File::exists(base_path('wave/resources/views/pages'))) {
            Folio::path(base_path('wave/resources/views/pages'))->middleware([
                '*' => [
                    //
                ],
            ]);
        }
    }

    protected function registerWaveComponentDirectory()
    {
        Blade::anonymousComponentPath(base_path('wave/resources/views/components'));
    }

    private function loadLivewireComponents()
    {
        Livewire::component('billing.checkout', Checkout::class);
        Livewire::component('billing.checkout-review', CheckoutReview::class);
        Livewire::component('billing.update', Update::class);
    }

    protected function registerThemeViewNamespace()
    {
        $this->loadViewsFrom(resource_path('themes/anchor'), 'theme');
    }

    protected function registerThemeComponentDirectories()
    {
        Blade::anonymousComponentPath(resource_path('themes/anchor/components'));
        Blade::anonymousComponentPath(resource_path('themes/anchor/components/elements'));
    }

    protected function registerThemeFolioDirectory()
    {
        $path = resource_path('themes/anchor/pages');
        if (File::exists($path)) {
            Folio::path($path)->middleware([
                '*' => [
                    //
                ],
            ]);
        }
    }

    protected function hasDBConnection()
    {
        $hasDatabaseConnection = true;

        try {
            DB::connection()->getPdo();
        } catch (Exception $e) {
            $hasDatabaseConnection = false;
        }

        return $hasDatabaseConnection;
    }
}
