<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileCredits extends Command
{
    protected $signature = 'credits:reconcile {workspace_id}';

    protected $description = 'Recompute credit balance from ledger';

    public function handle(): int
    {
        $workspaceId = $this->argument('workspace_id');
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            $this->error('Workspace not found');

            return self::FAILURE;
        }

        $sum = CreditTransaction::where('workspace_id', $workspace->id)->sum('amount');

        DB::transaction(function () use ($workspace, $sum) {
            $workspace->update(['credit_balance' => (int) $sum]);
        });

        $this->info("Reconciled workspace {$workspaceId} to balance {$sum}");

        return self::SUCCESS;
    }
}
