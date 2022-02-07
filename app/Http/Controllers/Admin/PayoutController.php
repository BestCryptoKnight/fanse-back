<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\PayoutBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Storage;
use Payment as PaymentGateway;

class PayoutController extends Controller
{
    public function index($type = null)
    {
        $query = Payout::with('user.payoutMethod')->with('batches');
        switch ($type) {
            case 'complete':
                $query->where('status', Payout::STATUS_COMPLETE);
                break;
            case 'pending':
            default:
                $query->where('status', Payout::STATUS_PENDING);
                break;
        }
        $payouts = $query->orderBy('created_at', 'asc')->paginate(config('misc.page.size'));
        $payouts->map(function ($item) {
            $item->append('batch');
        });
        return response()->json($payouts);
    }

    public function mark(Request $request, Payout $payout)
    {
        $this->validate($request, [
            'status' => [
                'required',
                Rule::in(Payout::STATUS_COMPLETE, Payout::STATUS_PENDING)
            ]
        ]);
        $payout->status = $request['status'];
        $payout->processed_at = $payout->status == Payout::STATUS_COMPLETE ? Carbon::now('UTC') : null;
        $payout->save();

        $payout->refresh()->load('user.payoutMethod');
        return response()->json($payout);
    }

    public function destroy(Payout $payout)
    {
        $payout->delete();
        return response()->json(['status' => true]);
    }

    public function batchIndex($type = null)
    {
        $query = PayoutBatch::withCount('payouts');
        switch ($type) {
            case 'pending':
                $query->where('status', Payout::STATUS_PENDING);
                break;
            case 'complete':
                $query->where('status', Payout::STATUS_COMPLETE);
                break;
            default:
                break;
        }
        $batches = $query->orderBy('created_at', 'asc')->paginate(config('misc.page.size'));
        return response()->json($batches);
    }

    public function batchStore()
    {
        $batch = PayoutBatch::create();
        $payouts = Payout::pending()->whereDoesntHave('batches')->get()->pluck('id');
        $batch->payouts()->sync($payouts);
        $batch->refresh()->loadCount('payouts');
        return response()->json($batch);
    }

    public function batchMark(Request $request, PayoutBatch $payoutBatch)
    {
        $this->validate($request, [
            'status' => [
                'required',
                Rule::in(Payout::STATUS_COMPLETE, Payout::STATUS_PENDING)
            ]
        ]);

        $now = Carbon::now('UTC');

        $payoutBatch->status = $request['status'];
        $payoutBatch->processed_at = $payoutBatch->status == Payout::STATUS_COMPLETE ? $now : null;
        $payoutBatch->save();

        foreach ($payoutBatch->payouts as $p) {
            $p->status = $request['status'];
            $p->processed_at = $payoutBatch->status == Payout::STATUS_COMPLETE ? $now : null;
            $p->save();
        }

        $payoutBatch->refresh()->loadCount('payouts');
        return response()->json($payoutBatch);
    }

    public function batchDestroy(PayoutBatch $payoutBatch)
    {
        if ($payoutBatch->status == Payout::STATUS_PENDING) {
            $payoutBatch->delete();
        }
        return response()->json(['status' => true]);
    }

    public function batchFile(PayoutBatch $payoutBatch)
    {
        $files = [];
        $gateways = [];
        foreach ($payoutBatch->payouts as $payout) {
            $gateway = $payout->info['gateway'];
            if (!in_array($gateway, $files)) {
                $files[$gateway] = fopen(storage_path('app/tmp') . DIRECTORY_SEPARATOR . $gateway . '.csv', 'w');
            }
            if (!in_array($gateway, $gateways)) {
                $gateways[$gateway] = PaymentGateway::driver($gateway);
            }
            $gateways[$gateway]->export($payout, $files[$gateway]);
        }

        $zfile = storage_path('app/tmp') . DIRECTORY_SEPARATOR  . 'payouts-batch-' . $payoutBatch->hash . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zfile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $type => $f) {
            fclose($f);
            $zip->addFile(storage_path('app/tmp') . DIRECTORY_SEPARATOR . $type . '.csv', $type . '.csv');
        }
        $zip->close();
        return response()->download($zfile);
    }
}
