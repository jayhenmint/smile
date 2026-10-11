<?php

namespace App\Providers;

use App\Auth\SchoolUserProvider;
use Illuminate\Support\Facades\Auth;
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
        Auth::provider('school_eloquent', fn ($app, array $config) => new SchoolUserProvider($app['hash'], $config['model']));
    }
}
