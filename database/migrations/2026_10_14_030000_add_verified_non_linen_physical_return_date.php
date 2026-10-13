<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_transactions', function (Blueprint $table): void {
            // Existing timestamps and historical return dates are never rewritten.
            $table->date('physical_return_date')->nullable();
            $table->string('physical_return_source', 40)->nullable();
            $table->text('physical_return_reason')->nullable();
            $table->foreignId('physical_return_evidence_file_id')->nullable()
                ->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('physical_return_evidence_file_id');
            $table->dropColumn([
                'physical_return_date',
                'physical_return_source',
                'physical_return_reason',
            ]);
        });
    }
};
