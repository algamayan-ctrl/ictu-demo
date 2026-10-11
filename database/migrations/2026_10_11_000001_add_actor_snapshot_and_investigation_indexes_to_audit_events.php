<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Additive only. Existing audit rows are never rewritten: the new columns stay
 * NULL for historical events, which the Audit Trail displays without inventing
 * a past name or role.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_events', 'actor_name')) {
                $table->string('actor_name')->nullable()->after('actor_user_id');
            }

            if (! Schema::hasColumn('audit_events', 'actor_role')) {
                $table->string('actor_role', 50)->nullable()->after('actor_name');
            }

            // Full history of one record, in order.
            $table->index(['record_type', 'record_id', 'occurred_at'], 'audit_events_record_history_index');

            // Audience (operational/technical) scoping of the paginated Audit Trail.
            $table->index(['action_code', 'record_type'], 'audit_events_action_record_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex('audit_events_record_history_index');
            $table->dropIndex('audit_events_action_record_type_index');
            $table->dropColumn(['actor_name', 'actor_role']);
        });
    }
};
