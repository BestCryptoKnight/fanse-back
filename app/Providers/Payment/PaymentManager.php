<?php

namespace App\Providers\Payment;

use App\Models\Message;
use App\Models\Payment;
use App\Models\Post;
use App\Models\User;
use App\Providers\Payment\Drivers\PaypalProvider;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class PaymentManager extends Manager
{
    protected $config = [];

    protected function createPaypalDriver()
    {
        $config = $this->container->make('config')['services.paypal'];
        return $this->buildProvider(
            PaypalProvider::class,
            $config
        );
    }

    public function buildProvider($provider, $config)
    {
        $config = [
            'service' => $config,
            'misc' => $this->container->make('config')['misc'],
            'app' => $this->container->make('config')['app'],
        ];
        return new $provider($config);
    }

    public function getDefaultDriver()
    {
        throw new InvalidArgumentException('No Payment driver was specified.');
    }

    public function getEnabledDrivers()
    {
        $available = ['paypal'];
        $enabled = [];
        foreach ($available as $a) {
            //try {
            $driver = $this->driver($a);
            if ($driver->isEnabled()) {
                $enabled[] = $driver;
            }
            //} catch (\Exception $e) {
            // do nothing
            //}
        }
        return $enabled;
    }

    public function processPayment(Payment $payment)
    {
        $response = [];
        switch ($payment->type) {
            case Payment::TYPE_SUBSCRIPTION_NEW:
                $sub = User::findOrFail($payment->info['sub_id']);
                $expires = Carbon::now('UTC')->addMonth();
                $info = null;
                if (isset($payment->info['bundle_id'])) {
                    $bundle = $sub->bundles()->findOrFail($payment->info['bundle_id']);
                    $expires = Carbon::now('UTC')->addMonths($bundle->months);
                    $info = [
                        'bundle_id' => $bundle->id
                    ];
                }
                $subscription = $payment->user->subscriptions()->where('token', $payment->token)->first();
                if (!$subscription) {
                    $subscription = $payment->user->subscriptions()->create([
                        'sub_id' => $payment->info['sub_id'],
                        'token' => $payment->token,
                        'gateway' => $payment->gateway,
                        'amount' => $payment->amount,
                        'expires' => $expires,
                        'info' => $info
                    ]);
                }
                $response['user'] = $sub;
                break;
            case Payment::TYPE_SUBSCRIPTION_RENEW:
                if (isset($payment->info['expire'])) {
                    $subscription = $payment->user->subscriptions()->where('token', $payment->token)->first();
                    $subscription->expire = new Carbon($payment->info['expire']);
                    $subscription->save();
                }
                break;
            case Payment::TYPE_POST:
                $post = Post::findOrFail($payment->info['post_id']);
                $post->access()->attach($payment->user->id);
                $response['post'] = $post;
                break;
            case Payment::TYPE_MESSAGE:
                $message = Message::with('user')->findOrFail($payment->info['message_id']);
                $message->access()->attach($payment->user->id);
                $response['message'] = $message;
                break;
        }
        return $response;
    }
}
