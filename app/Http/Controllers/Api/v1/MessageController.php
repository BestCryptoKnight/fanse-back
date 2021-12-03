<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Log;
use DB;

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
        $chats = $user->mailbox()->whereIn('messages.id', function ($q) use ($user) {
            $q->selectRaw('max(message_id)')->from('message_user')->where('user_id', $user->id)->groupBy('party_id');
        })->orderBy('created_at', 'desc')->get();

        $chats->map(function ($item) {
            $item->append(['party', 'read']);
        });

        return response()->json($chats);
    }

    public function indexChat(User $user)
    {
        $current = auth()->user();
        $messages = $current->mailbox()->with('media')->wherePivot('party_id', $user->id)->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        $messages->map(function ($item) {
            $item->append('read');
        });

        DB::table('message_user')->whereIn('message_id', $messages->pluck('message_id'))->where(function ($q) use ($current) {
            $q->where('user_id', $current->id)->orWhere('party_id', $current->id);
        })->update([
            'read' => 1
        ]);

        return response()->json([
            'party' => $user,
            'messages' => $messages
        ]);
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
            'price' => 'nullable|integer'
        ]);

        $price = $request->input('price') * 100;

        $message = $current->messages()->create([
            'message' => $request['message'],
            'price' => $price
        ]);

        $media = $request->input('media');
        if ($media) {
            $media = collect($media)->pluck('screenshot', 'id');
            $models = $current->media()->whereIn('id', $media->keys())->get();
            foreach ($models as $model) {
                $model->publish();
                if (isset($media[$model->id])) {
                    $info = $model->info;
                    $info['screenshot'] = $media[$model->id];
                    $model->info = $info;
                    $model->save();
                }
            }
            $message->media()->sync($media->keys());
        }

        // mailbox
        $current->mailbox()->attach($message, ['party_id' => $user->id]);
        $user->mailbox()->attach($message, ['party_id' => $current->id]);

        $message->refresh()->load('media');
        return response()->json($message);
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
        DB::table('message_user')->where('party_id', $user->id)->delete();
        return response()->json(['status' => true]);
    }
}
