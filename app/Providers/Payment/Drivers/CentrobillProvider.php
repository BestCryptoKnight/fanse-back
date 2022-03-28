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

class CentrobillProvider extends AbstractProvider
{
    private $url = 'https://api.centrobill.com';

    public function getName()
    {
        return 'CentroBill';
    }

    public function getId()
    {
        return 'centrobill';
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

    public function attach(Request $request, User $user)
    {
        try {
            $client = new Client();

            $response = $client->request('POST', $this->url . '/payment', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => [
                    'paymentSource' => [
                        'type' => 'token',
                        'value' => $request['token'],
                        '3ds' => false
                    ],
                    'sku' => [
                        'title' => 'Initial Payment',
                        'siteId' => $this->config['service']['site_id'],
                        'price' => [
                            [
                                'offset' => '0d',
                                'amount' => 1.00,
                                'currency' => config('misc.payment.currency.code'),
                                'repeat' => false
                            ]
                        ],
                    ],
                    'consumer' => [
                        'ip' => config('app.debug') ? '178.140.173.99' : $request->ip(),
                        'email' => $user->email,
                        'externalId' => strlen($user->id . '') < 3 ? '00' . $user->id : $user->id
                    ]
                ]
            ]);

            $json = json_decode($response->getBody(), true);
            if ($this->ccVerify($json)) {
                $profile = [
                    'consumer' => [
                        'id' => $json['consumer']['id']
                    ]
                ];
                $this->ccRefund($json['payment']);
                return $profile;
            }
            return null;
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function ccRefund(array $data)
    {
        try {
            $client = new Client();

            $response = $client->request('POST', $this->url . '/payment/' . $data['transactionId'] . '/credit', [
                'headers' => [
                    'Authorization' => $this->config['service']['api_key']
                ],
                'json' => [
                    'amount' => 1.00,
                    'reason' => 'Initial Payment Refund'
                ]
            ]);

            $json = json_decode($response->getBody(), true);
            if ($this->ccVerify($json)) {
                if ($json['payment']['mode'] == 'refund') {
                    return true;
                }
            }

            return false;
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    private function ccVerify($data)
    {
        return $data['payment']['code'] == 0
            && (config('app.debug') || $data['payment']['mode'] != 'test')
            && $data['payment']['status'] == 'success';
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
                $newPaymentModel = PaymentModel::create([
                    'hash' => $firstPaymentModel->hash . '..' . $request['subscription']['cycle'],
                    'user_id' => $firstPaymentModel->user_id,
                    'to_id' => $firstPaymentModel->to_id,
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
