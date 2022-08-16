<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Payment as PaymentGateway;
use Log;
use Cache;
use Carbon\Carbon;
use Symfony\Component\VarDumper\VarDumper;

class UserController extends Controller
{
    public function suggestions()
    {
        $user = auth()->user();
        $users = User::where('id', '<>', $user->id)
            ->where('role', '<>', User::ROLE_ADMIN)
            ->whereDoesntHave('subscribers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->get();
        return response()->json([
            'users' => $users
        ]);
    }

    public function show(string $username)
    {
        $user = User::where('username', $username)->with('bundles')->firstOrFail();
        $user->makeVisible(['bio', 'location', 'website','instagram','twitter','snapchat','tiktok']);
        return response()->json($user);
    }

    public function subscriptions()
    {
        $subs = auth()->user()->subscriptions()->with('sub')->paginate(config('misc.page.size'));
        return response()->json([
            'subs' => $subs
        ]);
    }

    public function subscribe(User $user)
    {
        $current = auth()->user();
        if ($current->id == $user->id) {
            abort(403);
        }

        $subscription = $current->subscriptions()->where('sub_id', $user->id)->first();
        if ($subscription) {
            $subscription->active = true;
            $subscription->save();
        } else {
            $subscription = $current->subscriptions()->create([
                'sub_id' => $user->id
            ]);
        }

        $subscription->refresh();
        $subscription->load('sub');

        return response()->json($subscription);
    }

    public function subscriptionDestroy(User $user)
    {
        $sub = auth()->user()->subscriptions()->whereHas('sub', function ($q) use ($user) {
            $q->where('id', $user->id);
        })->firstOrFail();

        if ($sub->active && $sub->gateway) {
            $gateway = PaymentGateway::driver($sub->gateway);
            $gateway->unsubscribe($sub);
        }

        if ($sub->expires) {
            $sub->active = false;
            $sub->save();
            return response()->json(['status' => true, 'subscription' => $sub]);
        }
        $sub->delete();
        return response()->json(['status' => false]);
    }

    public function resubscription(User $user)
    {
        $sub = auth()->user()->subscriptions()->whereHas('sub', function ($q) use ($user) {
            $q->where('id', $user->id);
        })->firstOrFail();
        if (!$sub->active && $sub->gateway) {
            $gateway = PaymentGateway::driver($sub->gateway);
            $gateway->resubscribe($sub);
        } 

        if ($sub->expires) {
            $sub->active = true;
            $sub->save();
            $sub->refresh();
            $sub->load('sub');
            return response()->json($sub);
        }
        $sub->delete();
        return response()->json(['status' => false]);
    }

    public function dolog()
    {
        return;
    }

    /**
     * Show user online status.
     */
    public function userStatus()
    {
        $users = User::all();
        return response()->json($users);
    }

    public function status()
    {
        $users = User::all();

        foreach ($users as $user) {

            if (Cache::has('user-is-online-' . $user->id))
                echo $user->name . " is online. Last seen: " . Carbon::parse($user->last_seen)->diffForHumans() . " ";
            else {
                if ($user->last_seen != null) {
                    echo $user->name . " is offline. Last seen: " . Carbon::parse($user->last_seen)->diffForHumans() . " ";
                } else {
                    echo $user->name . " is offline. Last seen: No data ";
                }
            }
        }
    }

    /**
     * Live status.
     */
    public function liveStatus($user_id)
    {
        // get user data
        $user = User::find($user_id);

        // check online status
        if (Cache::has('user-is-online-' . $user->id))
            $status = 'Online';
        else
            $status = 'Offline';

        // check last seen
        if ($user->last_seen != null)
            $last_seen = "Active " . Carbon::parse($user->last_seen)->diffForHumans();
        else
            $last_seen = "No data";

        // response
        return response()->json([
            'status' => $status,
            'last_seen' => $last_seen,
        ]);
    }
}
