<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryCustomRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 15, 12, 0, 0, 'Asia/Manila'));
        $this->actingAs(User::factory()->create(['access_classification' => AccessClassification::SpmuHead]))
            ->withSession(['active_workspace' => 'SPMU']);
    }

    private function record(string $number, ?string $submitted): BorrowingRequest
    {
        $borrower = User::factory()->create(['full_name' => 'Recorded '.$number]);
        $request = BorrowingRequest::create([
            'request_no' => $number, 'borrower_user_id' => $borrower->id,
            'current_version_no' => 1, 'status' => $submitted ? RequestStatus::UnderSpmu : RequestStatus::Draft,
        ]);
        $request->versions()->create([
            'version_no' => 1, 'submitted_at' => $submitted, 'purpose_event' => 'Recorded event',
            'location' => 'CSPC', 'division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies',
            'schedule_date' => '2026-12-20', 'return_date' => '2026-12-21',
            'needed_from' => '2026-12-20 08:00:00', 'return_due_at' => '2026-12-21 17:00:00',
        ]);
        return $request;
    }

    private function range(): array
    {
        return ['academic_period' => 'custom', 'start_date' => '2026-10-31', 'end_date' => '2026-11-02'];
    }

    public function test_custom_range_includes_both_endpoints_and_uses_latest_submission_instead_of_schedule_or_old_versions(): void
    {
        $this->record('BR-START', '2026-10-31 00:00:00');
        $this->record('BR-END', '2026-11-02 23:59:59');
        $this->record('BR-BEFORE', '2026-10-30 23:59:59');
        $this->record('BR-AFTER', '2026-11-03 00:00:00');
        $this->record('BR-DRAFT', null);
        $corrected = $this->record('BR-CORRECTED', '2026-10-31 10:00:00');
        $corrected->versions()->create(array_replace($corrected->currentVersion->getAttributes(), [
            'id' => null, 'version_no' => 2, 'submitted_at' => '2026-11-03 08:00:00',
        ]));
        $corrected->update(['current_version_no' => 2]);

        $page = $this->get(route('reports.index', ['report' => 'summary'] + $this->range()))->assertOk();
        $dataset = $page->viewData('dataset');
        $this->assertSame(['BR-END', 'BR-START'], $dataset->rows->pluck('request_no')->all());
        $this->assertCount(9, $dataset->columns);
        $this->assertSame('2026-10-31', $dataset->meta['period_from']);
        $this->assertSame('2026-11-02', $dataset->meta['period_to']);
        $page->assertSeeText('FOR THE PERIOD 31 OCTOBER 2026 – 02 NOVEMBER 2026');
        if ($directory = getenv('CONTROL_REPORT_HTML_DIR')) {
            file_put_contents($directory.'/summary-custom.html', $page->getContent());
            file_put_contents($directory.'/transactions-custom.html', $this->get(route('reports.index', [
                'report' => 'all-transactions',
            ] + $this->range()))->assertOk()->getContent());
        }
    }

    public function test_historical_month_and_year_survive_apply_clear_print_and_export_links(): void
    {
        $this->record('BR-OCTOBER', '2026-10-12 08:00:00');
        $this->record('BR-NOVEMBER', '2026-11-12 08:00:00');
        $page = $this->get(route('reports.index', [
            'report' => 'summary', 'academic_period' => 'month', 'summary_month' => 10, 'summary_year' => 2026,
        ]))->assertOk();
        $query = $page->viewData('reportFilters')->toQuery();
        $this->assertSame('month', $query['academic_period']);
        $this->assertSame(10, $query['summary_month']);
        $this->assertSame(2026, $query['summary_year']);
        $this->assertSame(['BR-OCTOBER'], $page->viewData('dataset')->rows->pluck('request_no')->all());
        $page->assertSeeText('FOR THE MONTH OF OCTOBER 2026')->assertSee('summary_month=10', false);
        $this->get(route('reports.print', ['type' => 'summary'] + $query))->assertOk()
            ->assertSeeText('FOR THE MONTH OF OCTOBER 2026')->assertSeeText('BR-OCTOBER')->assertDontSeeText('BR-NOVEMBER');
    }

    public function test_custom_dates_are_required_valid_and_ordered_before_generation(): void
    {
        foreach ([
            [[], 'start_date'],
            [['start_date' => '2026-11-01'], 'end_date'],
            [['start_date' => '2026-11-30', 'end_date' => '2026-11-01'], 'end_date'],
            [['start_date' => '2026-02-30', 'end_date' => '2026-03-01'], 'start_date'],
        ] as [$dates, $error]) {
            $this->getJson(route('reports.index', ['report' => 'summary', 'academic_period' => 'custom'] + $dates))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->get(route('reports.index', ['report' => 'summary', 'academic_period' => 'custom',
            'start_date' => '2026-11-15', 'end_date' => '2026-11-15']))->assertOk()
            ->assertSeeText('FOR THE PERIOD 15 NOVEMBER 2026 – 15 NOVEMBER 2026');
    }

    public function test_screen_print_and_all_existing_exports_share_the_custom_range_title_and_matching_rows(): void
    {
        $this->record('BR-IN-RANGE', '2026-11-01 08:00:00');
        $this->record('BR-OUTSIDE', '2026-11-10 08:00:00');
        $title = 'SUMMARY OF REQUESTS FOR EQUIPMENTS FOR THE PERIOD 31 OCTOBER 2026 – 02 NOVEMBER 2026';
        $this->get(route('reports.index', ['report' => 'summary'] + $this->range()))->assertOk()
            ->assertSeeText($title)->assertSeeText('BR-IN-RANGE')->assertDontSeeText('BR-OUTSIDE');

        foreach (['print', 'csv', 'pdf', 'docx', 'xlsx'] as $format) {
            $response = $this->get(route($format === 'print' ? 'reports.print' : 'reports.export', [
                'type' => 'summary', 'format' => $format,
            ] + $this->range()))->assertOk();
            $body = $format === 'csv' ? $response->streamedContent() : $response->getContent();
            if ($directory = getenv('CONTROL_REPORT_HTML_DIR')) file_put_contents($directory.'/summary-range.'.$format, $body);
            if (in_array($format, ['print', 'csv'], true)) {
                if ($format === 'print') $this->assertStringContainsString($title, $body);
                $this->assertStringContainsString('BR-IN-RANGE', $body);
                $this->assertStringNotContainsString('BR-OUTSIDE', $body);
            } elseif ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF', $body);
                $this->assertStringContainsString('%%EOF', $body);
            } else {
                $file = tempnam(sys_get_temp_dir(), 'summary-range-');
                file_put_contents($file, $body);
                try {
                    if ($format === 'docx') {
                        $zip = new \ZipArchive();
                        $this->assertTrue($zip->open($file));
                        $text = html_entity_decode(strip_tags($zip->getFromName('word/document.xml')), ENT_QUOTES | ENT_XML1);
                        $zip->close();
                    } else {
                        $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                        $text = json_encode($workbook->getActiveSheet()->toArray(), JSON_UNESCAPED_UNICODE);
                        $workbook->disconnectWorksheets();
                    }
                    $this->assertStringContainsString($title, $text);
                    $this->assertStringContainsString('BR-IN-RANGE', $text);
                    $this->assertStringNotContainsString('BR-OUTSIDE', $text);
                } finally {
                    @unlink($file);
                }
            }
        }
    }
}
