<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrower_registrations', function (Blueprint $table): void {
            $table->timestamp('submitted_for_review_at')->nullable()->index();
        });
        // Preserve existing verified review history without resending notifications.
        DB::table('borrower_registrations')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('users')->whereColumn('users.id', 'borrower_registrations.user_id')
                ->whereNotNull('users.email_verified_at');
        })->update(['submitted_for_review_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('borrower_registrations', function (Blueprint $table): void {
            $table->dropColumn('submitted_for_review_at');
        });
    }
};
