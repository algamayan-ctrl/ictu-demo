<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A failed delivery keeps delivery_status = FAILED forever for audit -
     * these columns are the explicit, non-guessable record of whether ICTU
     * has since resolved it, never inferred from age or any other heuristic.
     */
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->timestamp('resolved_at')->nullable()->after('provider_response');
            $table->foreignId('resolved_by_user_id')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable()->after('resolved_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['resolved_at', 'resolution_note']);
        });
    }
};
