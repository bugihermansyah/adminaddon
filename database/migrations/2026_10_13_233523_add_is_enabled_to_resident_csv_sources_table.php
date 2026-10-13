<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('resident_csv_sources', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->after('url');
        });

        foreach (config('services.resident_csv.urls', []) as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $sourceKey = is_string($path) ? basename($path) : '';

            if (preg_match('/^[A-Za-z0-9._-]+\.csv$/i', $sourceKey) !== 1) {
                continue;
            }

            DB::table('resident_csv_sources')->insertOrIgnore([
                'source_key' => $sourceKey,
                'url' => $url,
                'is_enabled' => true,
                'next_sync_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resident_csv_sources', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
