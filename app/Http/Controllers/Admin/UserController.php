<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\Request;
use Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($type = null, Request $request)
    {
        $query = User::whereNotIn('role', [User::ROLE_ADMIN]);
        if ($request->input('q')) {
            $query->where('username', 'like', '%' . $request->input('q') . '%')
                ->orWhere('name', 'like', '%' . $request->input('q') . '%');
        }

        switch ($type) {
            case 'fans':
                $query->where('role', User::ROLE_USER);
                break;
            case 'creators':
                $query->where('role', User::ROLE_CREATOR);
                break;
            case 'verification':
                $query->with('verification')->whereHas('verification', function ($q) {
                    $q->where('status', Verification::STATUS_PENDING);
                });
                break;
            default:
                $query->whereIn('role', [User::ROLE_USER, User::ROLE_CREATOR]);
                break;
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($users);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        $user->makeAuth();
        $user->makeVisible(['commission']);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $this->validate($request, [
            'username' => 'required|regex:/^[a-zA-Z0-9-_]+$/u|between:4,24|unique:App\Models\User,username,' . $user->id,
            'name' => 'required|string|max:191',
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:191',
            'website' => 'nullable|string|url',
            'email' => 'required|email|unique:App\Models\User,email,' . $user->id,
            'new_password' => 'nullable|min:8|confirmed',
            'commission' => 'nullable|integer|min:0|max:100',
            'instagram' => 'nullable|string|url',
            'twitter' => 'nullable|string|url',
            'snapchat' => 'nullable|string|url',
            'tiktok' => 'nullable|string|url'
        ]);

        $user->fill($request->only([
            'username', 'name', 'bio', 'location', 'website', 'email', 'commission','instagram','twitter','snapchat','tiktok'
        ]));
        if ($user->channel_type == User::CHANNEL_EMAIL) {
            $user->channel_id = $user->email;
        }
        if ($request->input('new_password')) {
            $user->password = Hash::make($request['new_password']);
        }
        $user->save();
        $user->makeAuth();
        $user->makeVisible(['commission']);

        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->delete();
    }

    public function verificationApprove(User $user)
    {
        if ($user->verification) {
            $user->verification->status = Verification::STATUS_APPROVED;
            $user->verification->save();
            $user->role = User::ROLE_CREATOR;
            $user->save();
        }
        $user->refresh();
        $user->load('verification');
        return response()->json($user);
    }

    public function verificationDecline(User $user)
    {
        if ($user->verification) {
            $user->verification->status = Verification::STATUS_DECLINED;
            $user->verification->save();
            $user->role = User::ROLE_USER;
            $user->save();
        }
        $user->refresh();
        $user->load('verification');
        return response()->json($user);
    }
}
