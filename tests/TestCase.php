<?php

namespace Nextbyte\Tests\Courier;

use Nextbyte\Courier\CourierManager;
use Nextbyte\Courier\CourierServiceProvider;
use Nextbyte\Courier\Facades\Courier;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected $loadEnvironmentVariables = true;

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            CourierServiceProvider::class,
        ];
    }

    /**
     * @param string $driver
     * @return \Nextbyte\Courier\Contracts\Courier
     */
    protected function makeDriver($driver)
    {
        return Courier::vendor($driver);
    }

//    protected function getEnvironmentSetUp($app)
//    {
//        Cashier::useCustomerModel(User::class);
//    }
//
//    protected function getPackageProviders($app)
//    {
//        return [CashierServiceProvider::class];
//    }
}
