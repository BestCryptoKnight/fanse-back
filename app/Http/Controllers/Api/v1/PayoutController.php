<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\PayoutMethod;
use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Payment as PaymentGateway;

class PayoutController extends Controller
{
    public function index()
    {
        
        $payouts = auth()->user()->payouts()->complete()->orderBy('updated_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($payouts);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|integer'
        ]);
        $user = auth()->user();
        $amount = $request['amount'] * 100;
        if ($request['amount'] > $user->balance || $request['amount'] > config('misc.payment.payout.min') * 100) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'amount' => [__('errors.incorrect-amount')]
                ]
            ], 422);
        }

        if ($user->withdraw) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'amount' => [__('errors.pending-withdrawal')]
                ]
            ], 422);
        }

        if (!$user->payoutMethod) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'amount' => [__('errors.no-payout-method-found')]
                ]
            ], 422);
        }

        $withdraw = $user->payouts()->create([
            'amount' => $amount,
            'info' => $user->payoutMethod
        ]);

        return response()->json($withdraw);
    }

    public function verificationStore(Request $request)
    {
        $countries = require(resource_path('data/countries.php'));
        $this->validate($request, [
            'country' => [
                'required',
                Rule::in(array_keys($countries))
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zip' => 'required|string|max:50',
            'photo' => 'required|image'
        ]);
        $user = auth()->user();
        $verification = Verification::firstOrNew(['user_id' => $user->id]);
        $verification->fill([
            'country' => $request['country'],
            'info' => $request->only(['first_name', 'last_name', 'address', 'city', 'state', 'zip'])
        ]);
        $verification->status = Verification::STATUS_PENDING;
        $verification->save();

        $image = $request->file('photo');
        $image->storeAs('verifications', $verification->hash . '.jpg');

        $verification->refresh();
        return response()->json($verification);
    }

    public function verificationShow()
    {
        return response()->json(['verification' => auth()->user()->verification]);
    }

    public function methodStore(Request $request)
    {
        $drivers = PaymentGateway::getPayoutDrivers();
        $gateways = [];
        foreach ($drivers as $d) {
            $gateways[] = $d->getId();
        }

        $this->validate($request, [
            'gateway' => [
                'required',
                Rule::in($gateways)
            ],
            'paypal' => 'required_if:gateway,paypal|email',
            'address' => 'required_if:gateway,bank|string|max:500',
            'name' => 'required_if:gateway,bank|string|max:100',
            'swift' => 'required_if:gateway,bank|string|max:100',
            'account' => 'required_if:gateway,bank|string|max:100',
        ]);

        $info = [];
        switch ($request['gateway']) {
            case 'paypal':
                $info['paypal'] = $request['paypal'];
                break;
            case 'bank':
                $info = $request->only(['address', 'name', 'swift', 'account']);
                break;
        }

        $user = auth()->user();
        $method = $user->payoutMethod;
        if (!$method) {
            $method = new PayoutMethod(['user_id' => $user->id]);
        }
        $method->gateway = $request['gateway'];
        $method->info = $info;
        $method->save();

        $method->refresh();
        return response()->json($method);
    }

    public function methodUpdate(PayoutMethod $payoutMethod)
    {
        $user = auth()->user();
        if ($payoutMethod->user_id != $user->id) {
            abort(403);
        }
        PayoutMethod::where('user_id', $user->id)->update(['main' => false]);
        $payoutMethod->main = true;
        $payoutMethod->save();
        return response()->json($user->payoutMethods);
    }

    public function info()
    {
        $user = auth()->user();
        $settings = [
            'payout' => config('misc.payment.payout.min') * 100
        ];
        $stats = [
            'balance' => $user->balance,
            'withdraw' => $user->withdraw
        ];

        $drivers = PaymentGateway::getPayoutDrivers();
        $dd = [];
        foreach ($drivers as $d) {
            $dd[] = ['id' => $d->getId(), 'name' => $d->getName()];
        }

        return response()->json([
            'method' => $user->payoutMethod,
            'stats' => $stats,
            'settings' => $settings,
            'gateways' => $dd
        ]);
    }

    public function earningsIndex()
    {
        $user = auth()->user();
        $earnings = $user->earnings()->with('user')->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($earnings);
    }
}
