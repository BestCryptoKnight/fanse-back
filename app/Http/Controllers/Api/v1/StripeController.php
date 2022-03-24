<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Payment as PaymentGateway;

class StripeController extends Controller
{
    public function intent()
    {
        $driver = PaymentGateway::driver('stripe');
        return $driver->intent();
    }
}
