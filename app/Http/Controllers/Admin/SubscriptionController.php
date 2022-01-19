<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($type = null, Request $request)
    {
        $query = Subscription::query()->with(['sub', 'user'])->whereNotNull('expires');
        switch ($type) {
            case 'expired':
                $query->onlyTrashed();
                break;
            case 'active':
                $query->where('active', true);
                break;
            case 'cancelled':
                $query->where('active', false);
                break;
            default:
                $query->withTrashed();
                break;
        }
        $subs = $query->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        $subs->map(function ($item) {
            $item->append(['total', 'fee']);
            $item->makeVisible(['deleted_at']);
        });
        return response()->json($subs);
    }

    public function resume(Subscription $subscription)
    {
        $subscription->active = true;
        $subscription->save();
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->active = false;
        $subscription->save();
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->forceDelete();
    }
}
