<?php

namespace App\Providers\Payment\Drivers;

use App\Models\Payment as PaymentModel;
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

use Log;

class PaypalProvider extends AbstractProvider
{
    protected $api;

    protected $name = 'PayPal';

    public function getName()
    {
        return 'PayPal';
    }

    public function getId()
    {
        return 'paypal';
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

    public function pay(PaymentModel $paymentModel)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new Amount();
        $amount->setTotal($paymentModel->amount / 100);
        $amount->setCurrency($paymentModel->currency);

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

    public function validate(Request $request)
    {
        if (isset($request['paymentId']) && isset($request['PayerID'])) {
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
        }
        return false;
    }
}
