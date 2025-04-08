<?php

namespace App\Providers;

use App\Models\Magento;
use App\Observers\MagentoObserver;
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
        Magento::observe(MagentoObserver::class);
    }
}
