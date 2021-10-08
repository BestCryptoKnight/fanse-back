<?php

namespace App\Http\Controllers\Api\v1;

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
use Hash;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'min:8',
                'confirmed'
            ],
            'name' => 'required|min:3'
        ], [
            'password' => __('validation.password_format')
        ]);

        $data = $request->only(['email', 'password', 'name']);
        $data['password'] = Hash::make($data['password']);
        $data['channel_id'] = $data['email'];
        $data['channel_type'] = User::CHANNEL_EMAIL;
        $user = User::create($data);

        $token = $user->createToken('main');
        $user->makeAuth();

        // all good so return token and user info
        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
            'is_new' => true
        ]);
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'channel_type' => [
                'required',
                Rule::in([
                    User::CHANNEL_EMAIL, User::CHANNEL_GOOGLE
                ]),
            ],
            'token' => 'required_unless:channel_type,' . User::CHANNEL_EMAIL,
            'email' => 'required_if:channel_type,' . User::CHANNEL_EMAIL,
            'password' => 'required_if:channel_type,' . User::CHANNEL_EMAIL
        ]);

        $is_new = false;

        switch ($request['channel_type']) {
            case User::CHANNEL_EMAIL:
                $user = User::where('channel_type', User::CHANNEL_EMAIL)
                    ->where('channel_id', $request['email'])
                    ->first();
                if (!$user || !Hash::check($request['password'], $user->password)) {
                    return response()->json([
                        'message' => '',
                        'errors' => [
                            '_' => __('errors.wrong-email-or-password')
                        ]
                    ], 422);
                }
                break;
            case User::CHANNEL_GOOGLE:
                // create user here if needed
                break;
        }

        $token = $user->createToken('main');
        $user->makeAuth();

        // all good so return token and user info
        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user,
            'is_new' => $is_new
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
        $user->makeAuth();
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

    public function dolog()
    {
    }
}
