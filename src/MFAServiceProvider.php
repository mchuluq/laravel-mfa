<?php namespace Mchuluq\LaravelMFA;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Mchuluq\LaravelMFA\Http\Middleware\RequireMFA;

class MFAServiceProvider extends ServiceProvider{
    
    public function register(){
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/mfa.php','mfa');
        // Register MFA Manager as singleton
        $this->app->singleton('mfa', function ($app) {
            return new MFAManager($app);
        });
        // Register middleware
        $this->app->singleton(RequireMFA::class, function ($app) {
            return new RequireMFA($app['mfa']);
        });

        // merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/mfa.php', 'mfa');
    }

    public function boot(){
        // Publish config
        $this->publishConfig();
        
        // Publish migrations
        $this->publishMigrations();

        // Publish views
        $this->publishViews();

        // Register routes
        $this->registerRoutes();

        // Register middleware
        $this->registerMiddleware();

        // Register event listeners
        $this->registerEventListeners();
    }

    protected function publishViews(){
        $this->publishes([
            __DIR__ . '/../views/vue' => resource_path('js/vendor/mfa'),
        ], 'mfa-vue');
        $this->publishes([
            __DIR__ . '/../views/challenge' => resource_path('views/vendor/mfa/challenge'),
            __DIR__ . '/../views/emails' => resource_path('views/vendor/mfa/emails'),
            __DIR__ . '/../views/layouts' => resource_path('views/vendor/mfa/layouts'),
        ], 'mfa-blade');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mfa');
    }
    protected function publishConfig(){
        $this->publishes([
            __DIR__ . '/../config/mfa.php' => config_path('mfa.php'),
        ], 'mfa-config');
    }
    protected function publishMigrations(){
        $migrations = array_merge(
            glob(__DIR__ . '/../resources/migrations/*.php') ?: [],
            glob(__DIR__ . '/../resources/migrations/*.php.stub') ?: []
        );
        $publishable = [];
        $time = time();
        foreach ($migrations as $index => $path) {
            $filename = basename($path);
            // Remove any existing timestamp prefix from package filename (e.g., 2025_12_30_123456_)
            $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $filename);
            // If file ends with .stub (e.g., create_x_table.php.stub), drop the .stub so target ends with .php
            if (substr($name, -5) === '.stub') {
                $name = substr($name, 0, -5);
            }
            // Ensure the name ends with .php
            if (substr($name, -4) !== '.php') {
                $name .= '.php';
            }
            // Skip if migration already exists in the application's migrations
            if (count(glob(database_path("migrations/*_{$name}"))) > 0) {
                continue;
            }
            // Ensure unique timestamp per file by incrementing seconds for each entry
            $timestamp = date('Y_m_d_His', $time + $index);
            $publishable[$path] = database_path("migrations/{$timestamp}_{$name}");
        }
        if (!empty($publishable)) {
            $this->publishes($publishable, 'mfa-migrations');
        }
    }
    protected function registerRoutes(){
        if (config('mfa.routes.enabled', true)) {
            Route::group([
                'prefix' => config('mfa.routes.prefix', 'mfa'),
                'middleware' => config('mfa.routes.middleware', ['web', 'auth']),
                'as' => config('mfa.routes.name_prefix', 'mfa.'),
                'namespace' => 'Mchuluq\LaravelMFA\Http\Controllers',
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }
    }
    protected function registerMiddleware(){
        $router = $this->app->make(Router::class);
        // Register middleware alias
        $router->aliasMiddleware('mfa', RequireMFA::class);
    }
    protected function registerEventListeners(){
        if (config('mfa.events.enabled', true)) {
            $events = $this->app['events'];
            // Register event listeners here
            // Example:
            // $events->listen(MFAEnabled::class, MFAEnabledListener::class);
        }
    }
    public function provides(){
        return ['mfa'];
    }
}