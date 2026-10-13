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
        Schema::create('resident_csv_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('interval_minutes')->default(60);
            $table->string('atomicity', 20)->default('per_file');
            $table->timestamps();
        });

        $atomicity = (string) config('services.resident_csv.atomicity', 'per_file');

        DB::table('resident_csv_settings')->insert([
            'id' => 1,
            'interval_minutes' => max(1, (int) config('services.resident_csv.interval_minutes', 60)),
            'atomicity' => in_array($atomicity, ['per_file', 'all_files'], true) ? $atomicity : 'per_file',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_csv_settings');
    }
};
