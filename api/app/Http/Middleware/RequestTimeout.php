<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequestTimeout
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('testing')) {
            return $next($request);
        }
        $seconds = (int) env('REQUEST_TIMEOUT_SECONDS', 15);
        if ($seconds > 0) {
            @set_time_limit($seconds);
            @ini_set('max_execution_time', (string) $seconds);
        }

        return $next($request);
    }
}
