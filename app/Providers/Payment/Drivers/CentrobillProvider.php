<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Bundle;
use App\Models\Payment as PaymentModel;
use App\Models\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

use Log;

class CentrobillProvider extends AbstractProvider
{
    protected $api;

    protected $name = 'CentroBill';

    public function getName()
    {
        return 'CentroBill';
    }

    public function getId()
    {
        return 'centrobill';
    }

    public function buy(PaymentModel $paymentModel)
    {
        $client = new Client();
        $payload = [
            'sku' => [
                'title' => $paymentModel->type == PaymentModel::TYPE_POST ? __('app.unlock-post') : __('app.unlock-message'),
                'siteId' => $this->config['service']['site_id'],
                'price' => [
                    [
                        'offset' => '0d',
                        'amount' => $paymentModel->amount / 100,
                        'currency' => config('misc.payment.currency.code'),
                        'repeat' => false
                    ]
                ],
                'url' => [
                    'redirectUrl' => $this->config['app']['app_url'] . '/payment/success/centrobill',
                    'ipnUrl' => url('/process/centrobill')
                ]
            ],
            'metadata' => [
                'hash' => $paymentModel->hash
            ]
        ];

        try {
            $response = $client->request('POST', 'https://api.centrobill.com/paymentPage', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => $payload
            ]);
            $json = json_decode($response->getBody());
            return ['redirect' => $json['url']];
        } catch (\Exception $ex) {
            Log::error($ex->getData());
        }
    }

    function subscribe(PaymentModel $paymentModel, User $user, Bundle $bundle = null)
    {
        $client = new Client();
        $payload = [
            'sku' => [
                'title' => __('app.subscription-to-x', [
                    'site' => config('app.name'),
                    'user' => $user->username,
                    'months' => $bundle ? $bundle->months : 1
                ]),
                'siteId' => $this->config['service']['site_id'],
                'price' => [
                    [
                        'offset' => ($bundle ? $bundle->months : 1) . 'm',
                        'amount' => $paymentModel->amount / 100,
                        'currency' => config('misc.payment.currency.code'),
                        'repeat' => true
                    ]
                ],
                'url' => [
                    'redirectUrl' => $this->config['app']['app_url'] . '/payment/success/centrobill',
                    'ipnUrl' => url('/process/centrobill')
                ]
            ],
            'metadata' => [
                'hash' => $paymentModel->hash
            ]
        ];

        try {
            $response = $client->request('POST', 'https://api.centrobill.com/paymentPage', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => $payload
            ]);
            $json = json_decode($response->getBody());
            return ['redirect' => $json['url']];
        } catch (\Exception $ex) {
            Log::error($ex->getData());
        }
    }

    public function validate(Request $request)
    {
        if (isset($request['metadata']['hash'])) {
            $payment = PaymentModel::where('hash', $request['metadata']['hash'])->first();
            if ($payment) {
                switch ($payment->type) {
                    case PaymentModel::TYPE_MESSAGE:
                    case PaymentModel::TYPE_POST:
                    case PaymentModel::TYPE_SUBSCRIPTION_NEW:
                        return $this->validatePayment($request, $payment);
                    case PaymentModel::TYPE_SUBSCRIPTION_RENEW:
                        return $this->validateRenewSubscription($request, $payment);
                }
            }
        }
        return false;
    }

    private function validatePayment(Request $request, PaymentModel $paymentModel)
    {
        try {
            if ($request['payment']['code'] * 1 == 0 && $request['payment']['mode'] == 'sale' && $request['payment']['status'] == 'success') {
                $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                $paymentModel->token = $request['payment']['transactionId'];
                $paymentModel->save();
                return $paymentModel;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return false;
    }

    private function validateRenewSubscription(Request $request)
    {
        return false;
    }
}
