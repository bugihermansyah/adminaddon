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
        Schema::create('resident_csv_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id');
            $table->string('source_key', 200);
            $table->string('status', 20)->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('deleted')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_key']);
            $table->index(['source_key', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_csv_sync_runs');
    }
};
