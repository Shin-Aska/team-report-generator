<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

/**
 * Register application-wide container and runtime configuration.
 *
 * No custom bindings or boot hooks are currently required; the methods remain explicit extension points.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $raw = config('trust_proxies.proxies');

        $proxies = is_array($raw)
            ? $raw
            : array_values(array_filter(array_map('trim', explode(',', (string) $raw))));

        TrustProxies::at($proxies);
        TrustProxies::withHeaders((int) config('trust_proxies.headers'));
    }
}
