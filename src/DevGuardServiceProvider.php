<?php

namespace DevGuard\Laravel;

use DevGuard\Laravel\Commands\DevGuardLockCommand;
use DevGuard\Laravel\Commands\DevGuardStatusCommand;
use DevGuard\Laravel\Http\Middleware\DevGuardMiddleware;
use DevGuard\WebGuard;
use Illuminate\Support\ServiceProvider;

/**
 * DevGuard Service Provider for Laravel.
 *
 * Provides package auto-discovery, configuration registration,
 * HTTP middleware routing, and Artisan administration commands.
 */
class DevGuardServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge package configuration with application configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/devguard.php',
            'devguard'
        );

        // Bind WebGuard instance as a singleton in the service container
        $this->app->singleton(WebGuard::class, function ($app) {
            $config = $app['config']->get('devguard', []);

            $failSafeVal = $config['fail_safe'] ?? 'open';
            $failSafe = ($failSafeVal === false || $failSafeVal === 'closed') ? 'closed' : 'open';

            $authKey = !empty($config['sdk_key'])
                ? (string) $config['sdk_key']
                : (!empty($config['api_key']) ? (string) $config['api_key'] : null);

            return new WebGuard([
                'projectId' => (string) ($config['project_id'] ?? ''),
                'apiUrl' => (string) ($config['api_url'] ?? 'https://api.devguard.uk'),
                'sdkKey' => !empty($config['sdk_key']) ? (string) $config['sdk_key'] : null,
                'apiKey' => $authKey,
                'failSafe' => $failSafe,
                'allowedDomains' => $config['allowed_domains'] ?? null,
                'allowedPaths' => (array) ($config['allowed_paths'] ?? []),
                'cacheTtl' => (int) ($config['cache_ttl'] ?? 300),
                'trustProxy' => (bool) ($config['trust_proxy'] ?? false),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration file in console mode
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/devguard.php' => function_exists('config_path')
                    ? config_path('devguard.php')
                    : base_path('config/devguard.php'),
            ], 'devguard-config');

            $this->commands([
                DevGuardStatusCommand::class,
                DevGuardLockCommand::class,
            ]);
        }

        // Register router middleware alias 'devguard'
        $this->registerMiddlewareAlias();
    }

    /**
     * Registers the middleware alias compatible with Laravel 8, 9, 10, and 11.
     *
     * @return void
     */
    protected function registerMiddlewareAlias(): void
    {
        $router = $this->app->make('router');

        if (method_exists($router, 'aliasMiddleware')) {
            // Laravel 5.4+ / 8 / 9 / 10 / 11
            $router->aliasMiddleware('devguard', DevGuardMiddleware::class);
        } elseif (method_exists($router, 'middleware')) {
            $router->middleware('devguard', DevGuardMiddleware::class);
        }
    }
}
