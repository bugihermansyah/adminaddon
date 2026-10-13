<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resident_csv_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 200)->unique();
            $table->text('url');
            $table->timestamp('next_sync_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->unsignedInteger('last_rows')->default(0);
            $table->unsignedInteger('last_added')->default(0);
            $table->unsignedInteger('last_updated')->default(0);
            $table->unsignedInteger('last_deleted')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_csv_sources');
    }
};
