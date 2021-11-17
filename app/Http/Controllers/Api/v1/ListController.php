<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        return response()->json([
            'lists' => $user->lists
        ]);
    }

    public function indexUser(User $user)
    {
        $lists = auth()->user()->lists;
        $contains = auth()->user()->listees()->where('lists.user_id', $user->id)->first();

        return response()->json([
            'lists' => $user->lists,
            'contains' => $contains ? $contains->pivot->list_ids : []
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:191'
        ]);

        $user = auth()->user();

        if ($user->lists()->where('title', $request['title'])->exists()) {
            return response()->json([
                'message' => '',
                'errors' => [
                    'title' => [__('errors.list-title-taken')]
                ]
            ], 422);
        }

        $list = $user->lists()->create([
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
                $ids = array_values(array_diff($ids, [$list_id]));
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
