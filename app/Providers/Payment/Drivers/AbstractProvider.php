<?php

namespace App\Providers\Payment\Drivers;

abstract class AbstractProvider
{
    protected $config = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function isEnabled()
    {
        return !isset($this->config['enabled']) || $this->config['enabled'];
    }

    abstract function getName();
    abstract function getId();
}
