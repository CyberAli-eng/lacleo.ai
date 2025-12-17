<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function billingContext(Request $request)
    {
        $email = strtolower(trim((string) $request->query('email')));
        if ($email === '') {
            return response()->json(['error' => 'EMAIL_REQUIRED'], 422);
        }
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            $user = User::where('email', 'like', '%'.$email.'%')->orderBy('email')->first();
        }
        if (! $user) {
            return response()->json(['error' => 'USER_NOT_FOUND'], 404);
        }

        $workspace = Workspace::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['id' => (string) strtolower(\Illuminate\Support\Str::ulid()), 'credit_balance' => 0, 'credit_reserved' => 0]
        );

        $tx = CreditTransaction::where('workspace_id', $workspace->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['id', 'amount', 'type', 'meta', 'created_at']);

        $resp = response()->json([
            'user' => ['id' => $user->id, 'email' => $user->email, 'name' => $user->name],
            'workspace' => ['id' => $workspace->id, 'balance' => (int) $workspace->credit_balance, 'reserved' => (int) $workspace->credit_reserved],
            'transactions' => $tx,
        ]);
        app(\App\Services\AuditLogger::class)->log('admin', (int) $request->user()->id, 'admin_billing_context', [
            'target_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        return $resp;
    }
}
