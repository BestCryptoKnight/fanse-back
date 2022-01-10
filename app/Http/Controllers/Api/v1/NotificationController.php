<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'desc')->paginate(config('misc.page.size'));
        $unviewed = [];
        foreach ($notifications as $n) {
            if (!$n->viewed) {
                $unviewed[] = $n->id;
            }
        }
        Notification::whereIn('id', $unviewed)->update([
            'viewed' => 1
        ]);

        return response()->json($notifications);
    }
}
