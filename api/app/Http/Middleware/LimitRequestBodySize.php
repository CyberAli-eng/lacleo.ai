<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LimitRequestBodySize
{
    public function handle(Request $request, Closure $next)
    {
        $maxKb = (int) env('MAX_REQUEST_BODY_KB', 64);
        $contentLength = (int) ($request->header('Content-Length') ?? 0);
        if ($contentLength > 0 && ($contentLength / 1024) > $maxKb) {
            return response()->json([
                'error' => 'REQUEST_TOO_LARGE',
                'message' => 'Request body exceeds limit',
                'limit_kb' => $maxKb,
            ], 413);
        }

        if ($contentLength === 0 && in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH'], true)) {
            $raw = $request->getContent(true);
            if ($raw !== '' && (strlen($raw) / 1024) > $maxKb) {
                return response()->json([
                    'error' => 'REQUEST_TOO_LARGE',
                    'message' => 'Request body exceeds limit',
                    'limit_kb' => $maxKb,
                ], 413);
            }
        }

        return $next($request);
    }
}
