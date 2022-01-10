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
        if (isset($request['paymentId']) && isset($request['PayerID'])) {
            return $this->validatePayment($request);
        } else if (isset($request['token'])) {
            return $this->validateSubscription($request);
        } else if (isset($request['event_type']) && $request['event_type'] == 'BILLING.SUBSCRIPTION.RENEWED') {
            return $this->validateRenewSubscription($request);
        }
        return false;
    }

    private function validatePayment(Request $request)
    {
        try {
            $payment = Payment::get($request['paymentId'], $this->getApi());
            $execution = new PaymentExecution();
            $execution->setPayerId($request->PayerID);
            $payment = $payment->execute($execution, $this->getApi());
            if ($payment->getState() == 'approved') {
                $paymentModel = PaymentModel::where('hash', $payment->transactions[0]->getInvoiceNumber())->first();
                if ($paymentModel) {
                    $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                    $paymentModel->token = $payment->getId();
                    $paymentModel->save();
                    return $paymentModel;
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return false;
    }

    private function validateSubscription(Request $request)
    {
        try {
            $agreement = new Agreement();
            $agreement->execute($request['token'], $this->getApi());

            $agreement = Agreement::get($agreement->getId(), $this->getApi());

            if ($agreement->getState() == 'Active') {
                $paymentModel = PaymentModel::where('hash', $agreement->getDescription())->first();
                if ($paymentModel) {
                    $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                    $paymentModel->token = $agreement->getId();
                    $paymentModel->save();
                    return $paymentModel;
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return false;
    }

    private function validateRenewSubscription(Request $request)
    {
        try {
            $agreement = Agreement::get($request['resource']['id'], $this->getApi());

            if ($agreement->getState() == 'Active') {
                $existing = PaymentModel::where(
                    'hash',
                    $request['resource']['description']
                        . '..'
                        . $request['resource']['agreement_details']['cycles_completed']
                )->first();
                if ($existing) {
                    return $existing;
                }
                $firstPaymentModel = PaymentModel::where('hash', $request['resource']['description'])->first();
                if ($firstPaymentModel) {
                    $info = $firstPaymentModel->info;
                    $info['expire'] = $request['resource']['agreement_details']['next_billing_date'];
                    $newPaymentModel = Payment::create([
                        'hash' => $firstPaymentModel->hash . '..' . $request['resource']['agreement_details']['cycles_completed'],
                        'user_id' => $firstPaymentModel->user_id,
                        'type' => PaymentModel::TYPE_SUBSCRIPTION_RENEW,
                        'info' => $info,
                        'amount' => $firstPaymentModel->amount,
                        'gateway' => $this->getId(),
                        'token' => $agreement->getId(),
                        'status' => PaymentModel::STATUS_COMPLETE,
                    ]);
                    return $newPaymentModel;
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return false;
    }
}
