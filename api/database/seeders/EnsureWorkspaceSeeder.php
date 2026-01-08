<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class EnsureWorkspaceSeeder extends Seeder
{
    public function run()
    {
        // Get all users without workspaces
        $usersWithoutWorkspaces = User::whereDoesntHave('workspace')->get();

        foreach ($usersWithoutWorkspaces as $user) {
            Workspace::create([
                'id' => (string) Str::ulid(),
                'owner_user_id' => $user->id,
                'plan_id' => null,
                'credit_balance' => 100, // Give them 100 free credits
                'credit_reserved' => 0,
            ]);

            $this->command->info("Created workspace for user: {$user->email}");
        }

        if ($usersWithoutWorkspaces->isEmpty()) {
            $this->command->info('All users already have workspaces.');
        }
    }
}
