<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = User::query();
        if ($request->input('q')) {
            $query->where('username', 'like', '%' . $request->input('q') . '%')
                ->orWhere('name', 'like', '%' . $request->input('q') . '%');
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
            'website' => 'nullable|string|url'
        ]);

        $user->fill($request->only([
            'username', 'name', 'bio', 'location', 'website'
        ]));
        $user->save();

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
}
