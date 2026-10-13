<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_no', 80)->nullable()->change();
            $table->string('employment_type', 30)->nullable()->change();
        });

        Schema::create('borrower_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->enum('registration_type', ['EMPLOYEE', 'EXTERNAL']);
            $table->string('company_name')->nullable();
            $table->foreignId('valid_id_file_id')->nullable()->constrained('stored_files')->restrictOnDelete();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING')->index();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('borrower_registrations') && DB::table('borrower_registrations')->exists()) {
            throw new RuntimeException('Registration history must be preserved. Use an approved data migration before rollback.');
        }
        // Restoring employee-only constraints must never destroy external accounts.
        if (DB::table('users')->whereNull('employee_no')->orWhereNull('employment_type')->exists()) {
            throw new RuntimeException('External accounts must be preserved. Resolve nullable user records through an approved data migration before rollback.');
        }

        Schema::dropIfExists('borrower_registrations');
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_no', 80)->nullable(false)->change();
            $table->string('employment_type', 30)->nullable(false)->change();
        });
    }
};
