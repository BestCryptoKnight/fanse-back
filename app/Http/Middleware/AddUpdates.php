<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

class AddUpdates
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            $user = auth()->user();
            if ($user && $user instanceof User) {
                $user->loadCount(['notificationsNew', 'mailboxNew']);
                $content = $response->getData(true);
                $content['updates'] = [
                    'notifications' => $user->notifications_new_count,
                    'messages' => $user->mailbox_new_count
                ];
                $response->setData($content);
            }
        }
        return $response;
    }
}
