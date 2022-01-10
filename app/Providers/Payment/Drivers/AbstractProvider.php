<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Bundle;
use App\Models\Payment as PaymentModel;
use App\Models\User;
use Illuminate\Http\Request;

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
    abstract function buy(PaymentModel $payment);
    abstract function subscribe(PaymentModel $paymentModel, User $user, Bundle $bundle = null);
    abstract function validate(Request $request);
}
