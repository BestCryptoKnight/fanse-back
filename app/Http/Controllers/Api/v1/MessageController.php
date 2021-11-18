<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // find all last messages
        $user = auth()->user();
        $chats = $user->messages()->with('party')->whereIn('id', function ($q) use ($user) {
            $q->selectRaw('max(id)')->from('messages')->where('user_id', $user->id)->groupBy('party_id')->orderBy('created_at', 'desc');
        })->get();

        return response()->json($chats);
    }

    public function indexChat(User $user)
    {
        $current = auth()->user();
        $messages = $current->messages()->with('party')->where('party_id', $user->id)->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($messages);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(User $user, Request $request)
    {
        $current = auth()->user();

        $this->validate($request, [
            'message' => 'required|max:191',
            'media' => 'nullable|array|max:' . config('misc.post.media.max'),
        ]);

        $messageFrom = $current->messages()->create([
            'message' => $request['message'],
            'party_id' => $user->id,
            'direction' => false
        ]);

        $messageTo = $user->messages()->create([
            'message' => $request['message'],
            'party_id' => $current->id,
            'direction' => true
        ]);

        $media = $request->input('media');
        if ($media) {
            $media = collect($media)->pluck('screenshot', 'id');
            $models = $user->media()->whereIn('id', $media->keys())->get();
            foreach ($models as $model) {
                $model->publish();
                if (isset($media[$model->id])) {
                    $info = $model->info;
                    $info['screenshot'] = $media[$model->id];
                    $model->info = $info;
                    $model->save();
                }
            }
            $messageFrom->media()->sync($media->keys());
            $messageTo->media()->sync($media->keys());
        }

        $messageFrom->refresh()->load(['media']);
        return response()->json($messageFrom);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $current = auth()->user();
        $current->messages()->where('party_id', $user->id)->delete();
        return response()->json(['status' => true]);
    }
}
