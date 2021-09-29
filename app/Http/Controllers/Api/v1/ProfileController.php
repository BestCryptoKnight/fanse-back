<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Image;
use Storage;

use function PHPSTORM_META\map;

class ProfileController extends Controller
{
    public function image(string $type, Request $request)
    {
        $this->validate($request, [
            'image' => 'nullable|image|max:' . config('misc.profile.image.maxsize')
        ]);

        $user = auth()->user();

        $type = $type == 'avatar' ? $type : 'cover';

        // new image uploaded
        if ($file = $request->file('image')) {
            list($w, $h) = explode('x', config('misc.profile.image.resize'));
            $path = storage_path('app/tmp') . DIRECTORY_SEPARATOR . $user->id . '-' . $type . '.' . $file->extension();
            $image = Image::make($file)->orientate()->fit($w, $h, function ($constraint) {
                $constraint->upsize();
            });
            $image->save($path);

            Storage::put('profile/' . $type . '/' . $user->id . '.jpg', file_get_contents($path));
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
            'website' => 'nullable|string|url'
        ]);

        $user->fill($request->only([
            'username', 'name', 'bio', 'location', 'website'
        ]));
        $user->save();

        $user->makeAuth();

        return response()->json($user);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        //
    }
}
