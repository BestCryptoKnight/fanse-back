<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Bundle;
use App\Models\Payment as PaymentModel;
use App\Models\Payout;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Agreement;
use PayPal\Api\Payer;
use PayPal\Api\Plan;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\PayerInfo;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;

use PayPal\Api\ChargeModel;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Common\PayPalModel;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\ShippingAddress;
use PayPal\Api\Currency;

use Log;

class PaypalProvider extends AbstractProvider
{
    protected $api;

    public function getName()
    {
        return 'PayPal';
    }

    public function getId()
    {
        return 'paypal';
    }

    public function isCC()
    {
        return false;
    }

    public function forPayment()
    {
        return true;
    }

    public function forPayout()
    {
        return true;
    }

    public function getApi()
    {
        if (!$this->api) {
            $this->api =
                new ApiContext(
                    new OAuthTokenCredential(
                        $this->config['service']['client_id'],
                        $this->config['service']['secret']
                    )
                );
        }
        return $this->api;
    }

    public function attach(Request $request, User $user)
    {
        return [];
    }

    public function buy(Request $request, PaymentModel $paymentModel)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new Amount();
        $amount->setTotal($paymentModel->amount / 100);
        $amount->setCurrency(config('misc.payment.currency.code'));

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        $transaction->setInvoiceNumber($paymentModel->hash);

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($this->config['app']['app_url'] . '/payment/success/paypal')
            ->setCancelUrl($this->config['app']['app_url'] . '/payment/failure');

        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($this->getApi());
            return ['redirect' => $payment->getApprovalLink()];
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            Log::error($ex->getData());
        }
    }

    function subscribe(Request $request, PaymentModel $paymentModel, User $user, Bundle $bundle = null)
    {
        $plan = new Plan();

        $plan->setName(
            __('app.subscription-to-x', [
                'site' => config('app.name'),
                'user' => $user->username,
                'months' => $bundle ? $bundle->months : 1
            ])
        )
            ->setDescription(__('app.subscription-info'))
            ->setType('infinite');

        $paymentDefinition = new PaymentDefinition();

        $paymentDefinition->setName('Regular Payments')
            ->setType('REGULAR')
            ->setFrequency('Month')
            ->setFrequencyInterval($bundle ? $bundle->months : 1)
            ->setAmount(new Currency(array('value' => ($paymentModel->amount / 100), 'currency' => config('misc.payment.currency.code'))));

        $merchantPreferences = new MerchantPreferences();

        $merchantPreferences->setReturnUrl($this->config['app']['app_url'] . '/payment/success/paypal')
            ->setCancelUrl($this->config['app']['app_url'] . '/payment/failure')
            ->setAutoBillAmount("yes")
            ->setInitialFailAmountAction("CONTINUE")
            ->setMaxFailAttempts("0");

        $plan->setPaymentDefinitions([$paymentDefinition]);
        $plan->setMerchantPreferences($merchantPreferences);

        try {
            $cPlan = $plan->create($this->getApi());

            $patch = new Patch();

            $value = new PayPalModel('{"state":"ACTIVE"}');
            $patch->setOp('replace')
                ->setPath('/')
                ->setValue($value);

            $patchRequest = new PatchRequest();
            $patchRequest->addPatch($patch);

            $cPlan->update($patchRequest, $this->getApi());
            $cPlan = Plan::get($cPlan->getId(), $this->getApi());

            $agreement = new Agreement();

            $agreement->setName($paymentModel->hash)
                ->setDescription($paymentModel->hash)
                ->setStartDate(Carbon::now()->addMinute(1)->toIso8601String());

            $plan = new Plan();

            $plan->setId($cPlan->getId());
            $agreement->setPlan($plan);

            $payer = new Payer();
            $payer->setPaymentMethod('paypal');
            $agreement->setPayer($payer);

            $agreement = $agreement->create($this->getApi());

            return ['redirect' => $agreement->getApprovalLink()];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function unsubscribe(Subscription $subscription)
    {
        return true;
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
                    $newPaymentModel = PaymentModel::create([
                        'hash' => $firstPaymentModel->hash . '..' . $request['resource']['agreement_details']['cycles_completed'],
                        'user_id' => $firstPaymentModel->user_id,
                        'to_id' => $firstPaymentModel->to_id,
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

    public function export(Payout $payout, $handler)
    {
        fputcsv($handler, [
            $payout->info['info']['paypal'],
            $payout->amount / 100,
            config('misc.payment.currency.code')
        ]);
    }
}
