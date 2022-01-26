<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Payment as PaymentGateway;
use Log;

class UserController extends Controller
{
    public function suggestions()
    {
        $user = auth()->user();
        $users = User::where('id', '<>', $user->id)
            ->where('role', '<>', User::ROLE_ADMIN)
            ->whereDoesntHave('subscribers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->take(30)->get();
        return response()->json([
            'users' => $users
        ]);
    }

    public function show(string $username)
    {
        $user = User::where('username', $username)->with('bundles')->firstOrFail();
        $user->makeVisible(['bio', 'location', 'website']);
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

    public function dolog()
    {
        return;
    }
}
