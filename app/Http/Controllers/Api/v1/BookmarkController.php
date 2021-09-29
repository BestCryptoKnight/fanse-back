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
        $status = $res['attached'] > 0;
        return response()->json(['status' => $status]);
    }
}
