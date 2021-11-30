<?php

namespace App\Providers;

use App\Providers\Payment\PaymentManager;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    protected $defer = true;

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('payment', function ($app) {
            return new PaymentManager($app);
        });
    }

    public function provides()
    {
        return ['payment'];
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
