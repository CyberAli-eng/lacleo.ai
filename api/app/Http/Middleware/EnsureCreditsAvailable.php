<?php

namespace App\Http\Middleware;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureCreditsAvailable
{
    public function handle(Request $request, Closure $next, string $category, int $required)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }
        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['id' => (string) strtolower(\Illuminate\Support\Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        // Admins bypass credit enforcement
        $adminEmails = array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', ''))));
        $isAdmin = in_array(strtolower($user->email ?? ''), array_map('strtolower', $adminEmails), true);
        if ($isAdmin) {
            return $next($request);
        }

        $requestId = $request->header('request_id');
        if ($requestId) {
            $exists = CreditTransaction::where('workspace_id', $workspace->id)
                ->where('type', 'spend')
                ->where('meta->request_id', $requestId)
                ->exists();
            if ($exists) {
                return $next($request);
            }
        }

        // Per-contact idempotency for reveal endpoints
        $contactId = (string) $request->input('contact_id', '');
        if ($contactId && in_array($category, ['reveal_email', 'reveal_phone'], true)) {
            $exists = CreditTransaction::where('workspace_id', $workspace->id)
                ->where('type', 'spend')
                ->where('meta->category', $category)
                ->where('meta->contact_id', $contactId)
                ->exists();
            if ($exists) {
                return $next($request);
            }
        }

        $insufficient = ($workspace->credit_balance ?? 0) < $required;
        if ($insufficient) {
            return response()->json([
                'error' => 'INSUFFICIENT_CREDITS',
                'balance' => (int) ($workspace->credit_balance ?? 0),
                'needed' => (int) $required,
                'short_by' => max(0, $required - (int) ($workspace->credit_balance ?? 0)),
            ], 402);
        }

        DB::transaction(function () use ($workspace, $required, $category, $requestId) {
            $ws = Workspace::where('id', $workspace->id)->lockForUpdate()->first();
            if (($ws->credit_balance ?? 0) < $required) {
                abort(402, 'Insufficient credits');
            }
            $ws->update(['credit_balance' => $ws->credit_balance - $required]);
            CreditTransaction::create([
                'workspace_id' => $ws->id,
                'amount' => -$required,
                'type' => 'spend',
                'meta' => ['category' => $category, 'request_id' => $requestId],
            ]);
        });

        return $next($request);
    }
}
