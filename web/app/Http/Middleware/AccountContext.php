<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $request->session()->put('current_user', [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => $user->role, 'persona' => 'Thành viên', 'avatar' => '', 'sourceProfileId' => null,
            ]);
            if ($user->app_user_id) {
                $request->session()->put('appUserId', $user->app_user_id);
            } else {
                $request->session()->forget('appUserId');
            }
        } else {
            $request->session()->forget(['current_user', 'appUserId', 'api_identities']);
        }

        return $next($request);
    }
}
