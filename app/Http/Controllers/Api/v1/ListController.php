<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $lists = $user->lists;
        $users = $user->listees()->paginate(config('misc.page.size'));
        return response()->json([
            'lists' => $lists,
            'users' => $users
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:191'
        ]);

        $list = auth()->user()->lists()->create([
            'title' => $request['title']
        ]);

        return response()->json($list);
    }

    public function add(User $user, int $list_id)
    {
        $status = false;
        $current = auth()->user();
        $entry = $current->listees()->where('listee_id', $user->id)->first();

        if (!$entry) {
            $status = true;
            $entry = $current->listees()->attach($user->id, ['list_ids' => [$list_id]]);
        } else {
            $ids = $entry->pivot->list_ids;
            if (in_array($list_id, $ids)) {
                $ids = array_diff($ids, [$list_id]);
            } else {
                $status = true;
                $ids[] = $list_id;
            }
            if (count($ids)) {
                $current->listees()->updateExistingPivot($user->id, ['list_ids' => $ids], false);
            } else {
                $current->listees()->detach([$user->id]);
            }
        }

        return response()->json(['status' => $status]);
    }
}
