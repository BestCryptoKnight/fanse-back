<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Payment as PaymentGateway;

class PaymentController extends Controller
{
    public function paymentGateways()
    {
        $drivers = PaymentGateway::getEnabledDrivers();
        $dd = [];
        foreach ($drivers as $d) {
            $dd[] = ['id' => $d->getId(), 'name' => $d->getName()];
        }
        return response()->json(['gateways' => $dd]);
    }

    public function price(Request $request)
    {
        $this->validate($request, [
            'price' => 'required|numeric|min:0|max:' . config('misc.payment.pricing.caps.subscription')
        ]);
        $user = auth()->user();
        $user->price = $request['price'] * 100;
        $user->save();
        $user->refresh();
        $user->makeAuth();
        return response()->json($user);
    }

    public function bundleStore(Request $request)
    {
        $this->validate($request, [
            'discount' => 'required|numeric|min:0|max:' . config('misc.payment.pricing.caps.discount'),
            'months' => 'required|numeric|min:2|max:12',
        ]);
        $user = auth()->user();

        $found = false;
        foreach ($user->bundles as $b) {
            if ($b->months == $request['months']) {
                $b->discount = $request['discount'];
                $b->save();
                $found = true;
                break;
            }
        }

        if (!$found) {
            $bundle = $user->bundles()->create($request->only(['discount', 'months']));
        }

        $user->refresh();
        $user->makeAuth();
        return response()->json($user);
    }

    public function bundleDestroy(Bundle $bundle, Request $request)
    {
        if ($bundle->user_id != auth()->user()->id) {
            abort(403);
        }
        $bundle->delete();

        $user = auth()->user();
        $user->makeAuth();
        return response()->json($user);
    }

    public function paymentStore(Request $request)
    {
        $drivers = PaymentGateway::getEnabledDrivers();
        $gateways = [];
        foreach ($drivers as $d) {
            $gateways[] = $d->getId();
        }

        $this->validate($request, [
            'gateway' => [
                'required',
                Rule::in($gateways),
            ],
            'type' => [
                'required',
                Rule::in([
                    Payment::TYPE_SUBSCRIPTION_NEW, Payment::TYPE_POST, Payment::TYPE_MESSAGE
                ]),
            ],
            'post_id' => 'required_if:type,' . Payment::TYPE_POST . '|exists:posts,id',
            'message_id' => 'required_if:type,' . Payment::TYPE_MESSAGE . '|exists:messages,id',
            'sub_id' => 'required_if:type,' . Payment::TYPE_SUBSCRIPTION_NEW . '|exists:users,id',
            'bundle_id' => 'nullable|exists:bundles,id',
        ]);

        $user = auth()->user();
        $amount = 0;
        $bundle = null;
        $info = [];
        $to = null;
        switch ($request['type']) {
            case Payment::TYPE_SUBSCRIPTION_NEW:
                $info['sub_id'] = $request['sub_id'];
                $sub = User::findOrFail($info['sub_id']);
                if ($user->id == $sub->id) {
                    abort(403);
                }
                $to = $sub->id;
                $amount = $sub->price;
                if ($request->input('bundle_id')) {
                    $info['bundle_id'] = $request['bundle_id'];
                    $bundle = $sub->bundles()->where('id', $info['bundle_id'])->firstOrFail();
                    $amount = $bundle->price;
                }
                break;
            case Payment::TYPE_POST:
                $info['post_id'] = $request['post_id'];
                $post = Post::findOrFail($info['post_id']);
                if ($user->id == $post->user_id) {
                    abort(403);
                }
                $to = $post->user_id;
                $amount = $post->price;
                break;
            case Payment::TYPE_MESSAGE:
                $info['message_id'] = $request['message_id'];
                $message = Message::findOrFail($info['message_id']);
                if ($user->id == $message->user_id) {
                    abort(403);
                }
                $to = $message->user_id;
                $amount = $message->price;
                break;
        }

        $gateway = PaymentGateway::driver($request['gateway']);

        $payment = $user->payments()->create([
            'type' => $request['type'],
            'to_id' => $to,
            'info' => $info,
            'amount' => $amount,
            'gateway' => $gateway->getId()
        ]);

        $response = $request['type'] == Payment::TYPE_SUBSCRIPTION_NEW
            ? $gateway->subscribe($payment, $sub, $bundle)
            : $gateway->buy($payment);

        return response()->json($response);
    }

    public function paymentProcess(string $gateway, Request $request)
    {
        $gateway = PaymentGateway::driver($gateway);
        $payment = $gateway->validate($request);
        $response = ['status' => true];
        if ($payment) {
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
            $payment->status = Payment::STATUS_COMPLETE;
            $payment->save();
            return response()->json($response);
        } else {
            return response()->json([
                'message' => '',
                'errors' => [
                    '_' => [__('errors.order-can-not-be-processed')]
                ]
            ], 422);
        }
    }
}
