<?php

namespace Nextbyte\Courier;

use Illuminate\Support\ServiceProvider;

class CourierServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $config = __DIR__.'/../config/courier.php';

        $this->mergeConfigFrom($config, 'courier');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravelCourier');

        $this->app->singleton('courier', function ($app) {
            return new CourierManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['courier'];
    }
}
