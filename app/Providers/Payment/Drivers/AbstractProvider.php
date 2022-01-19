<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Bundle;
use App\Models\Payment as PaymentModel;
use App\Models\Subscription;
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

    abstract function isCC();
    abstract function getName();
    abstract function getId();
    abstract function attach(Request $request, User $user);
    abstract function buy(Request $request, PaymentModel $payment);
    abstract function subscribe(Request $request, PaymentModel $paymentModel, User $user, Bundle $bundle = null);
    abstract function unsubscribe(Subscription $subscription);
    abstract function validate(Request $request);
}
