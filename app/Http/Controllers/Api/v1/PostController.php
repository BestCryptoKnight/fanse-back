<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Storage;
use Log;
use DB;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Post::active();
        if ($request->input('q')) {
            $query->where('message', 'like', '%' . $request['q'] . '%');
        }

        $posts = $query->orderByRaw('IF(schedule IS NULL,created_at,schedule) desc')->paginate(config('misc.page.size'));
        return response()->json($posts);
    }

    public function user(User $user, Request $request)
    {
        $current = auth()->user();
        if ($current && ($current->id != $user->id) || !$current) {
            $type = Post::TYPE_ACTIVE;
        } else {
            $type = $request->input('type');
            if (!in_array($type, [Post::TYPE_ACTIVE, Post::TYPE_EXPIRED, Post::TYPE_SCHEDULED])) {
                $type = Post::TYPE_ACTIVE;
            }
        }

        $query = $user->posts();
        switch ($type) {
            case Post::TYPE_ACTIVE:
                $query->active();
                break;
            case Post::TYPE_EXPIRED:
                $query->expired();
                break;
            case Post::TYPE_SCHEDULED:
                $query->scheduled();
                break;
        }
        $posts = $query->orderByRaw('IF(schedule IS NULL,created_at,schedule) desc')->paginate(config('misc.page.size'));
        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $this->validate($request, [
            'message' => 'required|string|max:2000',
            'media' => 'nullable|array|max:' . config('misc.post.media.max'),
            'poll' => 'nullable|array|min:2|max:' . config('misc.post.poll.max'),
            'media.*' => 'array',
            'media.*.id' => 'integer',
            'media.*.screenshot' => 'nullable|integer',
            'poll.*' => 'string|max:191',
            'expires' => 'nullable|integer|min:1|max:' . config('misc.post.expire.max'),
            'schedule' => 'nullable|date',
            'price' => 'nullable|integer'
        ]);

        $user = auth()->user();
        $data = $request->only(['message', 'expires']);

        $schedule = $request->input('schedule');
        if ($schedule) {
            $schedule = new Carbon($schedule, 'UTC');
            if (!$schedule->copy()->subMinutes(15)->isFuture()) {
                return response()->json([
                    'message' => '',
                    'errors' => [
                        'schedule' => [__('errors.schedule-must-be-in-future')]
                    ]
                ], 422);
            }
        }

        $price = $request->input('price');
        if ($price) {
            if (!config('misc.payment.pricing.allow_paid_posts_for_paid_accounts') && !$user->isFree) {
                return response()->json([
                    'message' => '',
                    'errors' => [
                        'price' => [__('errors.only-free-can-paid-post')]
                    ]
                ], 422);
            }
            $price = $price * 100;
        }

        $post = $user->posts()->create([
            'message' => $request->input('message'),
            'expires' => $request->input('expires'),
            'schedule' => $schedule,
            'price' => $price,
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
            $post->media()->sync($media->keys());
        }

        $poll = $request->input('poll', []);
        foreach ($poll as $option) {
            $post->poll()->create([
                'option' => $option
            ]);
        }

        $post->refresh()->load(['media', 'poll']);
        return response()->json($post);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function show(Post $post)
    {
        if ($post->user_id == auth()->user()->id || auth()->user()->isAdmin) {
            $post->media->map(function ($item) {
                $item->append(['thumbs']);
            });
            $post->makeVisible(['schedule']);
        }
        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $this->validate($request, [
            'message' => 'required|string|max:2000',
            'media' => 'nullable|array|max:' . config('misc.post.media.max'),
            'poll' => 'nullable|array|min:2|max:' . config('misc.post.poll.max'),
            'media.*' => 'array',
            'media.*.id' => 'integer',
            'media.*.screenshot' => 'nullable|integer',
            'poll.*' => 'string|max:191',
            'expires' => 'nullable|integer|min:1|max:' . config('misc.post.expire.max'),
            'schedule' => 'nullable|date',
            'price' => 'nullable|integer'
        ]);

        $user = auth()->user();
        $data = $request->only(['message', 'expires']);

        $schedule = $request->input('schedule');
        if ($schedule) {
            $schedule = new Carbon($schedule, 'UTC');
            if (!$schedule->copy()->subMinutes(15)->isFuture()) {
                return response()->json([
                    'message' => '',
                    'errors' => [
                        'schedule' => [__('errors.schedule-must-be-in-future')]
                    ]
                ], 422);
            }
        }

        $price = $request->input('price');
        if ($price) {
            if (!config('misc.payment.pricing.allow_paid_posts_for_paid_accounts') && !$user->isFree) {
                return response()->json([
                    'message' => '',
                    'errors' => [
                        'price' => [__('errors.only-free-can-paid-post')]
                    ]
                ], 422);
            }
            $price = $price * 100;
        }

        $post->fill([
            'message' => $request->input('message'),
            'expires' => $request->input('expires'),
            'schedule' => $schedule,
            'price' => $price,
        ]);
        $post->save();

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
            $post->media()->sync($media->keys());
        }

        $poll = $request->input('poll', []);
        foreach ($post->poll as $p) {
            $p->delete();
        }
        foreach ($poll as $option) {
            $post->poll()->create([
                'option' => $option
            ]);
        }

        $post->refresh()->load(['media', 'poll']);
        return response()->json($post);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post  $post
     * @return \Illuminate\Http\Response
     */
    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json(['status' => true]);
    }

    public function like(Post $post, Request $request)
    {
        if (!$post->hasAccess) {
            abort(403);
        }

        $user = auth()->user();
        $res = $post->likes()->toggle([$user->id]);

        $status = count($res['attached']) > 0;
        if ($status) {
            $post->user->notifications()->firstOrCreate([
                'type' => Notification::TYPE_LIKE,
                'info' => [
                    'user_id' => $user->id,
                    'post_id' => $post->id
                ]
            ]);
        }
        $post->loadCount(['likes']);

        return response()->json(['is_liked' => $status, 'likes_count' => $post->likes_count]);
    }

    public function vote(Post $post, Poll $poll)
    {
        if (!$post->hasAccess) {
            abort(403);
        }

        $poll->votes()->attach(auth()->user()->id);
        $post->refresh();
        return response()->json($post);
    }
}
