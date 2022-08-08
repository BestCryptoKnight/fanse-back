<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\Request;
use Image;
use Storage;
use Hash;
use Illuminate\Validation\Rule;

use function PHPSTORM_META\map;

class ProfileController extends Controller
{
    public function image(string $type, Request $request)
    {
        $this->validate($request, [
            'image' => 'nullable|image|max:' . config('misc.profile.' . $type . '.maxsize')
        ]);

        $user = auth()->user();

        $type = $type == 'avatar' ? $type : 'cover';

        // new image uploaded
        if ($file = $request->file('image')) {
            list($w, $h) = explode('x', config('misc.profile.' . $type . '.resize'));
            $path = storage_path('app/tmp') . DIRECTORY_SEPARATOR . $user->id . '-' . $type . '.' . $file->extension();
            $image = Image::make($file)->orientate()->fit($w, $h, function ($constraint) {
                $constraint->upsize();
            });
            $image->save($path);

            Storage::put('profile/' . $type . '/' . $user->id . '.jpg' , file_get_contents($path));
            Storage::disk('local')->delete('tmp/' . $user->id . '-' . $type . '.' . $file->extension());

            $user->{$type} = 1;
        }
        // user wants to remove image
        else {
            if ($user->{$type} == 1) {
                Storage::delete('profile/' . $type . '/' . $user->id . '.jpg');
            }
            $user->{$type} = 0;
        }

        $user->save();
        $user->makeAuth();

        return response()->json($user);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $this->validate($request, [
            'username' => 'required|regex:/^[a-zA-Z0-9-_]+$/u|between:4,24|unique:App\Models\User,username,' . $user->id,
            'name' => 'required|string|max:191',
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:191',
            'website' => 'nullable|string|url',
            'instagram' => 'nullable|string|url',
            'twitter' => 'nullable|string|url',
            'snapchat' => 'nullable|string|url',
            'tiktok' => 'nullable|string|url'

        ]);

        $user->fill($request->only([
            'username', 'name', 'bio', 'location', 'website','instagram','twitter','snapchat','tiktok'
        ]));
        $user->save();

        $user->makeAuth();

        return response()->json($user);
    }

    public function email(Request $request)
    {
        $user = auth()->user();
        $this->validate($request, [
            'email' => 'required|email|unique:App\Models\User,email,' . $user->id,
            'password' => 'required|string',
        ]);

        if (!Hash::check($request['password'], $user->password)) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'password' => [__('errors.wrong-password')]
                ]
            ], 422);
        }

        if ($user->email != $request['email']) {
            $user->email = $request['email'];
            if ($user->channel_type == User::CHANNEL_EMAIL) {
                $user->channel_id = $user->email;
            }
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        $user->makeAuth();
        return response()->json($user);
    }

    public function password(Request $request)
    {
        $this->validate($request, [
            'old_password' => 'required|string',
            'new_password' => 'required|min:8|confirmed',
        ], [
            'new_password' => __('validation.password_format')
        ]);

        $user = auth()->user();

        if (!Hash::check($request['old_password'], $user->password)) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'old_password' => [__('errors.wrong-old-password')]
                ]
            ], 422);
        }

        $user->password = Hash::make($request['new_password']);
        $user->save();
        // TODO: notify about password change via email
        $user->makeAuth();
        return response()->json($user);
    }
}
