<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laundry Area operations are outside SPMU authority. The signed Laundry
     * Form's actual Received By date remains on laundry_jobs.worker_received_at;
     * only the former SPMU date-authorization mechanism is removed.
     */
    public function up(): void
    {
        if (Schema::hasTable('laundry_jobs')) {
            Schema::table('laundry_jobs', function (Blueprint $table): void {
                if (Schema::hasColumn('laundry_jobs', 'receipt_date_reviewed_by_user_id')) {
                    $table->dropConstrainedForeignId('receipt_date_reviewed_by_user_id');
                }
            });

            Schema::table('laundry_jobs', function (Blueprint $table): void {
                foreach ([
                    'receipt_date_status',
                    'receipt_date_reviewed_at',
                    'receipt_date_review_note',
                ] as $column) {
                    if (Schema::hasColumn('laundry_jobs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('laundry_receiving_days');
    }

    public function down(): void
    {
        if (Schema::hasTable('laundry_jobs')) {
            Schema::table('laundry_jobs', function (Blueprint $table): void {
                if (! Schema::hasColumn('laundry_jobs', 'receipt_date_status')) {
                    $table->string('receipt_date_status', 40)->nullable()->after('worker_received_at');
                }
                if (! Schema::hasColumn('laundry_jobs', 'receipt_date_reviewed_by_user_id')) {
                    $table->foreignId('receipt_date_reviewed_by_user_id')->nullable()
                        ->after('receipt_date_status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('laundry_jobs', 'receipt_date_reviewed_at')) {
                    $table->timestamp('receipt_date_reviewed_at')->nullable()->after('receipt_date_reviewed_by_user_id');
                }
                if (! Schema::hasColumn('laundry_jobs', 'receipt_date_review_note')) {
                    $table->string('receipt_date_review_note', 500)->nullable()->after('receipt_date_reviewed_at');
                }
            });
        }

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
    }
};
