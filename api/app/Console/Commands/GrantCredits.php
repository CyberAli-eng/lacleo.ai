<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GrantCredits extends Command
{
    protected $signature = 'credits:grant {workspace_id} {amount} {reason?}';

    protected $description = 'Grant credits to a workspace';

    public function handle(): int
    {
        $workspaceId = $this->argument('workspace_id');
        $amount = (int) $this->argument('amount');
        $reason = $this->argument('reason') ?? 'manual_grant';

        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            $this->error('Workspace not found');

            return self::FAILURE;
        }

        DB::transaction(function () use ($workspace, $amount, $reason) {
            $workspace->increment('credit_balance', $amount);
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'amount' => $amount,
                'type' => 'adjustment',
                'meta' => ['reason' => $reason],
            ]);
        });

        $this->info("Granted {$amount} credits to workspace {$workspaceId}");

        return self::SUCCESS;
    }
}
