<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CsrfGuard
{
    public function handle(Request $request, Closure $next)
    {
        if (! env('CSRF_GUARD_ENABLED', true)) {
            return $next($request);
        }

        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if ($request->is('api/v1/billing/webhook/stripe')) {
            return $next($request);
        }

        $origin = (string) $request->headers->get('Origin', '');
        $stateful = (array) config('sanctum.stateful');
        $host = '';
        if ($origin !== '') {
            $parts = parse_url($origin);
            $host = isset($parts['host']) ? $parts['host'] : '';
        }

        $hasCookieToken = $request->cookies->has('XSRF-TOKEN');
        $requiresHeader = $hasCookieToken && ($host !== '' ? in_array($host, $stateful, true) : false);

        if ($requiresHeader) {
            $headerToken = (string) $request->headers->get('X-XSRF-TOKEN', '');
            if ($headerToken === '') {
                return response()->json([
                    'error' => 'CSRF_TOKEN_MISSING',
                    'message' => 'Missing X-XSRF-TOKEN header',
                ], 419);
            }
        }

        return $next($request);
    }
}
