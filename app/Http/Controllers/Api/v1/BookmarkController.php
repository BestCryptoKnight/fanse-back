<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function add(Post $post)
    {
        $res = auth()->user()->bookmarks()->toggle([$post->id]);
        $status = count($res['attached']) > 0;
        return response()->json(['is_bookmarked' => $status]);
    }

    public function index()
    {
        $posts = auth()->user()->bookmarks()->orderBy('bookmarks.created_at', 'desc')->paginate(config('misc.page.size'));
        return response()->json($posts);
    }
}
