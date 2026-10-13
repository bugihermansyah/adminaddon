<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('username')->nullable()->unique()->after('name');
            });
        }

        $adminEmail = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
        $adminUsername = env('FILAMENT_ADMIN_USERNAME', 'admin');

        if (
            DB::table('users')->where('email', $adminEmail)->exists()
            && ! DB::table('users')->where('username', $adminUsername)->exists()
        ) {
            DB::table('users')
                ->where('email', $adminEmail)
                ->whereNull('username')
                ->update(['username' => $adminUsername]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            });
        }
    }
};
