<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Payment as PaymentGateway;

class PaymentController extends Controller
{
    public function index($type = null, Request $request)
    {
        $query = Payment::query()->with(['user', 'to']);
        if ($request->input('q')) {
            $query->where('hash', 'like', '%' . $request->input('q') . '%')
                ->orWhere('token', 'like', '%' . $request->input('q') . '%')
                ->orWhereHas('user', function ($q) use ($request) {
                    $q->where('username', 'like', '%' . $request->input('q') . '%')
                        ->orWhere('name', 'like', '%' . $request->input('q') . '%');
                })
                ->orWhereHas('to', function ($q) use ($request) {
                    $q->where('username', 'like', '%' . $request->input('q') . '%')
                        ->orWhere('name', 'like', '%' . $request->input('q') . '%');
                });
        }

        switch ($type) {
            case 'pending':
                $query->where('status', Payment::STATUS_PENDING);
                break;
            case 'refunded':
                $query->where('status', Payment::STATUS_REFUNDED);
                break;
            default:
                $query->where('status', Payment::STATUS_COMPLETE);
                break;
        }
        $payments = $query->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        $payments->map(function ($item) {
            $item->makeVisible(['token']);
        });
        return response()->json($payments);
    }

    public function update(Request $request, Payment $payment)
    {
        $this->validate($request, [
            'status' => [
                'required',
                Rule::in([
                    Payment::STATUS_COMPLETE,
                    Payment::STATUS_REFUNDED
                ])
            ],
        ]);

        if (
            $payment->status == Payment::STATUS_PENDING
            && $request['status'] == Payment::STATUS_COMPLETE
        ) {
            PaymentGateway::processPayment($payment);
        }

        $payment->status = $request['status'];
        $payment->save();
        return response()->json($payment);
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(['status' => true]);
    }
}
