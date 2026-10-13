<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // These default credentials are for local development only.
        if (! app()->environment('local')) {
            return;
        }

        $email = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');

        if (DB::table('users')->where('email', $email)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Admin',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(env('FILAMENT_ADMIN_PASSWORD', 'admin')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep user records if this data migration is rolled back.
    }
};
