<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
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
        $comments = $post->comments()->topLevel()->withCount(['replies'])->orderBy('created_at', 'asc')->paginate(config('misc.page.size'));
        return response()->json($comments);
    }

    public function replies(Comment $comment)
    {
        $comments = $comment->replies()->orderBy('created_at', 'asc')->paginate(config('misc.page.comments'));
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
        // TODO: allow only comment on a post they have access to
        $this->validate($request, [
            'message' => 'required|string|max:191',
            'comment_id' => 'nullable|integer|exists:comments,id'
        ]);

        $comment = $post->comments()->create([
            'user_id' => auth()->user()->id,
            'message' => $request->input('message'),
            'comment_id' => $request->input('comment_id')
        ]);

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
        if ($comment->user_id == auth()->user()->id) {
            $comment->delete();
        }
        return response()->json(['status' => true]);
    }

    public function like(Comment $comment, Request $request)
    {
        $user = auth()->user();
        $res = $comment->likes()->toggle([$user->id]);
        return response()->json(['status' => count($res['attached']) > 0]);
    }
}
