<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CustomList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ListController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $original = collect([
            CustomList::bookmarks($user)
        ]);

        $lists = $original->concat($user->lists()->with('user')->get());
        $lists->map(function ($model) {
            $model->append('listees_count');
        });

        $lists = $lists->toArray();
        $lists[] = [
            'id' => CustomList::DEFAULT_FOLLOWING,
            'listees_count' => $user->following()->count()
        ];

        return response()->json([
            'lists' => $lists
        ]);
    }

    public function indexMessage()
    {
        $user = auth()->user();
        $lists = [];

        // recent
        $recent = User::where('id', '<>', $user->id)->whereIn('id', function ($q) use ($user) {
            $q->select('user_id')->from('messages')->where('created_at', '>', Carbon::now('UTC')->subDay(7))->where('mass', 0)->whereIn('id', function ($q) use ($user) {
                $q->select('message_id')->from('message_user')->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('party_id', $user->id);
                });
            });
        })->count();
        $lists[] = [
            'id' => CustomList::DEFAULT_RECENT,
            'listees_count' => $recent
        ];

        // fans
        $lists[] = [
            'id' => CustomList::DEFAULT_FANS,
            'listees_count' => $user->followers()->count()
        ];

        // following
        $lists[] = [
            'id' => CustomList::DEFAULT_FOLLOWING,
            'listees_count' => $user->subscriptions()->count()
        ];

        // custom
        $custom = $user->lists()->with('user')->get();
        $custom->map(function ($model) {
            $model->append('listees_count');
        });

        $lists = array_merge($lists, $custom->toArray());

        return response()->json([
            'lists' => $lists
        ]);
    }

    public function indexUser(User $user)
    {
        $current = auth()->user();

        $original = collect([
            CustomList::bookmarks($current)
        ]);

        $lists = $original->concat($current->lists()->with('user')->get());
        $lists->map(function ($model) {
            $model->append('listees_count');
        });

        $contains = $current->listees()->where('lists.listee_id', $user->id)->first();

        return response()->json([
            'lists' => $lists,
            'contains' => $contains ? $contains->pivot->list_ids : []
        ]);
    }

    public function indexList(int $id)
    {
        $list = ['id' => $id];

        $user = auth()->user();

        switch ($id) {
            case CustomList::DEFAULT_FOLLOWING:
                $query = $user->following();
                break;
            default:
                $query = $user->listees()->whereJsonContains('list_ids', $id);
                break;
        }

        $users = $query->paginate(config('misc.page.size'));
        return response()->json([
            'list' => $list,
            'users' => $users
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
