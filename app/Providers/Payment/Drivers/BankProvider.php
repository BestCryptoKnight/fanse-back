<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Bundle;
use App\Models\Payment as PaymentModel;
use App\Models\Payout;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class BankProvider extends AbstractProvider
{
    public function getName()
    {
        return 'Bank';
    }

    public function getId()
    {
        return 'bank';
    }

    public function isCC()
    {
        return false;
    }

    public function forPayment()
    {
        return false;
    }

    public function forPayout()
    {
        return true;
    }

    public function attach(Request $request, User $user)
    {
    }

    public function buy(Request $request, PaymentModel $paymentModel)
    {
    }

    function subscribe(Request $request, PaymentModel $paymentModel, User $user, Bundle $bundle = null)
    {
    }

    public function unsubscribe(Subscription $subscription)
    {
    }

    public function validate(Request $request)
    {
    }

    public function export(Payout $payout, $handler)
    {
        fputcsv($handler, [
            $payout->user->verification->info['first_name'],
            $payout->user->verification->info['last_name'],
            $payout->info['info']['name'],
            $payout->user->verification->country,
            $payout->info['info']['address'],
            $payout->info['info']['swift'],
            $payout->info['info']['account'],
        ]);
    }
}
