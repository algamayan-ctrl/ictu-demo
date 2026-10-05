<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_verification_otps')) {
            Schema::create('email_verification_otps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('email', 255);
                $table->string('code_hash', 255);
                $table->timestamp('expires_at')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();

                $table->unique('user_id', 'email_verification_otps_user_unique');
            });
        }

        /*
         * OTP verification does not place a system URL inside the email,
         * therefore the old shared verification-address setting is obsolete.
         */
        if (Schema::hasTable('system_settings')) {
            DB::table('system_settings')
                ->where('setting_key', 'email_verification_public_url')
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_otps');

        if (Schema::hasTable('system_settings')) {
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
    }
};