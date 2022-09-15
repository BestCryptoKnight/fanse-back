<?php

namespace App\Http\Controllers\Api\v1;


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
use Socialite;
use App\Rules\Recaptcha;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'min:8',
            ],
            'name' => 'required|min:3',
            'captcha_token' => [new Recaptcha()]
        ], [
            'password' => __('validation.password_format')
        ]);

        $data = $request->only(['email', 'password', 'name']);
        $data['password'] = Hash::make($data['password']);
        $data['channel_id'] = $data['email'];
        $data['channel_type'] = User::CHANNEL_EMAIL;
        $user = User::create($data);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('main', $user->abilities);
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
            'email' => 'required_if:channel_type,' . User::CHANNEL_EMAIL . '|email',
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
                            '_' => [__('errors.wrong-email-or-password')]
                        ]
                    ], 422);
                }
                break;
            case User::CHANNEL_GOOGLE:
                $duser = null;
                $driver = Socialite::driver(User::typeToString($request['channel_type']));
                try {
                    $duser = $driver->userFromToken($request['token']);
                } catch (\Exception $e) {
                    // failed
                }
                if (!$duser) {
                    return response()->json([
                        'message' => '',
                        'errors' => [
                            '_' => __('errors.login-failed')
                        ]
                    ], 422);
                }

                $user = User::where([
                    'channel_type' => $request['channel_type'],
                    'channel_id' => $duser->id,
                ])->first();

                if (!$user) {
                    $user = User::create([
                        'channel_type' => $request['channel_type'],
                        'channel_id' => $duser->id,
                        'name' => $duser->getName(),
                        'email' => $duser->getEmail()
                    ]);
                }
                break;
        }

        $token = $user->createToken('main', $user->abilities);
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
        auth()->user()->currentAccessToken()->delete();
        return response()->json(['status' => true]);
    }

    public function dolog()
    {
    }
    
    public function socialSignup($provider) {
        $auth = Socialite::driver($provider)->stateless()->user();
        $email = $auth->getEmail();
        $name = $auth->getName();
        $user = User::where('email', '=', $email)->first();
        if ($user === null) {
            $user = User::create([
                'channel_type' => 0,
                'channel_id' => $email,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($email)
            ]);
        } else {
            $user->channel_type = 0;
            $user->password = Hash::make($email);
            $user->save();
        }
        $token = $user->createToken('main', $user->abilities);
        $user->makeAuth();
        return response()->json(['data' => $email, 'user' => $user, 'token' => $token->plainTextToken]);
    }
}
