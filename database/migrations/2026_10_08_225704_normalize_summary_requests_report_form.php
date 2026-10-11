<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Corrects the original Version 1 seed of CSPC-F-SPMU-22 on databases that
 * ran the earlier seed ("Date/s Needed", "... for the {period}").
 *
 * Revisions are immutable records: only the untouched system seed
 * (Version 1, never configured by a user, still carrying the old title) is
 * corrected. Any Draft, Active or Archived revision saved through Report
 * Form Configuration is left exactly as it was approved.
 */
return new class extends Migration
{
    private const OLD_TITLE = 'Summary of Requests for Equipment for the {period}';

    private const NEW_TITLE = 'Summary of Requests for Equipment for the month of {month_year}';

    public function up(): void
    {
        if (! Schema::hasTable('report_form_templates')) {
            return;
        }

        $seed = DB::table('report_form_templates')
            ->where('report_key', 'SUMMARY_REQUESTS_EQUIPMENT')
            ->where('version_no', 1)
            ->whereNull('configured_by_user_id')
            ->where('title_template', self::OLD_TITLE)
            ->first();

        if (! $seed) {
            return;
        }

        $configuration = json_decode((string) $seed->configuration_json, true);
        $configuration = is_array($configuration) ? $configuration : [];

        $columns = is_array($configuration['columns'] ?? null) ? $configuration['columns'] : [];

        foreach ($columns as &$column) {
            if (($column['key'] ?? null) === 'dates_needed' && ($column['label'] ?? null) === 'Date/s Needed') {
                $column['label'] = 'Dates Needed';
            }
        }

        unset($column);

        $configuration['columns'] = $columns;

        DB::table('report_form_templates')
            ->where('id', $seed->id)
            ->update([
                'title_template' => self::NEW_TITLE,
                'configuration_json' => json_encode($configuration, JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: rolling back must not rewrite revision history.
    }
};
