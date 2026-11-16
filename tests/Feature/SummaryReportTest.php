<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\RequestVersion;
use App\Models\User;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\UnitOfMeasure;
use App\Reports\ReportFilters;
use App\Services\BorrowingRequestNumberService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private function requestRecord(string $number = 'BR-HISTORICAL-001'): BorrowingRequest
    {
        $borrower = User::factory()->create(['full_name' => 'Recorded Borrower']);
        $request = BorrowingRequest::create([
            'request_no' => $number, 'borrower_user_id' => $borrower->id,
            'current_version_no' => 1, 'status' => RequestStatus::UnderSpmu,
        ]);
        $this->version($request, 1, '2026-11-13 08:15:00');
        return $request;
    }

    private function version(BorrowingRequest $request, int $number, ?string $submitted): RequestVersion
    {
        $version = $request->versions()->create([
            'version_no' => $number, 'submitted_at' => $submitted,
            'purpose_event' => 'Recorded event', 'location' => 'CSPC',
            'division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies',
            'schedule_date' => '2026-11-16', 'return_date' => '2026-11-17',
            'needed_from' => '2026-11-16 00:00:00', 'return_due_at' => '2026-11-17 23:59:59',
            'created_by_user_id' => $request->borrower_user_id,
        ]);
        if ($submitted) {
            $reviewer = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
            $version->approvalSteps()->create(['stage_code' => \App\Enums\ApprovalStage::Spmu, 'sequence_no' => 2,
                'approver_user_id' => $reviewer->id, 'decision' => 'APPROVED', 'received_at' => $submitted, 'decided_at' => $submitted]);
        }
        return $version;
    }

    private function dataset(string $month = '2026-11')
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $filters = ReportFilters::fromRequest(Request::create('/reports'), 'summary', $from, $from->copy()->endOfMonth(), 'custom');
        return app(ReportService::class)->generate($filters);
    }

    public function test_latest_submitted_version_is_one_row_and_moves_to_its_resubmission_month(): void
    {
        $request = $this->requestRecord();
        $this->version($request, 2, '2026-12-01 09:00:00');
        $this->version($request, 3, null);
        $request->update(['current_version_no' => 3]);
        $this->assertSame(0, $this->dataset()->count());
        $dataset = $this->dataset('2026-12');
        $this->assertSame(1, $dataset->count());
        $this->assertSame('01/12/2026 09:00 AM', $dataset->rows[0]['received_at']);
        $this->assertSame('BR-HISTORICAL-001', $dataset->rows[0]['request_no']);
        $this->assertSame('UNMET', $dataset->rows[0]['remarks']);
    }

    public function test_report_uses_recorded_affiliation_and_actual_elapsed_release_time(): void
    {
        $request = $this->requestRecord();
        CustodyTransaction::create([
            'custody_no' => 'CT-SUMMARY-001', 'request_id' => $request->id,
            'request_version_id' => $request->currentVersion->id, 'borrower_user_id' => $request->borrower_user_id,
            'status' => 'ACTIVE', 'released_at' => '2026-11-14 10:00:00',
            'scheduled_release_at' => '2026-11-13 10:00:00', 'due_at' => '2026-11-17 17:00:00',
        ]);
        $before = DB::table('borrowing_requests')->get()->toJson();
        $dataset = $this->dataset();
        $row = $dataset->rows[0];
        $this->assertSame('College of Computer Studies — Academic', $row['office_department']);
        $this->assertSame('25h 45m', $row['elapsed']);
        $this->assertSame('MET', $row['remarks']);
        $this->assertSame(9, count($dataset->columns));
        $this->assertArrayNotHasKey('badge', $dataset->columns[8]);
        $this->assertSame($before, DB::table('borrowing_requests')->get()->toJson());
    }

    public function test_default_report_is_monthly_and_legacy_ledger_is_still_available(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 13, 12));
        $this->requestRecord();
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        $this->get(route('reports.index'))->assertOk()
            ->assertSee('SUMMARY OF REQUESTS FOR EQUIPMENTS FOR THE MONTH OF NOVEMBER 2026')
            ->assertSee('College of Computer Studies — Academic')
            ->assertDontSee('data-document-fullscreen-label');
        $this->get(route('reports.index', ['report' => 'all-transactions']))->assertOk()->assertSee('Transaction Records');
        $this->get(route('reports.index', ['report' => 'summary', 'summary_month' => 13]))->assertSessionHasErrors('summary_month');
        $this->get(route('reports.index', ['report' => 'summary', 'summary_year' => 2026, 'summary_month' => 12]))->assertOk()->assertDontSee('BR-HISTORICAL-001');
    }

    public function test_monthly_counter_skips_existing_numbers_resets_and_preserves_history(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 30, 23, 59, 0, 'Asia/Manila'));
        $legacy = $this->requestRecord('BR-2026-11-0042');
        $service = app(BorrowingRequestNumberService::class);
        $this->assertSame('BR-2026-11-0043', $service->newNumber($legacy->borrower_user_id));
        $this->assertSame('BR-2026-11-0044', $service->newNumber($legacy->borrower_user_id));
        $this->travelTo(Carbon::create(2026, 12, 1, 0, 0, 0, 'Asia/Manila'));
        $this->assertSame('BR-2026-12-0001', $service->newNumber($legacy->borrower_user_id));
        $this->assertSame('BR-2026-11-0042', $legacy->fresh()->request_no);
    }

    public function test_equipment_snapshots_and_all_export_formats_use_the_same_nine_columns(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 13, 12));
        $request = $this->requestRecord();
        $category = InventoryCategory::create(['category_code' => 'SUMMARY', 'category_name' => 'Summary fixture']);
        $unit = UnitOfMeasure::create(['unit_code' => 'SUMMARY-PC', 'unit_name' => 'Piece']);
        $item = InventoryItem::create([
            'category_id' => $category->id, 'unit_id' => $unit->id,
            'unique_description' => 'Renamed equipment', 'total_quantity' => 10,
            'condition_code' => 'SERVICEABLE', 'borrowable' => true, 'active' => true,
        ]);
        $request->currentVersion->items()->create([
            'inventory_item_id' => $item->id, 'description_snapshot' => 'Recorded Projector',
            'unit_snapshot' => 'Piece', 'requested_quantity' => 3, 'approved_quantity' => 1,
            'use_location' => 'ON_CAMPUS',
        ]);
        $second = $item->replicate();
        $second->unique_description = 'Renamed chair';
        $second->save();
        $request->currentVersion->items()->create([
            'inventory_item_id' => $second->id, 'description_snapshot' => 'Recorded Chair',
            'unit_snapshot' => 'Piece', 'requested_quantity' => 10, 'approved_quantity' => 2,
            'use_location' => 'ON_CAMPUS',
        ]);
        $this->assertSame("3 - Recorded Projector\n10 - Recorded Chair", $this->dataset()->rows[0]['equipment']);
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU']);
        foreach (['print', 'csv', 'pdf', 'docx', 'xlsx'] as $format) {
            $response = $this->get(route($format === 'print' ? 'reports.print' : 'reports.export', [
                'type' => 'summary', 'format' => $format, 'summary_month' => 11, 'summary_year' => 2026,
            ]))->assertOk();
            if ($format === 'print') {
                $response->assertSee('3 - Recorded Projector')->assertSee('Office &amp; Department', false)->assertSee('UNMET');
            } elseif ($format === 'csv') {
                $this->assertStringContainsString('3 - Recorded Projector', $response->streamedContent());
            } else {
                $this->assertStringStartsWith($format === 'pdf' ? '%PDF' : 'PK', $response->getContent());
                if ($format === 'docx') {
                    $path = tempnam(sys_get_temp_dir(), 'summary-word-');
                    file_put_contents($path, $response->getContent());
                    try {
                        $zip = new \ZipArchive;
                        $this->assertTrue($zip->open($path) === true);
                        $xml = $zip->getFromName('word/document.xml');
                        $zip->close();
                        $this->assertMatchesRegularExpression('/3 - Recorded Projector.*<w:br\s*\/>.*10 - Recorded Chair/s', $xml);
                    } finally {
                        @unlink($path);
                    }
                }
            }
        }
        $borrower = User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
        $this->actingAs($borrower)->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('reports.index'))->assertForbidden();
    }

    public function test_custom_analytics_detail_links_preserve_the_selected_dates(): void
    {
        $this->app->instance('request', Request::create('/analytics', 'GET', [
            'start_date' => '2026-02-03', 'end_date' => '2026-03-04', 'borrower_type' => 'EXTERNAL',
        ]));
        $urls = [
            \App\Support\AnalyticsDetailLink::to('requests', 'overview', 'custom'),
            \App\Support\AnalyticsDrilldown::borrowing('custom'),
        ];
        foreach ($urls as $url) {
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame('2026-02-03', $query['start_date']);
            $this->assertSame('2026-03-04', $query['end_date']);
        }
    }

    public function test_recorded_signer_name_survives_a_later_profile_name_change(): void
    {
        $request = $this->requestRecord();
        $file = \App\Models\StoredFile::create([
            'uploaded_by_user_id' => $request->borrower_user_id, 'disk' => 'local',
            'storage_path' => 'tests/summary-signature.png', 'original_name' => 'signature.png',
            'mime_type' => 'image/png', 'byte_size' => 1, 'sha256' => str_repeat('a', 64),
            'classification' => 'SIGNATURE',
        ]);
        $signature = \App\Models\UserSignature::create([
            'user_id' => $request->borrower_user_id, 'stored_file_id' => $file->id, 'status' => 'ACTIVE',
        ]);
        $snapshot = \App\Models\SignatureSnapshot::create([
            'user_signature_id' => $signature->id, 'signer_user_id' => $request->borrower_user_id,
            'snapshot_file_id' => $file->id, 'signer_name' => 'Recorded, Exact Name M.',
            'purpose_code' => 'BORROWING_REQUEST', 'sha256' => str_repeat('a', 64), 'captured_at' => now(),
        ]);
        $request->currentVersion->update(['borrower_signature_snapshot_id' => $snapshot->id]);
        $request->borrower->update(['full_name' => 'Changed Profile Name']);
        $this->assertSame('Recorded, Exact Name M.', $this->dataset()->rows[0]['borrower']);
        $this->assertSame('Recorded, Exact Name M.', $snapshot->fresh()->signer_name);
    }

    public function test_missing_submission_times_are_not_assigned_to_a_guessed_month(): void
    {
        $request = $this->requestRecord();
        $request->currentVersion->update(['submitted_at' => null]);
        $draft = $this->requestRecord('BR-UNSUBMITTED-DRAFT');
        $draft->update(['status' => RequestStatus::Draft]);
        $draft->currentVersion->update(['submitted_at' => null]);
        $dataset = $this->dataset();
        $this->assertSame(0, $dataset->count());
        $this->assertStringStartsWith('1 historical request(s)', $dataset->notice);
    }

    public function test_summary_dates_sort_chronologically_across_month_and_year_boundaries(): void
    {
        $rows = collect([
            ['date' => '01/01/2027 09:00 AM'],
            ['date' => '29/11/2026 11:59 PM'],
            ['date' => '01/12/2026 08:00 AM'],
        ]);
        $table = \App\Reports\ReportRecordTable::fromRequest(
            Request::create('/reports', 'GET', ['sort' => 'date', 'direction' => 'asc']),
            [['key' => 'date', 'label' => 'Received Date & Time']], $rows, '/reports', []
        );
        $this->assertSame([
            '29/11/2026 11:59 PM', '01/12/2026 08:00 AM', '01/01/2027 09:00 AM',
        ], $table->records->getCollection()->pluck('date')->all());
    }

    public function test_excel_preserves_recorded_names_as_text_and_summary_dates_as_dates(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 13, 12));
        $request = $this->requestRecord();
        $request->borrower->update(['full_name' => '13/11/2026']);
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $response = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', ['type' => 'summary', 'format' => 'xlsx']))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'summary-xlsx-');
        file_put_contents($path, $response->getContent());
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
            foreach ($sheet->toArray() as $index => $row) {
                if (($row[0] ?? null) !== $request->request_no) continue;
                $number = $index + 1;
                $this->assertSame('13/11/2026', $sheet->getCell('B'.$number)->getValue());
                $this->assertSame('s', $sheet->getCell('B'.$number)->getDataType());
                $this->assertSame('n', $sheet->getCell('E'.$number)->getDataType());
                $this->assertEquals(floor($sheet->getCell('E'.$number)->getValue()), $sheet->getCell('E'.$number)->getValue());
                $this->assertSame('dd/mm/yyyy', $sheet->getCell('E'.$number)->getStyle()->getNumberFormat()->getFormatCode());
                return;
            }
            $this->fail('The exported request row was not found.');
        } finally {
            @unlink($path);
        }
    }
}
