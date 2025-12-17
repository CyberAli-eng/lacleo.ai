<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class EnsureAdminUserSeeder extends Seeder
{
    public function run()
    {
        $email = 'shaizqurashi12345@gmail.com';
        $user = User::where('email', $email)->first();
        if (! $user) {
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
