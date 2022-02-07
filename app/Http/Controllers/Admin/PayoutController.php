<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\PayoutBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Storage;

class PayoutController extends Controller
{
    public function index()
    {
        $query = Payout::with('user.payoutMethod')->with('batches');
        switch ($type) {
            case 'pending':
                $query->where('status', Payout::STATUS_PENDING);
                break;
            case 'complete':
                $query->where('status', Payment::STATUS_COMPLETE);
                break;
            default:
                break;
        }
        $payouts = $query->orderBy('created_at', 'asc')->paginate(config('misc.page.size'));
        return response()->json($payouts);
    }

    public function mark(Request $request, Payout $payout)
    {
        $this->validate($request, [
            'status' => [
                'requried',
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

    public function batchIndex()
    {
        $query = PayoutBatch::withCount('payouts');
        switch ($type) {
            case 'pending':
                $query->where('status', Payout::STATUS_PENDING);
                break;
            case 'complete':
                $query->where('status', Payment::STATUS_COMPLETE);
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
        $payouts = Payout::pending()->get()->pluck('id');
        $batch->sync($payouts);
        $batch->refresh()->loadCount('payouts');
        return response()->json($batch);
    }

    public function batchMark(Request $request, PayoutBatch $batch)
    {
        $this->validate($request, [
            'status' => [
                'requried',
                Rule::in(Payout::STATUS_COMPLETE, Payout::STATUS_PENDING)
            ]
        ]);
        $batch->status = $request['status'];
        $batch->processed_at = $batch->status == Payout::STATUS_COMPLETE ? Carbon::now('UTC') : null;
        $batch->save();

        $batch->refresh()->load('user.payoutMethod');
        return response()->json($payout);
    }

    public function batchDestroy(PayoutBatch $batch)
    {
        $batch->delete();
        return response()->json(['status' => true]);
    }

    public function batchFile(PayoutBatch $batch)
    {
        $files = [];
        $gateways = [];
        foreach ($batch->payouts as $payout) {
            $type = $payout->info['type'];
            if (!in_array($type, $files)) {
                $files[$type] = fopen(storage_path('app/tmp') . DIRECTORY_SEPARATOR . $type . '.csv', 'w');
            }
            if (!in_array($type, $gateways)) {
                $gateways[$type] = PaymentGateway::driver($type);
            }
            $gateways[$type]->export($payout, $files[$type]);
        }

        $zfile = storage_path('app/tmp') . DIRECTORY_SEPARATOR  . 'payouts-batch-' . $batch->hash . '.zip';
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
