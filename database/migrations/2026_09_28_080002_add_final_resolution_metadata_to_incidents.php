<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->foreignId('resolved_by_user_id')->nullable()
                ->after('rslddp_attachment_file_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by_user_id');
            $table->timestamp('f2f_processing_completed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['resolved_at', 'f2f_processing_completed_at']);
        });
    }
};
