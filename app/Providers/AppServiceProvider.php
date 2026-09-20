<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

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
        Paginator::useBootstrapFive();
        \Illuminate\Support\Facades\RateLimiter::for('guidance-strikes', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(90)->by($request->session()->getId()));
        \Illuminate\Support\Facades\RateLimiter::for('guidance-exam', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(180)->by($request->session()->getId().':'.hash('sha256', (string) $request->route('token'))));
        \Illuminate\Support\Facades\RateLimiter::for('guidance-identity', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('session:'.$request->session()->getId()),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(600)->by('ip:'.$request->ip()),
        ]);
    }
}
