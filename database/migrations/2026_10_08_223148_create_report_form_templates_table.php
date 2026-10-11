<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_form_templates', function (Blueprint $table): void {
            $table->id();

            $table->string('report_key', 80);
            $table->unsignedInteger('version_no');

            $table->string('status', 20)->default('DRAFT');

            $table->string('form_code', 80)->nullable();
            $table->date('effectivity_date')->nullable();
            $table->string('revision_label', 50)->nullable();

            $table->string('title_template', 255);

            $table->json('configuration_json');

            $table->text('change_reason')->nullable();

            $table
                ->foreignId('configured_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('activated_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['report_key', 'version_no'],
                'report_form_templates_key_version_unique'
            );

            $table->index(
                ['report_key', 'status'],
                'report_form_templates_key_status_index'
            );
        });


        /*
         * Current official SPMU reference form.
         *
         * This becomes Version 1 and may later be superseded through
         * Configuration > Report Form Configuration.
         */
        $configuration = [
            'header' => [
                'show_logo' => true,
                'republic' => 'Republic of the Philippines',
                'institution' => 'CAMARINES SUR POLYTECHNIC COLLEGES',
                'location' => 'Nabua, Camarines Sur',
            ],

            'columns' => [
                [
                    'key' => 'control_no',
                    'label' => 'Control No.',
                    'visible' => true,
                    'order' => 1,
                    'group' => null,
                ],
                [
                    'key' => 'requesting_office',
                    'label' => 'Requesting Office/Department',
                    'visible' => true,
                    'order' => 2,
                    'group' => null,
                ],
                [
                    'key' => 'requested_equipment',
                    'label' => 'Requested Equipment',
                    'visible' => true,
                    'order' => 3,
                    'group' => null,
                ],
                [
                    'key' => 'dates_needed',
                    'label' => 'Dates Needed',
                    'visible' => true,
                    'order' => 4,
                    'group' => null,
                ],
                [
                    'key' => 'received_date',
                    'label' => 'Date',
                    'visible' => true,
                    'order' => 5,
                    'group' => 'RECEIVED',
                ],
                [
                    'key' => 'received_time',
                    'label' => 'Time',
                    'visible' => true,
                    'order' => 6,
                    'group' => 'RECEIVED',
                ],
                [
                    'key' => 'released_date',
                    'label' => 'Date',
                    'visible' => true,
                    'order' => 7,
                    'group' => 'RELEASED',
                ],
                [
                    'key' => 'released_time',
                    'label' => 'Time',
                    'visible' => true,
                    'order' => 8,
                    'group' => 'RELEASED',
                ],
                [
                    'key' => 'total_time_allocated',
                    'label' => 'Total Time Allocated',
                    'visible' => true,
                    'order' => 9,
                    'group' => null,
                ],
                [
                    'key' => 'remarks',
                    'label' => 'Remarks',
                    'visible' => true,
                    'order' => 10,
                    'group' => null,
                ],
            ],

            'formula' => [
                'enabled' => true,
                'label' => 'Formula',
                'numerator_label' => 'Number of requests responded on time',
                'denominator_label' => 'Total number of requests',
                'target_percent' => 100,
                'met_label' => 'MET',
                'not_met_label' => 'NOT MET',
            ],

            'signatories' => [
                'prepared_by_label' => 'Prepared by',
                'prepared_by_name' => 'JU-AN C. ADOPTANTE',
                'prepared_by_designation' => 'Administrative Assistant II',

                'noted_by_label' => 'Noted by',
                'noted_by_name' => 'ANGELICA P. REGONDOLA, PhD',
                'noted_by_designation' => 'Admin Officer V / SPMU Head',

                'show_date' => true,
            ],

            'page' => [
                'size' => 'A4',
                'orientation' => 'LANDSCAPE',
            ],
        ];


        DB::table('report_form_templates')->insert([
            'report_key' => 'SUMMARY_REQUESTS_EQUIPMENT',
            'version_no' => 1,
            'status' => 'ACTIVE',

            'form_code' => 'CSPC-F-SPMU-22',
            'effectivity_date' => '2025-08-01',
            'revision_label' => 'Rev. 2',

            'title_template' =>
                'Summary of Requests for Equipment for the month of {month_year}',

            'configuration_json' => json_encode(
                $configuration,
                JSON_UNESCAPED_SLASHES
            ),

            'change_reason' =>
                'Initial configuration based on the current official SPMU report form.',

            'configured_by_user_id' => null,
            'activated_at' => now(),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_form_templates');
    }
};