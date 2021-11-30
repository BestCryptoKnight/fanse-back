<?php

namespace App\Providers\Payment\Drivers;

use App\Providers\Payment\Contracts\Payment as PaymentModel;
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

    public function pay(PaymentModel $payment)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new Amount();
        $amount->setTotal($payment->amount / 100);
        $amount->setCurrency($this->config['misc']['payment']['currency']['code']);

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        $transaction->setInvoiceNumber($payment->hash);

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($this->config['app']['app_url'] . '/payment/success/' . $payment->hash)
            ->setCancelUrl($this->config['app']['app_url'] . '/payment/failure');

        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions(array($transaction))
            ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($this->getApi());
            return $payment->getApprovalLink();
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            Log::error($ex->getData());
        }
    }
}
