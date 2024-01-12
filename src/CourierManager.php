<?php

namespace Nextbyte\Courier;

use Illuminate\Support\Manager;
use Nextbyte\Courier\Clients\BestExpress\BestExpress;
use Nextbyte\Courier\Clients\DhlEcommerce\DhlEcommerce;
use Nextbyte\Courier\Clients\Gdex\Gdex;
use Nextbyte\Courier\Clients\NationwideExpress\NationwideExpress;
use Nextbyte\Courier\Clients\PosLaju\PosLaju;
use Nextbyte\Courier\Drivers\BestExpress\BestExpressDriver;
use Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver;
use Nextbyte\Courier\Drivers\Gdex\GdexDriver;
use Nextbyte\Courier\Drivers\NationwideExpress\NationwideExpressDriver;
use Nextbyte\Courier\Drivers\Ninjavan\NinjavanDriver;
use Nextbyte\Courier\Drivers\Null\NullDriver;
use Nextbyte\Courier\Drivers\PosLaju\PosLajuDriver;

class CourierManager extends Manager
{
    /**
     * Get a driver instance.
     *
     * @param  string|null  $name
     * @return \Nextbyte\Courier\Contracts\Courier
     */
    public function vendor($name = null)
    {
        return $this->driver($name);
    }

//    /**
//     * Create a Nexmo Courier driver instance.
//     *
//     * @return \Nextbyte\Courier\Drivers\NexmoDriver
//     */
//    public function createNexmoDriver()
//    {
//        return new NexmoDriver(
//            $this->createNexmoClient(),
//            $this->container['config']['courier.nexmo.from']
//        );
//    }

    /**
     * @return NationwideExpressDriver
     */
    public function createNationwideExpressDriver()
    {
        return new NationwideExpressDriver(
            $this->createNationwideExpressClient(),
            new \GuzzleHttp\Client()
        );
    }

    /**
     * Create the NationwideExpress client.
     *
     * @return NationwideExpress
    */
    protected function createNationwideExpressClient()
    {
        return new NationwideExpress();
    }

    /**
     * @return PosLajuDriver
     */
    public function createPosLajuDriver()
    {
        return new PosLajuDriver(
            $this->createPosLajuClient(),
            new \GuzzleHttp\Client()
        );
    }

    /**
     * Create the PosLaju client.
     *
     * @return PosLaju
    */
    protected function createPosLajuClient()
    {
        return new PosLaju();
    }

    /**
     * Create a Null Courier driver instance.
     *
     * @return \Nextbyte\Courier\Drivers\Null\NullDriver
     */
    public function createNullDriver()
    {
        return new NullDriver;
    }

    /**
     * Create a Best Courier driver instance.
     *
     * @return \Nextbyte\Courier\Drivers\BestExpress\BestExpressDriver
     */
    public function createBestExpressDriver()
    {
        return new BestExpressDriver(new BestExpress($this->config->get('courier.best-express')));
    }

    /**
     * Create a Dhl Courier driver instance.
     *
     * @return \Nextbyte\Courier\Drivers\DhlEcommerce\DhlEcommerceDriver
     */
    public function createDhlEcommerceDriver()
    {
        return new DhlEcommerceDriver(new DhlEcommerce($this->config->get('courier.dhl-ecommerce'), null,
            $this->container->get('cache')));
    }

    /**
     * Create a Ninjavan Courier driver instance.
     *
     * @return \Nextbyte\Courier\Drivers\Ninjavan\NinjavanDriver
     */
    public function createNinjavanDriver()
    {
        return new NinjavanDriver();
    }

    /**
     * @return GdexDriver
     */
    public function createGdexDriver()
    {
        return new GdexDriver(new Gdex($this->config->get('courier.gdex')));
    }

    /**
     * Get the default Courier driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->container['config']['courier.default'] ?? 'null';
    }
}
