<?php

namespace App\Providers\Payment;

use App\Providers\Payment\Drivers\PaypalProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class PaymentManager extends Manager
{
    protected $config = [];

    protected function createPaypalDriver()
    {
        $config = $this->container->make('config')['services.paypal'];
        return $this->buildProvider(
            PaypalProvider::class,
            $config
        );
    }

    public function buildProvider($provider, $config)
    {
        $config = [
            'service' => $config,
            'misc' => $this->container->make('config')['misc'],
            'app' => $this->container->make('config')['app'],
        ];
        return new $provider($config);
    }

    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Payment driver was specified.');
    }

    public function getEnabledDrivers()
    {
        $available = ['paypal'];
        $enabled = [];
        foreach ($available as $a) {
            //try {
            $driver = $this->driver($a);
            if ($driver->isEnabled()) {
                $enabled[] = $driver;
            }
            //} catch (\Exception $e) {
            // do nothing
            //}
        }
        return $enabled;
    }
}
