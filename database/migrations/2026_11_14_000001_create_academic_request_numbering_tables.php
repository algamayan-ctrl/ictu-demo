<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_request_number_counters', function (Blueprint $table): void {
            $table->string('academic_year', 20)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('request_number_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->json('counter_snapshot');
            $table->string('status', 20);
            $table->timestamps();
        });
        Schema::create('request_number_assignments', function (Blueprint $table): void {
            $table->foreignId('request_id')->primary()->constrained('borrowing_requests')->restrictOnDelete();
            $table->string('academic_year', 20);
            $table->unsignedBigInteger('sequence_number');
            $table->string('number', 50)->unique();
            $table->timestamp('assigned_at');
            $table->uuid('migration_batch')->nullable();
            $table->unique(['academic_year', 'sequence_number'], 'academic_request_sequence_unique');
        });
        Schema::create('request_number_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('borrowing_requests')->restrictOnDelete();
            $table->string('alias', 50)->unique();
            $table->uuid('migration_batch')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('request_number_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->foreignId('request_id')->constrained('borrowing_requests')->restrictOnDelete();
            $table->string('old_number', 50);
            $table->string('new_number', 50);
        });
    }

    public function down(): void
    {
        if (DB::table('request_number_assignments')->exists() || DB::table('request_number_batches')->where('status', 'APPLIED')->exists()) {
            throw new RuntimeException('Reverse the validated Control No. mapping before rolling back its schema.');
        }
        foreach (['request_number_changes', 'request_number_aliases', 'request_number_assignments', 'request_number_batches', 'academic_request_number_counters'] as $table) Schema::dropIfExists($table);
    }
};
