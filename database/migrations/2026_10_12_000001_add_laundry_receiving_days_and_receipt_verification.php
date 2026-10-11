<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only.
 *
 * - laundry_receiving_days: dates on which the SPMU Head/Admin authorized the
 *   Laundry Area to receive returned linen. Separate from the SPMU
 *   Operational Calendar on purpose: an authorized Laundry date never opens
 *   the SPMU Office and never affects equipment pickup, release or return.
 *   Rows are never deleted; a revocation is recorded on the row and every
 *   change is written to the Audit Trail.
 * - laundry_jobs: how the signed Laundry Received date was checked against
 *   the receiving calendars, and an Action Officer's confirmation that the
 *   linen was not returned. All columns are nullable, so existing rows keep
 *   their current meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laundry_receiving_days')) {
            Schema::create('laundry_receiving_days', function (Blueprint $table): void {
                $table->id();
                $table->date('receiving_date')->unique();
                $table->boolean('is_authorized')->default(true);
                $table->string('reason', 500);
                $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('authorized_at')->nullable();
                $table->string('revocation_reason', 500)->nullable();
                $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('laundry_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('laundry_jobs', 'receipt_date_status')) {
                $table->string('receipt_date_status', 40)->nullable()->after('worker_received_at');
            }
            if (! Schema::hasColumn('laundry_jobs', 'receipt_date_reviewed_by_user_id')) {
                $table->foreignId('receipt_date_reviewed_by_user_id')->nullable()->after('receipt_date_status')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('laundry_jobs', 'receipt_date_reviewed_at')) {
                $table->timestamp('receipt_date_reviewed_at')->nullable()->after('receipt_date_reviewed_by_user_id');
            }
            if (! Schema::hasColumn('laundry_jobs', 'receipt_date_review_note')) {
                $table->string('receipt_date_review_note', 500)->nullable()->after('receipt_date_reviewed_at');
            }
            if (! Schema::hasColumn('laundry_jobs', 'unreturned_confirmed_by_user_id')) {
                $table->foreignId('unreturned_confirmed_by_user_id')->nullable()->after('receipt_date_review_note')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('laundry_jobs', 'unreturned_confirmed_at')) {
                $table->timestamp('unreturned_confirmed_at')->nullable()->after('unreturned_confirmed_by_user_id');
            }
            if (! Schema::hasColumn('laundry_jobs', 'unreturned_confirmation_reason')) {
                $table->string('unreturned_confirmation_reason', 500)->nullable()->after('unreturned_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('laundry_jobs', function (Blueprint $table): void {
            foreach (['receipt_date_reviewed_by_user_id', 'unreturned_confirmed_by_user_id'] as $column) {
                if (Schema::hasColumn('laundry_jobs', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('laundry_jobs', function (Blueprint $table): void {
            foreach ([
                'receipt_date_status',
                'receipt_date_reviewed_at',
                'receipt_date_review_note',
                'unreturned_confirmed_at',
                'unreturned_confirmation_reason',
            ] as $column) {
                if (Schema::hasColumn('laundry_jobs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('laundry_receiving_days');
    }
};
