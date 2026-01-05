<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class EnsureAdminUserSeeder extends Seeder
{
    public function run()
    {
        // 1. Get Admin Emails from Env
        $envEmails = (string) env('ADMIN_EMAILS', '');
        $adminEmails = array_filter(array_map('trim', explode(',', $envEmails)));

        if (empty($adminEmails)) {
            $this->command->warn('No ADMIN_EMAILS defined in .env. Skipping admin creation.');
            return;
        }

        // Use the first email as the primary admin for seeding
        $email = $adminEmails[0];
        $user = User::where('email', $email)->first();
        if (!$user) {
            User::create([
                'id' => (string) Str::ulid(),
                'name' => 'Admin User',
                'email' => $email,
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]);
        }
    }
}
