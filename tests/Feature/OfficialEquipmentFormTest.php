<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\AuditEvent;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\ReportFormTemplate;
use App\Models\RequestVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\OfficialEquipmentSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialEquipmentFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_spmu_head_can_view_or_export_official_form(): void
    {
        $borrower = User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
        $this->actingAs($borrower)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.official.equipment'))->assertForbidden();
        $this->get(route('reports.official.equipment.export', ['format' => 'pdf']))->assertForbidden();
    }

    public function test_head_preview_is_system_based_and_print_carries_signature_spaces(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $session = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        // The preview is application UI: no paper header, seal, signature block or page footer.
        $session->get(route('reports.official.equipment', $query))
            ->assertOk()
            ->assertSee('Report preview')
            ->assertSee('aria-label="Report summary values"', false)
            ->assertSee('Not evaluated')
            ->assertSee('Generate Official Report')
            ->assertSee('Print Official Form')
            ->assertDontSee('Republic of the Philippines')
            ->assertDontSee('class="institutional-head"', false)
            ->assertDontSee('alt="CSPC seal"', false)
            ->assertDontSee('Date: __________________')
            ->assertDontSee('JU-AN C. ADOPTANTE')
            ->assertDontSee('Official form · physical signatures required')
            ->assertDontSee('signature_snapshot_id');

        // The generated document applies the Active revision's formal layout.
        $session->get(route('reports.official.equipment.export', $query + ['format' => 'print']))
            ->assertOk()
            ->assertSee('CSPC-F-SPMU-22')
            ->assertSee('Date: __________________')
            ->assertSee('JU-AN C. ADOPTANTE')
            ->assertSee('Not evaluated')
            ->assertDontSee('signature_snapshot_id');
    }

    public function test_real_submitted_request_appears_and_draft_is_excluded(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $unit = OrganizationalUnit::query()->create([
            'unit_code' => 'OFF22', 'unit_name' => 'Official Test Unit',
            'unit_type' => 'OFFICE', 'active' => true,
        ]);
        $request = BorrowingRequest::query()->create([
            'request_no' => 'OF-REAL-501',
            'borrower_user_id' => $head->id,
            'accountable_unit_id' => $unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);
        RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'submitted_at' => '2026-09-15 09:14:00',
            'purpose_event' => 'Official-form test',
            'location' => 'Main Campus',
            'needed_from' => '2026-09-17 00:00:00',
            'return_due_at' => '2026-09-18 23:59:59',
            'office_unit' => 'Test Laboratory',
            'schedule_date' => '2026-09-17',
            'return_date' => '2026-09-18',
        ]);
        $draft = BorrowingRequest::query()->create([
            'request_no' => 'OF-DRAFT-502',
            'borrower_user_id' => $head->id,
            'accountable_unit_id' => $unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::Draft,
        ]);
        RequestVersion::query()->create([
            'request_id' => $draft->id,
            'version_no' => 1,
            'purpose_event' => 'Draft fixture',
            'location' => 'Main Campus',
            'needed_from' => '2026-09-17 00:00:00',
            'return_due_at' => '2026-09-18 23:59:59',
            'submitted_at' => null,
        ]);
        $data = app(OfficialEquipmentSummaryService::class)->build(
            \Carbon\Carbon::parse('2026-09-01')->startOfDay(),
            \Carbon\Carbon::parse('2026-09-30')->endOfDay(),
        );
        $this->assertSame(1, $data['total']);
        $this->assertSame('OF-REAL-501', $data['rows']->first()['control_no']);
        $this->assertSame('15 Sep 2026', $data['rows']->first()['received_date']);
        $this->assertSame('—', $data['rows']->first()['released_date']);
        $this->assertNull($data['on_time_percent']);
    }

    public function test_spmu_officer_cannot_view_or_export_official_form(): void
    {
        $officer = User::factory()->create(['access_classification' => AccessClassification::SpmuOfficer]);
        $this->actingAs($officer)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.official.equipment'))->assertForbidden();
        $this->get(route('reports.official.equipment.export', ['format' => 'csv']))->assertForbidden();
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'OFFICIAL_REPORT_GENERATED')->count());
    }

    public function test_reports_page_no_longer_advertises_the_official_form(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.index'))->assertOk()
            ->assertDontSee('Open Official Form')
            ->assertDontSee(route('reports.official.equipment'), false);

        // The form itself is kept: it is still generated from its own route.
        $this->get(route('reports.official.equipment'))->assertOk();
    }

    public function test_only_the_active_revision_drives_output_not_drafts(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $active = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $active->configuration_json;
        $config['signatories']['prepared_by_name'] = 'DRAFT ONLY PERSON';
        ReportFormTemplate::query()->create([
            'report_key' => OfficialEquipmentSummaryService::KEY, 'version_no' => 2, 'status' => 'DRAFT',
            'form_code' => 'CSPC-F-SPMU-22', 'effectivity_date' => '2026-10-01', 'revision_label' => 'Rev. 3',
            'title_template' => $active->title_template, 'configuration_json' => $config, 'change_reason' => 'Draft fixture',
        ]);

        $session = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $session->get(route('reports.official.equipment', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Rev. 2')
            ->assertDontSee('Rev. 3')
            ->assertDontSee('DRAFT ONLY PERSON')
            ->assertSee('Summary of Requests for Equipment for the Month of September 2026');

        $session->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()
            ->assertSee('JU-AN C. ADOPTANTE')
            ->assertSee('Rev. 2')
            ->assertDontSee('DRAFT ONLY PERSON');
    }

    public function test_activation_archives_previous_revision_and_new_revision_drives_output(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $original = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $originalConfig = $original->configuration_json;
        $config = $originalConfig;
        $config['signatories']['noted_by_name'] = 'NEW NOTED SIGNATORY';
        $draft = ReportFormTemplate::query()->create([
            'report_key' => OfficialEquipmentSummaryService::KEY, 'version_no' => 2, 'status' => 'DRAFT',
            'form_code' => 'CSPC-F-SPMU-22', 'effectivity_date' => '2026-10-01', 'revision_label' => 'Rev. 3',
            'title_template' => $original->title_template, 'configuration_json' => $config, 'change_reason' => 'New signatory',
        ]);

        $session = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $session->post(route('policies.report-forms.activate', $draft))->assertRedirect();

        $original->refresh();
        $this->assertSame('ARCHIVED', $original->status);
        $this->assertSame($originalConfig, $original->configuration_json);
        $this->assertSame('ACTIVE', $draft->fresh()->status);

        $session->get(route('reports.official.equipment', ['from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertSee('Rev. 3');
        $session->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertSee('NEW NOTED SIGNATORY')->assertSee('Rev. 3')
            ->assertDontSee('ANGELICA P. REGONDOLA, PhD');
    }

    public function test_active_and_archived_revisions_cannot_be_modified_or_deleted(): void
    {
        $active = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();

        try {
            $active->update(['revision_label' => 'Rev. 9']);
            $this->fail('An Active revision was modified.');
        } catch (\LogicException) {
        }
        $this->assertSame('Rev. 2', $active->fresh()->revision_label);

        $active->fresh()->update(['status' => 'ARCHIVED']);
        $archived = $active->fresh();
        $this->assertSame('ARCHIVED', $archived->status);

        $this->expectException(\LogicException::class);
        $archived->delete();
    }

    public function test_normalization_migration_leaves_configured_revisions_untouched(): void
    {
        $seed = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $seed->configuration_json;
        $config['columns'][3]['label'] = 'Date/s Needed';
        // Simulate a user-approved archived revision that still carries the older wording.
        \Illuminate\Support\Facades\DB::table('report_form_templates')->where('id', $seed->id)->update([
            'status' => 'ARCHIVED', 'configured_by_user_id' => User::factory()->create()->id,
            'title_template' => 'Summary of Requests for Equipment for the {period}',
            'configuration_json' => json_encode($config),
        ]);

        $migration = require database_path('migrations/2026_10_08_225704_normalize_summary_requests_report_form.php');
        $migration->up();
        $migration->down();

        $after = $seed->fresh();
        $this->assertSame('Summary of Requests for Equipment for the {period}', $after->title_template);
        $this->assertSame('Date/s Needed', $after->configuration_json['columns'][3]['label']);
    }

    public function test_released_request_maps_received_released_and_elapsed_time(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $unit = OrganizationalUnit::query()->create(['unit_code' => 'OFF601', 'unit_name' => 'Records Unit', 'unit_type' => 'OFFICE', 'active' => true]);
        $request = BorrowingRequest::query()->create([
            'request_no' => 'OF-REL-601', 'borrower_user_id' => $head->id, 'accountable_unit_id' => $unit->id,
            'current_version_no' => 1, 'status' => RequestStatus::ApprovedReadyForRelease,
        ]);
        $version = RequestVersion::query()->create([
            'request_id' => $request->id, 'version_no' => 1, 'submitted_at' => '2026-09-10 08:15:00',
            'purpose_event' => 'Release fixture', 'location' => 'Main Campus', 'office_unit' => 'Registrar',
            'needed_from' => '2026-09-12 00:00:00', 'return_due_at' => '2026-09-12 23:59:59',
            'schedule_date' => '2026-09-12', 'return_date' => '2026-09-12',
        ]);
        CustodyTransaction::query()->create([
            'custody_no' => 'CUS-OF-601', 'request_id' => $request->id, 'request_version_id' => $version->id,
            'borrower_user_id' => $head->id, 'status' => 'RELEASED',
            'due_at' => '2026-09-12 17:00:00', 'released_at' => '2026-09-11 10:20:00',
        ]);

        $row = app(OfficialEquipmentSummaryService::class)->build(
            \Carbon\Carbon::parse('2026-09-01')->startOfDay(),
            \Carbon\Carbon::parse('2026-09-30')->endOfDay(),
        )['rows']->first();

        $this->assertSame('Records Unit', $row['requesting_office']);
        $this->assertSame('12 Sep 2026', $row['dates_needed']);
        $this->assertSame('8:15 AM', $row['received_time']);
        $this->assertSame('11 Sep 2026', $row['released_date']);
        $this->assertSame('10:20 AM', $row['released_time']);
        $this->assertSame('26 h 5 min', $row['total_time_allocated']);
        $this->assertSame('Released', $row['remarks']);
    }

    public function test_exports_are_audited_word_keeps_paper_layout_and_data_exports_identify_the_report(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead, 'full_name' => 'Export Head']);
        $base = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        // Excel is a data workbook: identification rows, one filterable header row, no paper elements.
        $xlsx = $base->get(route('reports.official.equipment.export', $query + ['format' => 'xlsx']))->assertOk();
        $book = $this->unzip($xlsx->getContent());
        $sheet = $book['xl/worksheets/sheet1.xml'];
        $strings = $book['xl/sharedStrings.xml'];
        foreach (['Reporting Period', 'Month of September 2026', 'CSPC-F-SPMU-22 · Rev. 2', 'Not evaluated', 'Export Head', 'Control No.', 'Received Date', 'Released Time'] as $text) {
            $this->assertStringContainsString($text, $strings);
        }
        $this->assertStringContainsString('<pane ', $sheet);
        $this->assertStringNotContainsString('<mergeCell', $sheet);
        $this->assertStringNotContainsString('&amp;P of &amp;N', $sheet);
        $this->assertStringNotContainsString('ANGELICA P. REGONDOLA', $strings);
        $this->assertStringNotContainsString('Date: ____', $strings);
        $this->assertStringNotContainsString('CAMARINES SUR POLYTECHNIC COLLEGES', $strings);
        $this->assertEmpty(array_filter(array_keys($book), fn (string $name): bool => str_starts_with($name, 'xl/media/')));

        $docx = $base->get(route('reports.official.equipment.export', $query + ['format' => 'docx']))->assertOk();
        $document = $this->unzip($docx->getContent());
        $this->assertStringContainsString('w:gridSpan w:val="2"', $document['word/document.xml']);
        $this->assertStringContainsString('JU-AN C. ADOPTANTE', $document['word/document.xml']);
        $this->assertStringContainsString('Date: __________________', $document['word/document.xml']);
        $this->assertStringContainsString('NUMPAGES', implode('', array_filter($document, fn ($n) => str_starts_with($n, 'word/footer'), ARRAY_FILTER_USE_KEY)));

        $title = 'Summary of Requests for Equipment for the Month of September 2026';
        $this->assertStringContainsString($title, $book['xl/sharedStrings.xml']);
        $this->assertStringContainsString($title, $document['word/document.xml']);
        $this->assertStringContainsString('<dc:title>'.$title.'</dc:title>', $book['docProps/core.xml']);
        $this->assertStringContainsString('<dc:title>'.$title.'</dc:title>', $document['docProps/core.xml']);

        // CSV is data: identifying lines, a blank line, then the header row. No signatures or letterhead.
        $csv = $base->get(route('reports.official.equipment.export', $query + ['format' => 'csv']))->assertOk();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->getContent());
        $this->assertStringContainsString('CSPC-F-SPMU-22_20260901-20260930', (string) $csv->headers->get('Content-Disposition'));
        $lines = preg_split('/\r?\n/', substr($csv->getContent(), 3));
        $this->assertSame('Report,"'.$title.'"', $lines[0]);
        $this->assertSame('"Reporting Period","Month of September 2026"', $lines[1]);
        $this->assertStringContainsString('September 1, 2026 – September 30, 2026', $lines[2]);
        $this->assertStringContainsString('CSPC-F-SPMU-22 · Rev. 2', $lines[3]);
        $header = array_search('', $lines, true) + 1;
        $this->assertStringStartsWith('"Control No."', $lines[$header]);
        $this->assertStringContainsString('"Received Date"', $lines[$header]);
        $this->assertStringNotContainsString('ADOPTANTE', $csv->getContent());
        $this->assertStringNotContainsString('CAMARINES SUR', $csv->getContent());

        $this->assertSame(3, AuditEvent::query()->where('action_code', 'OFFICIAL_REPORT_GENERATED')->count());
    }

    /** @return array<string, string> */
    private function unzip(string $bytes): array
    {
        $file = tempnam(sys_get_temp_dir(), 'official_zip_');
        file_put_contents($file, $bytes);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($file);

        return $entries;
    }

    public function test_reporting_period_comes_from_selected_dates_not_effectivity(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $base = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);

        // The form's effectivity is August 2025; the selected dates decide the period.
        $base->get(route('reports.official.equipment', ['from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Summary of Requests for Equipment for the Month of October 2026')
            ->assertSee('effective August 2025')
            ->assertDontSee('for the Month of August 2025');

        $base->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Effectivity Date: August 2025');

        $base->get(route('reports.official.equipment', ['from' => '2026-10-01', 'to' => '2026-10-15']))
            ->assertOk()
            ->assertSee('Summary of Requests for Equipment for the Period of October 1–15, 2026')
            ->assertDontSee('for the Month of October 2026');

        $base->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Summary of Requests for Equipment for the Month of October 2026');
    }

    public function test_generating_reports_never_rewrites_the_legacy_title(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $active = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $stored = $active->title_template;
        $this->assertStringContainsString('{month_year}', $stored);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Summary of Requests for Equipment for the Month of October 2026');

        $this->assertSame($stored, $active->fresh()->title_template);
    }

    private function build(): array
    {
        return app(OfficialEquipmentSummaryService::class)->build(
            \Carbon\Carbon::parse('2026-09-01')->startOfDay(),
            \Carbon\Carbon::parse('2026-09-30')->endOfDay(),
        );
    }

    public function test_fields_missing_from_a_stored_revision_stay_hidden(): void
    {
        $active = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $stored = $active->configuration_json;

        // The approved seed stores only the ten official columns.
        $this->assertSame(OfficialEquipmentSummaryService::OFFICIAL_KEYS, array_column($stored['columns'], 'key'));
        $this->assertSame(OfficialEquipmentSummaryService::OFFICIAL_KEYS, array_column($this->build()['columns'], 'key'));

        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $print = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.official.equipment.export', ['format' => 'print', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk();
        foreach (OfficialEquipmentSummaryService::OPTIONAL_KEYS as $key) {
            $print->assertDontSee(OfficialEquipmentSummaryService::LABELS[$key]);
        }

        // Generating never writes the missing keys back into the revision.
        $this->assertSame($stored, $active->fresh()->configuration_json);
    }

    public function test_revision_saved_before_new_fields_existed_keeps_its_approved_layout(): void
    {
        $seed = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $seed->configuration_json;
        // A revision approved with the three earlier optional fields: one included, two not.
        $config['columns'][] = ['key' => 'borrower_name', 'label' => 'Borrower', 'visible' => true, 'order' => 11, 'group' => null];
        $config['columns'][] = ['key' => 'requested_quantity', 'label' => 'Total Quantity Requested', 'visible' => false, 'order' => 12, 'group' => null];
        $config['columns'][] = ['key' => 'approved_date', 'label' => 'Final Approval Date', 'visible' => false, 'order' => 13, 'group' => null];
        \Illuminate\Support\Facades\DB::table('report_form_templates')->where('id', $seed->id)
            ->update(['configuration_json' => json_encode($config)]);

        $this->assertSame(
            [...OfficialEquipmentSummaryService::OFFICIAL_KEYS, 'borrower_name'],
            array_column($this->build()['columns'], 'key')
        );
    }

    public function test_revision_without_stored_columns_falls_back_to_the_official_ten(): void
    {
        $seed = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $seed->configuration_json;
        unset($config['columns']);
        \Illuminate\Support\Facades\DB::table('report_form_templates')->where('id', $seed->id)
            ->update(['configuration_json' => json_encode($config)]);

        $columns = $this->build()['columns'];
        $this->assertSame(OfficialEquipmentSummaryService::OFFICIAL_KEYS, array_column($columns, 'key'));
        $this->assertSame('Control No.', $columns[0]['label']);
    }

    /** A submitted September 2026 request with its current version. */
    private function submittedRequest(string $no, array $request = [], array $version = []): BorrowingRequest
    {
        $unit = OrganizationalUnit::query()->firstOrCreate(
            ['unit_code' => 'OFF700'],
            ['unit_name' => 'Fields Unit', 'unit_type' => 'OFFICE', 'active' => true]
        );
        $record = BorrowingRequest::query()->create($request + [
            'request_no' => $no, 'borrower_user_id' => User::factory()->create()->id,
            'accountable_unit_id' => $unit->id, 'current_version_no' => 1, 'status' => RequestStatus::UnderSpmu,
        ]);
        RequestVersion::query()->create($version + [
            'request_id' => $record->id, 'version_no' => 1, 'submitted_at' => '2026-09-10 08:15:00',
            'purpose_event' => 'Fixture', 'location' => 'Main Campus',
            'needed_from' => '2026-09-12 00:00:00', 'return_due_at' => '2026-09-12 23:59:59',
            'schedule_date' => '2026-09-12', 'return_date' => '2026-09-12',
        ]);

        return $record;
    }

    private function custodyFor(BorrowingRequest $request, array $attributes = []): CustodyTransaction
    {
        return CustodyTransaction::query()->create($attributes + [
            'custody_no' => 'CUS-'.$request->request_no, 'request_id' => $request->id,
            'request_version_id' => $request->currentVersion->id, 'borrower_user_id' => $request->borrower_user_id,
            'status' => 'ACTIVE', 'due_at' => '2026-09-12 17:00:00', 'released_at' => '2026-09-11 10:20:00',
        ]);
    }

    /** Make every supported field part of the Active revision, as an activated Draft would. */
    private function includeEveryField(): void
    {
        $seed = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $seed->configuration_json;
        foreach (OfficialEquipmentSummaryService::OPTIONAL_KEYS as $index => $key) {
            $config['columns'][] = ['key' => $key, 'label' => OfficialEquipmentSummaryService::LABELS[$key],
                'visible' => true, 'order' => 11 + $index, 'group' => null];
        }
        \Illuminate\Support\Facades\DB::table('report_form_templates')->where('id', $seed->id)
            ->update(['configuration_json' => json_encode($config)]);
    }

    public function test_new_optional_fields_read_the_actual_request_approval_and_custody_records(): void
    {
        $officer = User::factory()->create(['full_name' => 'Verifying Officer']);
        $delegate = User::factory()->create(['full_name' => 'Delegated Approver']);

        $full = $this->submittedRequest('OF-FLD-701', ['status' => RequestStatus::ApprovedReadyForRelease, 'final_approved_at' => '2026-09-10 15:00:00'], [
            'purpose_event' => '  Foundation Day program  ', 'location' => 'Naga City Convention Center',
            'division_code' => OrganizationalUnit::DIVISION_ACADEMIC, 'off_campus' => true,
        ]);
        $versionId = $full->currentVersion->id;
        \App\Models\ApprovalStep::query()->create([
            'request_version_id' => $versionId, 'approver_user_id' => $officer->id, 'stage_code' => 'SPMU',
            'sequence_no' => 1, 'decision' => 'VERIFIED', 'decided_at' => '2026-09-10 10:00:00',
        ]);
        \App\Models\ApprovalStep::query()->create([
            'request_version_id' => $versionId, 'approver_user_id' => $delegate->id, 'stage_code' => 'SPMU',
            'sequence_no' => 2, 'decision' => 'APPROVED', 'decided_at' => '2026-09-10 15:00:00',
        ]);
        $this->custodyFor($full, ['custody_no' => 'CUS-FLD-701']);

        // Still under review: no decision, no custody, no division on record.
        $pending = $this->submittedRequest('OF-FLD-702');
        \App\Models\ApprovalStep::query()->create([
            'request_version_id' => $pending->currentVersion->id, 'stage_code' => 'SPMU',
            'sequence_no' => 2, 'decision' => 'RECEIVED',
        ]);

        $data = $this->build();
        $rows = $data['rows']->keyBy('control_no');

        $this->assertSame(2, $data['total']);
        $this->assertSame([
            'purpose_event' => 'Foundation Day program', 'location' => 'Naga City Convention Center',
            'division' => 'Academic', 'off_campus' => 'Yes',
            'approved_by' => 'Delegated Approver', 'custody_no' => 'CUS-FLD-701',
        ], array_intersect_key($rows['OF-FLD-701'], array_flip(['purpose_event', 'location', 'division', 'off_campus', 'approved_by', 'custody_no'])));

        $this->assertSame([
            'purpose_event' => 'Fixture', 'location' => 'Main Campus',
            'division' => '—', 'off_campus' => 'No', 'approved_by' => '—', 'custody_no' => '—',
        ], array_intersect_key($rows['OF-FLD-702'], array_flip(['purpose_event', 'location', 'division', 'off_campus', 'approved_by', 'custody_no'])));

        // Every supported key is always present on a row, whatever the revision shows.
        foreach (OfficialEquipmentSummaryService::KEYS as $key) {
            $this->assertArrayHasKey($key, $rows['OF-FLD-702']);
        }
    }

    public function test_remarks_reports_the_current_operational_status(): void
    {
        $this->submittedRequest('OF-RMK-1');
        $this->submittedRequest('OF-RMK-2', ['status' => RequestStatus::ReturnedForRevision]);
        $this->submittedRequest('OF-RMK-3', ['status' => RequestStatus::Rejected]);
        $ready = $this->submittedRequest('OF-RMK-4', ['status' => RequestStatus::ApprovedReadyForRelease]);
        $this->custodyFor($ready, ['status' => 'PREPARING_RELEASE', 'released_at' => null]);
        $out = $this->submittedRequest('OF-RMK-5', ['status' => RequestStatus::ApprovedReadyForRelease]);
        $this->custodyFor($out);
        $overdue = $this->submittedRequest('OF-RMK-6', ['status' => RequestStatus::ApprovedReadyForRelease]);
        $this->custodyFor($overdue, ['status' => 'OVERDUE']);
        $returning = $this->submittedRequest('OF-RMK-7', ['status' => RequestStatus::ApprovedReadyForRelease]);
        $this->custodyFor($returning, ['status' => 'RETURN_PROCESSING']);
        $closed = $this->submittedRequest('OF-RMK-8', ['status' => RequestStatus::ApprovedReadyForRelease]);
        $this->custodyFor($closed, ['status' => 'CLOSED', 'closed_at' => '2026-09-13 09:00:00']);
        // Cancellation stamps closed_at on the custody record; it is still a cancelled request.
        $cancelled = $this->submittedRequest('OF-RMK-9', ['status' => RequestStatus::Cancelled]);
        $this->custodyFor($cancelled, ['status' => 'CANCELLED', 'released_at' => null, 'closed_at' => '2026-09-11 09:00:00']);

        $data = $this->build();

        $this->assertSame([
            'OF-RMK-1' => 'Under SPMU Review',
            'OF-RMK-2' => 'Returned for Revision',
            'OF-RMK-3' => 'Rejected',
            'OF-RMK-4' => 'Ready for Release',
            'OF-RMK-5' => 'Released / On Custody',
            'OF-RMK-6' => 'Overdue',
            'OF-RMK-7' => 'Return Processing',
            'OF-RMK-8' => 'Completed',
            'OF-RMK-9' => 'Cancelled',
        ], $data['rows']->pluck('remarks', 'control_no')->all());

        // Released is counted from the release record, not from the Remarks wording.
        $this->assertSame(4, $data['released_count']);
        $this->assertSame('Remarks', collect($data['columns'])->firstWhere('key', 'remarks')['label']);
    }

    public function test_every_field_renders_in_every_format_with_long_and_missing_data(): void
    {
        $this->includeEveryField();
        $long = 'Regional Higher Education Research and Extension Symposium with Exhibit and Awarding Ceremony';
        $venue = 'CSPC Gymnasium and Multi-Purpose Covered Court, Main Campus, Nabua';
        $full = $this->submittedRequest('OF-ALL-801', ['status' => RequestStatus::ApprovedReadyForRelease], [
            'purpose_event' => $long, 'location' => $venue, 'division_code' => OrganizationalUnit::DIVISION_ADMINISTRATION,
        ]);
        \App\Models\ApprovalStep::query()->create([
            'request_version_id' => $full->currentVersion->id, 'stage_code' => 'SPMU', 'sequence_no' => 2, 'decision' => 'APPROVED',
            'approver_user_id' => User::factory()->create(['full_name' => 'Approving Head'])->id,
        ]);
        $this->custodyFor($full, ['custody_no' => 'CUS-ALL-801']);
        $this->submittedRequest('OF-ALL-802');

        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $base = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];
        $labels = array_map(fn (string $key): string => OfficialEquipmentSummaryService::LABELS[$key], OfficialEquipmentSummaryService::OPTIONAL_KEYS);
        $values = [$long, $venue, 'Administrative', 'Approving Head', 'CUS-ALL-801'];

        $preview = $base->get(route('reports.official.equipment', $query))->assertOk();
        $print = $base->get(route('reports.official.equipment.export', $query + ['format' => 'print']))->assertOk();
        foreach ([...$labels, ...$values, 'OF-ALL-802'] as $text) {
            $preview->assertSee($text);
            $print->assertSee($text);
        }

        // Protected document elements are untouched by the added columns.
        $print->assertSee('CAMARINES SUR POLYTECHNIC COLLEGES')->assertSee('CSPC-F-SPMU-22')->assertSee('Rev. 2')
            ->assertSee('Summary of Requests for Equipment for the Month of September 2026')
            ->assertSee('Effectivity Date: August 2025')->assertSee('JU-AN C. ADOPTANTE')->assertSee('ANGELICA P. REGONDOLA, PhD')
            ->assertSee('RECEIVED')->assertSee('RELEASED');

        $pdf = $base->get(route('reports.official.equipment.export', $query + ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $docx = $this->unzip($base->get(route('reports.official.equipment.export', $query + ['format' => 'docx']))->assertOk()->getContent());
        $xlsx = $this->unzip($base->get(route('reports.official.equipment.export', $query + ['format' => 'xlsx']))->assertOk()->getContent());
        $csv = $base->get(route('reports.official.equipment.export', $query + ['format' => 'csv']))->assertOk()->getContent();
        foreach ([...$labels, ...$values] as $text) {
            $this->assertStringContainsString(e($text), $docx['word/document.xml']);
            $this->assertStringContainsString(e($text), $xlsx['xl/sharedStrings.xml']);
            $this->assertStringContainsString($text, $csv);
        }
        $this->assertStringContainsString('JU-AN C. ADOPTANTE', $docx['word/document.xml']);

        // One header row carrying all 19 columns in the configured order.
        $lines = preg_split('/\r?\n/', substr($csv, 3));
        $header = str_getcsv($lines[array_search('', $lines, true) + 1]);
        $this->assertSame([
            'Control No.', 'Requesting Office/Department', 'Requested Equipment', 'Dates Needed',
            'Received Date', 'Received Time', 'Released Date', 'Released Time', 'Total Time Allocated', 'Remarks',
            ...$labels,
        ], $header);
        $this->assertCount(19, $header);
    }

    /** Add entries to the Active revision's stored columns, as an activated Draft would carry them. */
    private function storeColumns(array $columns): void
    {
        $seed = ReportFormTemplate::query()->where('report_key', OfficialEquipmentSummaryService::KEY)->sole();
        $config = $seed->configuration_json;
        array_push($config['columns'], ...$columns);
        \Illuminate\Support\Facades\DB::table('report_form_templates')->where('id', $seed->id)
            ->update(['configuration_json' => json_encode($config)]);
    }

    public function test_custom_columns_render_blank_cells_in_every_format(): void
    {
        $plain = 'custom_sign000001';
        $markup = 'custom_markup0001';
        $formula = 'custom_formula001';
        $this->storeColumns([
            ['key' => $plain, 'label' => 'Received By (Signature over Printed Name)', 'visible' => true, 'order' => 11, 'group' => null, 'custom' => true],
            ['key' => $markup, 'label' => 'R&D <Notes>', 'visible' => true, 'order' => 12, 'group' => null, 'custom' => true],
            ['key' => $formula, 'label' => '=SUM(A1:A9)', 'visible' => true, 'order' => 13, 'group' => null, 'custom' => true],
        ]);
        $request = $this->submittedRequest('OF-CUS-901');
        $this->custodyFor($request);

        $data = $this->build();
        $row = $data['rows']->first();
        $this->assertSame([...OfficialEquipmentSummaryService::OFFICIAL_KEYS, $plain, $markup, $formula], array_column($data['columns'], 'key'));
        // No data source: blank, never a placeholder and never a borrowed system value.
        $this->assertSame(['', '', ''], [$row[$plain], $row[$markup], $row[$formula]]);
        $this->assertSame('OF-CUS-901', $row['control_no']);
        $this->assertEqualsWithDelta(100, array_sum(array_column($data['columns'], 'width')), 0.1);

        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $base = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];

        $base->get(route('reports.official.equipment', $query))->assertOk()
            ->assertSee('Received By (Signature over Printed Name)')->assertSee('R&D <Notes>')
            ->assertDontSee('R&D <Notes>', false);

        $print = $base->get(route('reports.official.equipment.export', $query + ['format' => 'print']))->assertOk()
            ->assertSee('Received By (Signature over Printed Name)')->assertSee('R&D <Notes>')
            ->assertDontSee('<Notes>', false)
            ->assertSee('CAMARINES SUR POLYTECHNIC COLLEGES')->assertSee('CSPC-F-SPMU-22')
            ->assertSee('Summary of Requests for Equipment for the Month of September 2026')
            ->assertSee('JU-AN C. ADOPTANTE');
        // The three custom cells close the record row, empty.
        $this->assertStringContainsString('<td></td><td></td><td></td></tr>', $print->getContent());

        $pdf = $base->get(route('reports.official.equipment.export', $query + ['format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Word and Excel stay well-formed with markup characters in a heading.
        $docx = $this->unzip($base->get(route('reports.official.equipment.export', $query + ['format' => 'docx']))->assertOk()->getContent());
        $xlsx = $this->unzip($base->get(route('reports.official.equipment.export', $query + ['format' => 'xlsx']))->assertOk()->getContent());
        foreach ([$docx['word/document.xml'], $xlsx['xl/sharedStrings.xml'], $xlsx['xl/worksheets/sheet1.xml']] as $xml) {
            $this->assertNotFalse(@simplexml_load_string($xml), 'export part is not well-formed XML');
        }
        $this->assertStringContainsString('R&amp;D &lt;Notes&gt;', $docx['word/document.xml']);
        $this->assertStringContainsString('R&amp;D &lt;Notes&gt;', $xlsx['xl/sharedStrings.xml']);
        $this->assertStringNotContainsString('<f>', $xlsx['xl/worksheets/sheet1.xml']);

        $csv = $base->get(route('reports.official.equipment.export', $query + ['format' => 'csv']))->assertOk()->getContent();
        $lines = preg_split('/\r?\n/', substr($csv, 3));
        $at = array_search('', $lines, true) + 1;
        $header = str_getcsv($lines[$at]);
        $record = str_getcsv($lines[$at + 1]);
        $this->assertSame(['Received By (Signature over Printed Name)', 'R&D <Notes>', "'=SUM(A1:A9)"], array_slice($header, 10));
        $this->assertCount(13, $record);
        $this->assertSame(['', '', ''], array_slice($record, 10));
        $this->assertSame('OF-CUS-901', $record[0]);
    }

    public function test_only_well_formed_custom_entries_become_columns(): void
    {
        $this->storeColumns([
            // Not marked custom, malformed key, a system key posing as custom, and a hidden custom column.
            ['key' => 'custom_unmarked01', 'label' => 'Unmarked', 'visible' => true, 'order' => 11, 'group' => null],
            ['key' => 'Custom Bad Key', 'label' => 'Bad Key', 'visible' => true, 'order' => 12, 'group' => null, 'custom' => true],
            ['key' => '_link', 'label' => 'Link', 'visible' => true, 'order' => 13, 'group' => null, 'custom' => true],
            ['key' => 'custom_hidden001', 'label' => 'Hidden', 'visible' => false, 'order' => 14, 'group' => null, 'custom' => true],
            ['key' => 'custom_nolabel01', 'label' => '  ', 'visible' => true, 'order' => 15, 'group' => null, 'custom' => true],
        ]);
        $this->submittedRequest('OF-CUS-902');

        $data = $this->build();
        $this->assertSame([...OfficialEquipmentSummaryService::OFFICIAL_KEYS, 'custom_nolabel01'], array_column($data['columns'], 'key'));
        $this->assertSame('Custom Column', collect($data['columns'])->firstWhere('key', 'custom_nolabel01')['label']);
        $this->assertStringContainsString('/requests/', $data['rows']->first()['_link']);
    }

    public function test_export_uses_real_formats_not_html_disguised_as_files(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $base = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $csv = $base->get(route('reports.official.equipment.export', ['format' => 'csv']));
        $csv->assertOk()->assertSee('Control No.', false);
        $pdf = $base->get(route('reports.official.equipment.export', ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        foreach (['xlsx', 'docx'] as $format) {
            $response = $base->get(route('reports.official.equipment.export', ['format' => $format]));
            $response->assertOk();
            $this->assertStringStartsWith('PK', $response->getContent());
        }
    }
}
