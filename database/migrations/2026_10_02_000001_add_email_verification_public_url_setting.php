<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'email_verification_public_url'],
            [
                'value_json' => null,
                'data_type' => 'STRING',
                'group_code' => 'ICTU',
                'description' => 'Shared SPMU-ACPMP address used in institutional email verification links so recipients can verify from another device.',
                'status' => 'ACTIVE',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->where('setting_key', 'email_verification_public_url')
                ->delete();
        }
    }
};
