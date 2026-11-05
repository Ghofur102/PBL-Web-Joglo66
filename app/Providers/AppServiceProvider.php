<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        if (app()->environment('production') || request()->header('x-forwarded-proto') === 'https' || str_contains(request()->getHost(), 'poliwangi.ac.id') || str_contains(request()->getHost(), 'ngrok')) {
            URL::forceScheme('https');
        }

        if (app()->environment('production') || str_contains(request()->getHost(), 'poliwangi.ac.id')) {
            URL::forceRootUrl(config('app.url'));
        }
    }
}
