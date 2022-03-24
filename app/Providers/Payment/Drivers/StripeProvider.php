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

use Log;

class StripeProvider extends AbstractProvider
{
    protected $api;

    public function getName()
    {
        return 'Stripe';
    }

    public function getId()
    {
        return 'stripe';
    }

    public function isCC()
    {
        return true;
    }

    public function forPayment()
    {
        return true;
    }

    public function forPayout()
    {
        return false;
    }

    public function __construct($config)
    {
        parent::__construct($config);
        \Stripe\Stripe::setApiKey($this->config['service']['secret_key']);
    }

    public function getApi()
    {
        if (!$this->api) {
            $this->api = new \Stripe\StripeClient($this->config['service']['secret_key']);
        }
        return $this->api;
    }

    public function intent()
    {
        $customer = \Stripe\Customer::create();
        $intent = $this->getApi()->setupIntents->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
        ]);
        return [
            'customer' => ['id' => $customer->id],
            'token' => $intent->client_secret
        ];
    }

    public function attach(Request $request, User $user)
    {
        $intent = $this->getApi()->setupIntents->retrieve($request['setup_intent']);
        $payment_method_id = $intent->payment_method;
        $method = $this->getApi()->paymentMethods->retrieve($payment_method_id);
        return [
            'title' => '****' . $method->card->last4,
            'customer' => ['id' => $intent->customer],
            'method' => ['id' => $payment_method_id]
        ];
    }

    public function buy(Request $request, PaymentModel $paymentModel)
    {
        $client = new Client();

        $source = ['3ds' => false];
        $consumer = [
            'ip' => config('app.debug') ? '178.140.173.99' : $request->ip()
        ];

        if ($paymentModel->user->mainPaymentMethod) {
            $source['type'] = 'consumer';
            $source['value'] = $paymentModel->user->mainPaymentMethod->info['consumer']['id'];
            $consumer['id'] = $paymentModel->user->mainPaymentMethod->info['consumer']['id'];
        } else {
            $source['type'] = 'token';
            $source['value'] = $request['token'];
            $consumer['email'] = $paymentModel->user->email;
            $consumer['externalId'] = strlen($paymentModel->user->id . '') < 3
                ? '00' . $paymentModel->user->id : $paymentModel->user->id;
        }

        $title = '';
        switch ($paymentModel->type) {
            case PaymentModel::TYPE_MESSAGE:
                $title = __('app.unlock-message');
                break;
            case PaymentModel::TYPE_POST:
                $title = __('app.unlock-post');
                break;
            case PaymentModel::TYPE_TIP:
                $title = __('app.tip');
                break;
        }

        $payload = [
            'paymentSource' => $source,
            'sku' => [
                'title' => $title,
                'siteId' => $this->config['service']['site_id'],
                'price' => [
                    [
                        'offset' => '0d',
                        'amount' => $paymentModel->amount / 100,
                        'currency' => config('misc.payment.currency.code'),
                        'repeat' => false
                    ]
                ],
            ],
            'metadata' => [
                'hash' => $paymentModel->hash
            ],
            'consumer' => $consumer
        ];

        try {
            $response = $client->request('POST', $this->url . '/payment', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => $payload
            ]);
            $json = json_decode($response->getBody(), true);
            if ($this->ccVerify($json)) {
                $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                $paymentModel->token = $json['payment']['transactionId'];
                $paymentModel->save();
                return [
                    'info' => [
                        'consumer' => ['id' => $json['consumer']['id']]
                    ]
                ];
            }
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    function subscribe(Request $request, PaymentModel $paymentModel, User $user, Bundle $bundle = null)
    {
        $client = new Client();

        $source = ['3ds' => false];
        $consumer = [
            'ip' => config('app.debug') ? '178.140.173.99' : $request->ip()
        ];

        if ($paymentModel->user->mainPaymentMethod) {
            $source['type'] = 'consumer';
            $source['value'] = $paymentModel->user->mainPaymentMethod->info['consumer']['id'];
            $consumer['id'] = $paymentModel->user->mainPaymentMethod->info['consumer']['id'];
        } else {
            $source['type'] = 'token';
            $source['value'] = $request['token'];
            $consumer['email'] = $paymentModel->user->email;
            $consumer['externalId'] = strlen($paymentModel->user->id . '') < 3
                ? '00' . $paymentModel->user->id : $paymentModel->user->id;
        }

        $payload = [
            'paymentSource' => $source,
            'sku' => [
                'title' => __('app.subscription-to-x', [
                    'site' => config('app.name'),
                    'user' => $user->username,
                    'months' => $bundle ? $bundle->months : 1
                ]),
                'siteId' => $this->config['service']['site_id'],
                'price' => [
                    [
                        'offset' => '0d',
                        'amount' => $paymentModel->amount / 100,
                        'currency' => config('misc.payment.currency.code'),
                        'repeat' => false
                    ],
                    [
                        'offset' => ($bundle ? $bundle->months : 1) * 30 . 'd',
                        'amount' => $paymentModel->amount / 100,
                        'currency' => config('misc.payment.currency.code'),
                        'repeat' => true
                    ]
                ],
                'url' => [
                    'ipnUrl' => url('/latest/process/centrobill')
                ]
            ],
            'consumer' => $consumer,
            'metadata' => [
                'hash' => $paymentModel->hash
            ]
        ];

        try {
            $response = $client->request('POST', $this->url . '/payment', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => $payload
            ]);
            $json = json_decode($response->getBody(), true);
            if ($this->ccVerify($json)) {
                $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                $paymentModel->token = $json['subscription']['id'];
                $paymentModel->save();

                return [
                    'info' => [
                        'consumer' => ['id' => $json['consumer']['id']]
                    ]
                ];
            }
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function unsubscribe(Subscription $subscription)
    {
        $client = new Client();
        try {
            $response = $client->request('PUT', $this->url . '/subscription/' . $subscription->token . '/cancel', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ]
            ]);
            $json = json_decode($response->getBody(), true);
            if ($json['status'] == 'canceled') {
                return true;
            }
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
        return false;
    }

    public function validate(Request $request)
    {
        if ($this->ccVerify($request)) {
            if (isset($request['subscription']['cycle']) && $request['subscription']['cycle'] > 0) {
                return $this->validateRenewSubscription($request);
            }
        }
        return false;
    }

    private function validateRenewSubscription(Request $request)
    {
        try {
            $existing = PaymentModel::where(
                'hash',
                $request['metadata']['hash']
                    . '..'
                    . $request['subscription']['cycle']
            )->first();
            if ($existing) {
                return $existing;
            }
            $firstPaymentModel = PaymentModel::where('hash', $request['metadata']['hash'])->first();
            if ($firstPaymentModel) {
                $info = $firstPaymentModel->info;
                $info['expire'] = $request['subscription']['renewalDate'];
                $newPaymentModel = Payment::create([
                    'hash' => $firstPaymentModel->hash . '..' . $request['subscription']['cycle'],
                    'user_id' => $firstPaymentModel->user_id,
                    'type' => PaymentModel::TYPE_SUBSCRIPTION_RENEW,
                    'info' => $info,
                    'amount' => $firstPaymentModel->amount,
                    'gateway' => $this->getId(),
                    'token' => $request['subscription']['id'],
                    'status' => PaymentModel::STATUS_COMPLETE,
                ]);
                return $newPaymentModel;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return false;
    }

    public function export(Payout $payout, $handler)
    {
    }
}
