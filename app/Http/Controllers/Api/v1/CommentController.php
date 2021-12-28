<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Post $post)
    {
        if (!$post->hasAccess) {
            abort(403);
        }

        $comments = $post->comments()->orderBy('created_at', 'asc')->paginate(config('misc.page.size'));
        return response()->json($comments);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Post $post, Request $request)
    {
        if (!$post->hasAccess) {
            abort(403);
        }

        $this->validate($request, [
            'message' => 'required|string|max:191',
        ]);

        $comment = $post->comments()->create([
            'user_id' => auth()->user()->id,
            'message' => $request->input('message')
        ]);

        $post->user->notifications()->create([
            'type' => Notification::TYPE_COMMENT,
            'info' => [
                'comment_id' => $comment->id,
                'user_id' => $comment->user_id,
                'post_id' => $post->id
            ]
        ]);

        $comment->load('user');

        return response()->json($comment);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Comment  $comment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);
        $comment->delete();
        return response()->json(['status' => true]);
    }

    public function like(Comment $comment, Request $request)
    {
        if (!$comment->post->hasAccess) {
            abort(403);
        }

        $user = auth()->user();
        $res = $comment->likes()->toggle([$user->id]);

        $status = count($res['attached']) > 0;
        if ($status) {
            $comment->post->user->notifications()->firstOrCreate([
                'type' => Notification::TYPE_COMMENT_LIKE,
                'info' => [
                    'user_id' => $user->id,
                    'comment_id' => $comment->id,
                    'post_id' => $comment->post_id,
                ]
            ]);
        }

        $comment->loadCount(['likes']);

        return response()->json(['is_liked' => $status, 'likes_count' => $comment->likes_count]);
    }
}
