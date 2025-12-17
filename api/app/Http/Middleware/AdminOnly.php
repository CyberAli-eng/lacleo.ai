<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $adminEmails = array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))));
        $isAdmin = in_array(strtolower($user->email ?? ''), array_map('strtolower', $adminEmails), true);

        if (! $isAdmin) {
            abort(403);
        }

        return $next($request);
    }
}
