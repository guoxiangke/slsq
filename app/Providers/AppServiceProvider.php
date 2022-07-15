<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Observers\CustomerObserver;
use App\Observers\OrderObserver;
use App\Services\Xbot;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Xbot::class, function() {
            return new Xbot();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment() !== 'local') {
            URL::forceScheme('https');
        }
        Customer::observe(CustomerObserver::class);
        Order::observe(OrderObserver::class);
    }
}
