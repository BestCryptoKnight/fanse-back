<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(string $username)
    {
        $user = User::where('username', $username)->firstOrFail();
        $user->makeVisible(['bio', 'location', 'website']);
        return response()->json($user);
    }
}
