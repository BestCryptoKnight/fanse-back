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
        $params = [
            'amount' => $paymentModel->amount,
            'currency' => config('misc.payment.currency.code'),
            'metadata' => [
                'hash' => $paymentModel->hash
            ],
        ];
        if ($paymentModel->user->mainPaymentMethod) {
            $params['customer'] = $paymentModel->user->mainPaymentMethod->info['customer']['id'];
            $params['payment_method'] = $paymentModel->user->mainPaymentMethod->info['method']['id'];
            $params['off_session'] = true;
            $params['confirm'] = true;
        } else {
            $customer = \Stripe\Customer::create();
            $params['customer'] = $customer->id;
            $params['setup_future_usage'] = 'off_session';
        }

        $intent = $this->getApi()->paymentIntents->create($params);
        if ($intent->status == 'succeeded') {
            $paymentModel->status = PaymentModel::STATUS_COMPLETE;
            $paymentModel->token = $intent->id;
            $paymentModel->save();
            return ['info' => true];
        }

        return [
            'token' => $intent->client_secret
        ];
    }

    function subscribe(Request $request, PaymentModel $paymentModel, User $user, Bundle $bundle = null)
    {
        $intent = null;
        $customer = null;
        if ($bundle) {
            // Pay once for discounted months
            $paramsForBundlePay = [
                'amount' => $paymentModel->amount,
                'currency' => config('misc.payment.currency.code'),
                'metadata' => [
                    'hash' => $paymentModel->hash
                ],
            ];
            if ($paymentModel->user->mainPaymentMethod) {
                $paramsForBundlePay['customer'] = $paymentModel->user->mainPaymentMethod->info['customer']['id'];
                $paramsForBundlePay['payment_method'] = $paymentModel->user->mainPaymentMethod->info['method']['id'];
                $paramsForBundlePay['off_session'] = true;
                $paramsForBundlePay['confirm'] = true;
            } else {
                $customer = \Stripe\Customer::create();
                $paramsForBundlePay['customer'] = $customer->id;
                $paramsForBundlePay['setup_future_usage'] = 'off_session';
            }

            $intent = $this->getApi()->paymentIntents->create($paramsForBundlePay);
        }
        
        $subscriptionAmountPerMonth = round($bundle
            ? ($paymentModel->amount / $bundle->months) / (1 - $bundle->discount / 100)
            : $paymentModel->amount);
        // find product or create one
        $product_id = 'prod_sub_' . $user->id;
        try {
            $product = $this->getApi()->products->retrieve($product_id);
        } catch (\Exception $e) {
            $product = $this->getApi()->products->create([
                'id' => $product_id,
                'name' => 'Subscription to User #' . $user->id
            ]);
        }
        // find price or create one
        $prices = $this->getApi()->prices->all(['product' => $product_id]);
        $price = null;
        foreach ($prices as $p) {
            if (
                $p->recurring->interval == 'month'
                && $p->recurring->interval_count == 1
                && $p->unit_amount == $subscriptionAmountPerMonth
            ) {
                $price = $p;
                break;
            }
        }
        if (!$price) {
            $price = $this->getApi()->prices->create([
                'product' => $product_id,
                'unit_amount' => $subscriptionAmountPerMonth,
                'currency' => config('misc.payment.currency.code'),
                'recurring' => [
                    'interval' => 'month',
                    'interval_count' => 1
                ]
            ]);
        }

        // create subscription
        $params = [
            'items' => [[
                'price' => $price->id,
                'metadata' => [
                    'hash' => $paymentModel->hash
                ],
            ]],
        ];
        if ($paymentModel->user->mainPaymentMethod) {
            $params['customer'] = $paymentModel->user->mainPaymentMethod->info['customer']['id'];
            $params['default_payment_method'] = $paymentModel->user->mainPaymentMethod->info['method']['id'];
            $params['off_session'] = true;
        } else {
            if($customer == null) {
                $customer = \Stripe\Customer::create();
            }
            $params['customer'] = $customer->id;
            $params['payment_behavior'] = 'default_incomplete';
            $params['expand'] = ['latest_invoice.payment_intent'];
        }
        if ($bundle) {
            $params['trial_end'] = Carbon::now()->addMonths($bundle->months)->getTimestamp();
            // $params['trial_period_days'] = 90;
        }
    
        $subscription = $this->getApi()->subscriptions->create($params);
        $info = $paymentModel->info;
        $paymentModel->info = [
            'sub_id' => $info['sub_id'],
            'subscription_id' => $subscription->id
        ];
        if ($subscription->status == 'active') {
            $paymentModel->status = PaymentModel::STATUS_COMPLETE;
            $paymentModel->token = $subscription->id;
            
            $paymentModel->save();
            return ['info' => true];
        } else if ($subscription->status == 'trialing') {
            if ($paymentModel->user->mainPaymentMethod) {
                $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                $paymentModel->token = $subscription->id; 
                $paymentModel->save();
                return ['info' => true];
            } else {
                $paymentModel->save();
                return ['token' => $intent->client_secret];
            }
        }  
        else {
            $paymentModel->save();
        }

        return $intent ? [
            'token' => $intent->client_secret
        ] : [
            'token' => $subscription->latest_invoice->payment_intent->client_secret
        ];
    }

    public function unsubscribe(Subscription $subscription)
    {
        try {
            $this->getApi()->subscriptions->update($subscription->info['subscription_id'], [
                'cancel_at_period_end' => true,
            ]);
            return true;
        } catch (\Exception $e) {
        }
        return false;
    }

    public function resubscribe(Subscription $subscription)
    {
        try {
            $this->getApi()->subscriptions->update($subscription->info['subscription_id'], [
                'cancel_at_period_end' => false,
            ]);
            return true;
        } catch (\Exception $e) {
        }
        return false;
    }

    public function validate(Request $request)
    {
        // subscription renew
        if (isset($request['object']) && $request['object'] == 'event') {
            return $this->validateRenewSubscription($request);
        }
        // other payments
        $intent = $this->getApi()->paymentIntents->retrieve($request['payment_intent']);
        if ($intent->status == 'succeeded') {
            $payment_method_id = $intent->payment_method;
            $method = $this->getApi()->paymentMethods->retrieve($payment_method_id);
            if ($intent->metadata->hash) {
                $paymentModel = PaymentModel::where('hash', $intent->metadata->hash)->first();
            } else {
                $invoice = $this->getApi()->invoices->retrieve($intent->invoice);
                $subscriptionItem = $this->getApi()->subscriptionItems->retrieve($invoice->lines->data[0]->subscription_item);
                $paymentModel = PaymentModel::where('hash', $subscriptionItem->metadata->hash)->first();
            }
            if ($paymentModel) {
                $paymentModel->status = PaymentModel::STATUS_COMPLETE;
                $paymentModel->token = $intent->id;
                $paymentModel->save();
            }
            return [
                'payment' => $paymentModel,
                'title' => '****' . $method->card->last4,
                'info' => [
                    'customer' => ['id' => $intent->customer],
                    'method' => ['id' => $payment_method_id]
                ]
            ];
        }

        return false;
    }

    private function validateRenewSubscription(Request $request)
    {
        $invoice = $this->getApi()->invoices->retrieve($request['data']['object']['invoice']);
        $subscription_id = $invoice->subscription;
        if ($subscription_id) {
            $firstPaymentModel = PaymentModel::where('token', $subscription_id)->first();
            if ($firstPaymentModel) {
                $subscription = $this->getApi()->subscriptions->retrieve($subscription_id);
                $existing = PaymentModel::where(
                    'hash',
                    $firstPaymentModel->hash
                        . '..'
                        . $subscription->current_period_end
                )->first();
                if ($existing) {
                    return $existing;
                }
                $info = $firstPaymentModel->info;
                $info['expire'] = Carbon::createFromTimestamp($subscription->current_period_end)->toIso8601String();
                $newPaymentModel = PaymentModel::create([
                    'hash' => $firstPaymentModel->hash . '..' . $subscription->current_period_end,
                    'user_id' => $firstPaymentModel->user_id,
                    'to_id' => $firstPaymentModel->to_id,
                    'type' => PaymentModel::TYPE_SUBSCRIPTION_RENEW,
                    'info' => $info,
                    'amount' => $firstPaymentModel->amount,
                    'gateway' => $this->getId(),
                    'token' => $subscription_id,
                    'status' => PaymentModel::STATUS_COMPLETE,
                ]);
                return $newPaymentModel;
            }
        }
        return false;
    }

    public function export(Payout $payout, $handler)
    {
    }
}
