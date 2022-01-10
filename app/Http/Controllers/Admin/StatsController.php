<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $totals = [
            'users' => User::count(),
            'posts' => Post::count(),
            'sales' => Payment::where('status', Payment::STATUS_COMPLETE)->sum('amount') * 1,
            'fees' => Payment::where('status', Payment::STATUS_COMPLETE)->sum(DB::raw('amount * (fee/100)')) * 1,
        ];


        $users = DB::table('users')->selectRaw('DATE(created_at) d, COUNT(DISTINCT id) c')->groupByRaw('DATE(created_at)');
        $posts = DB::table('posts')->selectRaw('DATE(IF(schedule IS NOT NULL, schedule, created_at)) d, COUNT(DISTINCT id) c')
            ->groupByRaw('DATE(IF(schedule IS NOT NULL, schedule, created_at))');
        $payments = DB::table('payments')->where('status', Payment::STATUS_COMPLETE)
            ->selectRaw('DATE(created_at) d, SUM(amount) c')->groupByRaw('DATE(created_at)');
        $fees = DB::table('payments')->where('status', Payment::STATUS_COMPLETE)
            ->selectRaw('DATE(created_at) d, SUM(amount * (fee/100)) c')->groupByRaw('DATE(created_at)');
        $now = Carbon::now('UTC');

        $period = $request->input('period');

        switch ($period) {
            case '2w':
                $users->where('created_at', '>=', $now->subWeeks(2));
                break;
            case '1w':
                $users->where('created_at', '>=', $now->subWeek());
                break;
            case '30d':
            default:
                $users->where('created_at', '>=', $now->subDays(30));
                $posts->where(DB::raw('IF(schedule IS NOT NULL, schedule, created_at)'), '>=', $now->subDays(30));
                $payments->where('created_at', '>=', $now->subDays(30));
                $fees->where('created_at', '>=', $now->subDays(30));
                break;
        }

        return response()->json([
            'users' => $users->get()->pluck('c', 'd'),
            'posts' => $posts->get()->pluck('c', 'd'),
            'payments' => $payments->get()->pluck('c', 'd'),
            'fees' => $fees->get()->pluck('c', 'd'),
            'totals' => $totals
        ]);
    }
}
