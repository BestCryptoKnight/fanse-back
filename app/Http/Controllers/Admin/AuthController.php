<?php

namespace App\Http\Controllers\Admin;

use Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Helpers\Common;
use Storage;
use GuzzleHttp;
use App\Models\Entity;
use Image;
use Illuminate\Validation\Rule;
use Log;

class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'username' => 'required',
            'password' => 'required'
        ]);

        $token = auth('admin')->attempt($request->only([
            'username', 'password'
        ]));

        if (!$token) {
            return response()->json([
                'message' => '',
                'errors' => [
                    '_' => [__('errors.login-failed')]
                ]
            ], 422);
        }

        // all good so return token and user info
        return response()->json([
            'token' => $token
        ]);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();
        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return response()->json(['status' => true]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     * @deprecated
     */
    public function refresh()
    {
        try {
            return response()->json(['token' => auth()->refresh()]);
        } catch (\Exception $e) {
            abort(401, 'Unauthenticated.');
        }
    }
}
