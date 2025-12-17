<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function log(string $actorType, int $actorId, string $action, array $meta = []): void
    {
        $rid = bin2hex(random_bytes(12));
        $payload = [
            'timestamp' => now()->toIso8601String(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'request_id' => $rid,
            'target_id' => $meta['target_id'] ?? null,
            'workspace_id' => $meta['workspace_id'] ?? null,
            'meta' => \App\Http\Middleware\RedactSensitiveMiddleware::redactArray($meta),
        ];
        $line = json_encode($payload);
        Log::channel(config('logging.channels.audit') ? 'audit' : 'stack')->info($line);
        if (is_dir(storage_path('logs'))) {
            file_put_contents(storage_path('logs/audit.log'), $line."\n", FILE_APPEND);
        }
    }
}
