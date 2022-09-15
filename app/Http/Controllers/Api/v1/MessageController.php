<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\MessageEvent;
use App\Events\MessageReadEvent;
use App\Http\Controllers\Controller;
use App\Jobs\MassMessageJob;
use App\Jobs\MessageJob;
use App\Models\CustomList;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
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

        // is there a running or recent mass messaging campaign
        $mass = $user
            ->messages()
            ->where('mass', true)
            ->where('created_at', '>', Carbon::now('UTC')->subMinutes(5))
            ->orderBy('created_at', 'desc')
            ->first();
        if ($mass) {
            $mass->append(['recipients_count'])->makeVisible(['recipients_count']);
        }

        return response()->json(['chats' => $chats, 'mass' => $mass]);
    }

    public function indexChat(User $user)
    {
        $current = auth()->user();
        $messages = $current->mailbox()->with('media')->wherePivot('party_id', $user->id)->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        $ids = [];
        $messages->map(function ($item) use ($current, &$ids) {
            $item->append('read');
            if ($item->user_id != $current->id && !$item->read) {
                $ids[] = $item->id;
            }
        });

        if (count($ids)) {
            DB::table('message_user')->whereIn('message_id', $ids)->where(function ($q) use ($current) {
                $q->where('user_id', $current->id)->orWhere('party_id', $current->id);
            })->update([
                'read' => 1
            ]);
            MessageReadEvent::dispatch($user, $current);
        }

        return response()->json([
            'party' => $user,
            'messages' => $messages
        ]);
    }

    public function storeMass(Request $request)
    {
        $this->authorize('mass', Message::class);

        $this->validate($request, [
            'message' => 'required|max:6000',
            'media' => 'nullable|file|mimes:' . config('misc.media.mimes') . '|max:' . config('misc.media.maxsize'),
            'price' => 'nullable|integer',
            'include' => 'array',
            'exclude' => 'nullable|array',
            'include.*' => 'integer',
            'exclude.*' => 'integer',
        ]);

        $user = auth()->user();

        $price = $request->input('price') * 100;

        $message = $user->messages()->create([
            'message' => $request['message'],
            'price' => $price ? $price : null,
            'mass' => true
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
            $message->media()->sync($media->keys());
        }

        MassMessageJob::dispatchAfterResponse($user, $message, $request['include'], $request->input('exclude', []));

        $message->refresh()->load('media');
        return response()->json($message);
    }

    public function store(User $user, Request $request)
    {
        $current = auth()->user();

        $this->validate($request, [
            'message' => 'required|max:6000',
            'media' => 'nullable|array|max:' . config('misc.post.media.max'),
            'price' => 'nullable|integer'
        ]);

        $price = $request->input('price') * 100;

        $message = $current->messages()->create([
            'message' => $request['message'],
            'price' => $price ? $price : null
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

        MessageJob::dispatchAfterResponse($message, $current, $user);

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
