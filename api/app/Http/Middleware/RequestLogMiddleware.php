<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $next($request);
        }
        $incomingId = $request->header('X-Request-ID') ?: $request->header('request_id');
        $rid = (string) ($incomingId ?: bin2hex(random_bytes(12)));
        $request->headers->set('X-Request-ID', $rid);
        $request->headers->set('request_id', $rid);
        $start = microtime(true);
        $userId = optional($request->user())->id;
        $workspaceId = $request->attributes->get('workspace_id');
        $route = $request->route();
        $action = $route ? ($route->getActionName() ?: '') : '';
        $method = $request->getMethod();
        $uri = $request->getPathInfo();
        $ip = $request->ip();
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            $payload = [
                'timestamp' => now()->toIso8601String(),
                'request_id' => $rid,
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'method' => $method,
                'uri' => $uri,
                'route' => $action,
                'status' => 500,
                'latency_ms' => $latency,
                'response_size' => 0,
                'meta' => [
                    'query' => \App\Http\Middleware\RedactSensitiveMiddleware::redactString((string) $request->getContent()),
                    'params' => \App\Http\Middleware\RedactSensitiveMiddleware::redactArray($request->all()),
                    'ip' => $ip,
                ],
            ];
            try {
                Log::channel(config('logging.channels.structured') ? 'structured' : 'stack')->debug(json_encode($payload));
                if (class_exists(\App\Services\ErrorReporter::class)) {
                    app(\App\Services\ErrorReporter::class)->reportException($e, $request);
                }
            } catch (\Throwable $loggingError) {
                // Squelch logging errors to prevent hiding the original exception
            }
            throw $e;
        }
        $latency = (int) ((microtime(true) - $start) * 1000);
        $size = 0;
        try {
            if (method_exists($response, 'getContent')) {
                $size = (int) strlen($response->getContent());
            }
        } catch (\Throwable $e) {
            $size = 0;
        }
        $payload = [
            'timestamp' => now()->toIso8601String(),
            'request_id' => $rid,
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'method' => $method,
            'uri' => $uri,
            'route' => $action,
            'status' => $response->getStatusCode(),
            'latency_ms' => $latency,
            'response_size' => $size,
            'meta' => [
                'query' => \App\Http\Middleware\RedactSensitiveMiddleware::redactString((string) $request->getContent()),
                'params' => \App\Http\Middleware\RedactSensitiveMiddleware::redactArray($request->all()),
                'ip' => $ip,
            ],
        ];
        Log::channel(config('logging.channels.structured') ? 'structured' : 'stack')->info(json_encode($payload));
        $response->headers->set('X-Request-ID', $rid);
        $response->headers->set('request_id', $rid);

        return $response;
    }
}
