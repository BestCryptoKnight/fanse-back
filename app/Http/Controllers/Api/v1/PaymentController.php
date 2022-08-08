<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Post;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use CreatePaymentMethodsTable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Payment as PaymentGateway;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = auth()->user()->payments()->complete()->orderBy('updated_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($payments);
    }

    public function gateways()
    {
        $drivers = PaymentGateway::getPaymentDrivers();
        $dd = [];
        foreach ($drivers as $d) {
            if (!$d->isCC()) {
                $dd[] = ['cc' => false, 'id' => $d->getId(), 'name' => $d->getName()];
            }
        }
        $cc = PaymentGateway::getCCDriver();
        if ($cc) {
            $dd[] = ['cc' => true, 'id' => $cc->getId(), 'name' => ''];
        }
        return response()->json([
            'gateways' => $dd,
            'method' => auth()->user()->mainPaymentMethod
        ]);
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
            'discount' => 'required|numeric|min:0|max:95',
            'months' => 'required|numeric|min:1|max:12',
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

    public function store(Request $request)
    {
        $drivers = PaymentGateway::getPaymentDrivers();
        $gateways = [];
        foreach ($drivers as $d) {
            if (!$d->isCC()) {
                $gateways[] = $d->getId();
            }
        }
        if (PaymentGateway::getCCDriver()) {
            $gateways[] = 'cc';
        }

        $user = auth()->user();

        $rules = [
            'type' => [
                'required',
                Rule::in([
                    Payment::TYPE_SUBSCRIPTION_NEW, Payment::TYPE_POST, Payment::TYPE_MESSAGE, Payment::TYPE_TIP
                ]),
            ],
            'post_id' => 'required_if:type,' . Payment::TYPE_POST . '|exists:posts,id',
            'message_id' => 'required_if:type,' . Payment::TYPE_MESSAGE . '|exists:messages,id',
            'sub_id' => 'required_if:type,' . Payment::TYPE_SUBSCRIPTION_NEW . '|exists:users,id',
            'to_id' => 'required_if:type,' . Payment::TYPE_TIP . '|exists:users,id',
            'amount' => 'required_if:type,' . Payment::TYPE_TIP,
            'bundle_id' => 'nullable|exists:bundles,id',
        ];
        if (!$user->mainPaymentMethod) {
            $rules['gateway'] = [
                'required',
                Rule::in($gateways),
            ];
        }

        $this->validate($request, $rules);

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
                $to = $sub;
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
                $to = $post->user;
                $amount = $post->price;
                break;
            case Payment::TYPE_MESSAGE:
                $info['message_id'] = $request['message_id'];
                $message = Message::findOrFail($info['message_id']);
                if ($user->id == $message->user_id) {
                    abort(403);
                }
                $to = $message->user;
                $amount = $message->price;
                break;
            case Payment::TYPE_TIP:
                $info['message'] = $request->input('message', '');
                if ($request->input('post_id')) {
                    $info['post_id'] = $request['post_id'];
                }
                $amount = $request['amount'] * 100;
                $to = User::find($request['to_id']);
                break;
        }

        if ($user->mainPaymentMethod) {
            $gateway = PaymentGateway::getCCDriver();
        } else {
            $gateway = PaymentGateway::driver($request['gateway']);
        }

        $payment = $user->payments()->create([
            'type' => $request['type'],
            'to_id' => $to->id,
            'info' => $info,
            'amount' => $amount,
            'gateway' => $gateway->getId(),
            'fee' => $to->commission
        ]);

        $response = $request['type'] == Payment::TYPE_SUBSCRIPTION_NEW
            ? $gateway->subscribe($request, $payment, $sub, $bundle)
            : $gateway->buy($request, $payment);

        if (!$response) {
            return response()->json([
                'message' => '',
                'errors' => [
                    '_' => [__('errors.order-can-not-be-processed')]
                ]
            ], 422);
        }

        if (isset($response['info'])) {
            if ($request['title']) {
                $m = $user->paymentMethods()->where([
                    'gateway' => 'cc',
                    'title' => $request['title']
                ])->first();
                if (!$m) {
                    $m = $user->paymentMethods()->create([
                        'gateway' => 'cc',
                        'title' => $request['title'],
                        'info' => $response['info'],
                        'main' => $user->mainPaymentMethod ? false : true
                    ]);
                }
            }
            return $this->doProcess($payment);
        }

        return response()->json($response);
    }

    public function process(string $gateway, Request $request)
    {
        $gateway = PaymentGateway::driver($gateway);
        $result = $gateway->validate($request);
        if (is_array($result)) {
            $payment = $result['payment'];
            if (isset($result['info'])) {
                $m = $payment->user->paymentMethods()->where([
                    'gateway' => 'cc',
                    'title' => $result['title']
                ])->first();
                if (!$m) {
                    $m = $payment->user->paymentMethods()->create([
                        'gateway' => 'cc',
                        'title' => $result['title'],
                        'info' => $result['info'],
                        'main' => $payment->user->mainPaymentMethod ? false : true
                    ]);
                }
            }
        } else {
            $payment = $result;
        }
        return $this->doProcess($payment);
    }

    private function doProcess($validated)
    {
        if ($validated) {
            $response = PaymentGateway::processPayment($validated);
            $response['status'] = true;
            $validated->status = Payment::STATUS_COMPLETE;
            $validated->save();
            return response()->json($response);
        }
        return response()->json([
            'message' => '',
            'errors' => [
                '_' => [__('errors.order-can-not-be-processed')]
            ]
        ], 422);
    }

    public function methodIndex()
    {
        $driver = PaymentGateway::getCCDriver();
        $cc = $driver ? [
            'id' => $driver->getId()
        ] : null;

        return response()->json([
            'methods' => auth()->user()->paymentMethods,
            'cc' => $cc
        ]);
    }

    public function methodMain(PaymentMethod $paymentMethod)
    {
        $this->authorize('update', $paymentMethod);
        $user = auth()->user();

        foreach ($user->paymentMethods as $p) {
            $p->main = $p->id == $paymentMethod->id;
            $p->save();
        }

        $user->refresh();
        $user->load('paymentMethods');

        return response()->json(['methods' => $user->paymentMethods]);
    }

    public function methodStore(Request $request)
    {
        $driver = PaymentGateway::getCCDriver();
        if (!$driver) {
            abort(500, 'CC Driver is not set.');
        }

        $user = auth()->user();

        $info = $driver->attach($request, $user);
        if (!$info) {
            return response()->json([
                'message' => '',
                'errors' => [
                    '_' => [__('errors.payment-method-error')]
                ]
            ], 422);
        }

        $m = $user->paymentMethods()->create([
            'info' => $info,
            'title' => isset($info['title']) ? $info['title'] : $request->input('title'),
            'gateway' => 'cc'
        ]);
        if (!$user->mainPaymentMethod) {
            $m->main = true;
            $m->save();
        }

        $m->refresh();
        return response()->json($m);
    }

    public function methodDestroy(PaymentMethod $paymentMethod)
    {
        $this->authorize('delete', $paymentMethod);
        $paymentMethod->delete();
        $user = auth()->user();
        if (!$user->mainPaymentMethod) {
            $next = $user->paymentMethods()->first();
            if ($next) {
                $next->main = true;
                $next->save();
            }
        }
        $user->refresh();
        $user->load('paymentMethods');

        return response()->json(['methods' => auth()->user()->paymentMethods]);
    }
}
