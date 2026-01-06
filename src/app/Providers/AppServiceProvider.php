<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

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
