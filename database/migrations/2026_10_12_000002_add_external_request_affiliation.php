<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowing_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('accountable_unit_id')->nullable()->change();
        });
        Schema::table('request_versions', function (Blueprint $table): void {
            $table->enum('affiliation_type', ['EMPLOYEE', 'EXTERNAL'])->nullable();
            $table->string('external_company_name')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('borrowing_requests')->whereNull('accountable_unit_id')->exists()
            || DB::table('request_versions')->whereNotNull('affiliation_type')->orWhereNotNull('external_company_name')->exists()) {
            throw new RuntimeException('Request affiliation history must be preserved. Use a reviewed data-preserving migration before rollback.');
        }
        Schema::table('request_versions', function (Blueprint $table): void {
            $table->dropColumn(['affiliation_type', 'external_company_name']);
        });
        Schema::table('borrowing_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('accountable_unit_id')->nullable(false)->change();
        });
    }
};
