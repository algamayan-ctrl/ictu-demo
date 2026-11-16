<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\ApprovalStage;
use App\Enums\RequestStatus;
use App\Models\ApprovalStep;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Reports\AccountabilityStatusGroups;
use App\Reports\ReportCatalogue;
use App\Reports\ReportFilters;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ControlNumberReportsRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(12, 0));
        $this->head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
    }

    private function record(string $number, ?string $submitted = '2026-11-08 08:00:00', RequestStatus $status = RequestStatus::UnderSpmu): BorrowingRequest
    {
        $borrower = User::factory()->create(['full_name' => 'Recorded '.$number]);
        $request = BorrowingRequest::create(['request_no' => $number, 'borrower_user_id' => $borrower->id, 'current_version_no' => 1, 'status' => $status]);
        $this->version($request, 1, $submitted);
        return $request;
    }

    private function version(BorrowingRequest $request, int $number, ?string $submitted): RequestVersion
    {
        return $request->versions()->create(['version_no' => $number, 'submitted_at' => $submitted, 'purpose_event' => 'Recorded workshop',
            'location' => 'CSPC', 'division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies',
            'schedule_date' => '2026-11-20', 'return_date' => '2026-11-21', 'needed_from' => '2026-11-20 08:00:00', 'return_due_at' => '2026-11-21 17:00:00']);
    }

    private function review(RequestVersion $version, string $time, string $decision = 'APPROVED', int $sequence = 2): void
    {
        ApprovalStep::create(['request_version_id' => $version->id, 'stage_code' => ApprovalStage::Spmu, 'sequence_no' => $sequence,
            'approver_user_id' => $this->head->id, 'decision' => $decision, 'received_at' => $version->submitted_at, 'decided_at' => $time]);
    }

    private function dataset(string $key, array $query = [])
    {
        $filters = ReportFilters::fromRequest(Request::create('/reports', 'GET', $query), $key, now()->startOfMonth(), now()->endOfMonth(), 'month');
        return app(ReportService::class)->generate($filters, $this->head);
    }

    public function test_overview_uses_one_latest_submitted_version_per_request_and_excludes_only_unsubmitted_records(): void
    {
        $request = $this->record('BR-OVERVIEW');
        $this->review($request->currentVersion, '2026-11-08 09:30:00');
        $draft = $this->version($request, 2, null);
        $draft->update(['office_unit' => 'Unsubmitted office']);
        $request->update(['current_version_no' => 2]);
        foreach ([RequestStatus::Rejected, RequestStatus::Cancelled, RequestStatus::Expired] as $status) $this->record('BR-'.$status->value, status: $status);
        $this->record('UNSUBMITTED', submitted: null, status: RequestStatus::Draft);
        $dataset = $this->dataset('all-transactions');
        $this->assertSame(4, $dataset->count());
        $this->assertCount(7, $dataset->columns);
        $this->assertSame('All Transactions (General Overview)', ReportCatalogue::grouped()['General']['all-transactions']);
        $row = $dataset->rows->firstWhere('request_no', 'BR-OVERVIEW');
        $this->assertSame('College of Computer Studies', $row['office_unit']);
        $this->assertSame('08/11/2026 09:30 AM', $row['received_at']);
        $this->assertSame(route('requests.show', $request), $row['_link']);
        $this->assertContains('Cancelled', $dataset->rows->pluck('status')->all());
        $this->assertNotContains('UNSUBMITTED', $dataset->rows->pluck('request_no')->all());
    }

    public function test_summary_ignores_receipt_steps_and_uses_first_real_review_of_latest_submission_for_duration(): void
    {
        $request = $this->record('BR-REVIEW');
        $this->review($request->currentVersion, '2026-11-08 08:00:00', 'RECEIVED', 1);
        $this->review($request->currentVersion, '2026-11-08 10:00:00');
        $latest = $this->version($request, 2, '2026-11-10 08:00:00');
        $this->review($latest, '2026-11-10 09:00:00', 'VERIFIED', 1);
        $this->review($latest, '2026-11-10 09:30:00');
        $request->update(['current_version_no' => 2]);
        CustodyTransaction::create(['custody_no' => 'CUS-REVIEW', 'request_id' => $request->id, 'request_version_id' => $latest->id,
            'borrower_user_id' => $request->borrower_user_id, 'status' => 'ACTIVE', 'released_at' => '2026-11-10 11:45:00', 'due_at' => '2026-11-21 17:00:00']);
        $row = $this->dataset('summary')->rows->first();
        $this->assertSame('10/11/2026 09:00 AM', $row['received_at']);
        $this->assertSame('2h 45m', $row['elapsed']);
        $this->assertSame('MET', $row['remarks']);
        $this->assertDatabaseCount('approval_steps', 4);
    }

    public function test_missing_review_does_not_fabricate_elapsed_time_even_when_release_is_recorded(): void
    {
        $request = $this->record('BR-MISSING');
        CustodyTransaction::create(['custody_no' => 'CUS-MISSING', 'request_id' => $request->id, 'request_version_id' => $request->currentVersion->id,
            'borrower_user_id' => $request->borrower_user_id, 'status' => 'ACTIVE', 'released_at' => '2026-11-10 11:45:00', 'due_at' => '2026-11-21 17:00:00']);
        $row = $this->dataset('summary')->rows->first();
        $this->assertSame('Not reviewed / not recorded', $row['received_at']);
        $this->assertSame('Not recorded', $row['elapsed']);
        $this->assertSame('MET', $row['remarks']);
    }

    public function test_old_number_search_is_shared_by_screen_and_all_matching_export_rows(): void
    {
        $one = $this->record('BR-2026-11-1'); $this->record('BR-2026-11-2');
        $this->review($one->currentVersion, '2026-11-08 08:00:00', 'PENDING');
        DB::table('request_number_aliases')->insert(['request_id' => $one->id, 'alias' => 'BR-OLD-SEARCH', 'created_at' => now()]);
        $dataset = $this->dataset('all-transactions', ['search' => 'BR-OLD-SEARCH']);
        $this->assertSame(1, $dataset->count());
        $this->assertSame('BR-2026-11-1', $dataset->rows->first()['request_no']);
        $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU']);
        $this->get(route('reports.index', ['report' => 'all-transactions', 'search' => 'BR-OLD-SEARCH']))
            ->assertOk()->assertSeeText('BR-2026-11-1')->assertDontSeeText('BR-2026-11-2')->assertSee('report-overview-eye', false);
        $csv = $this->get(route('reports.export', ['type' => 'all-transactions', 'format' => 'csv', 'search' => 'BR-OLD-SEARCH']))->assertOk()->streamedContent();
        $this->assertStringContainsString('BR-2026-11-1', $csv);
        $this->assertStringNotContainsString('BR-2026-11-2', $csv);
    }

    public function test_inventory_category_filter_preserves_balances_and_grouped_case_filters_preserve_legacy_codes(): void
    {
        $unit = UnitOfMeasure::create(['unit_code' => 'CR-PC', 'unit_name' => 'Piece']);
        foreach (['FURNITURE', 'EQUIPMENT'] as $category) {
            $cat = InventoryCategory::create(['category_code' => $category, 'category_name' => ucfirst(strtolower($category))]);
            InventoryItem::create(['category_id' => $cat->id, 'unit_id' => $unit->id, 'unique_description' => $category.' item',
                'total_quantity' => 8, 'condition_code' => 'SERVICEABLE', 'borrowable' => true, 'active' => true]);
        }
        $dataset = $this->dataset('inventory', ['category' => 'FURNITURE']);
        $this->assertSame(1, $dataset->count()); $this->assertSame('8', $dataset->rows->first()['total']);
        $this->assertTrue(AccountabilityStatusGroups::matches('GROUP_PROCESSING', 'RSLDDP_FOR_ACCOUNTING_PROCESSING', 'PROPERTY'));
        $this->assertTrue(AccountabilityStatusGroups::matches('GROUP_PAYMENT', 'RSLDDP_PAYMENT_REQUIRED', 'PROPERTY'));
        $this->assertTrue(AccountabilityStatusGroups::matches('GROUP_RESOLVED', 'CLOSED', 'PROPERTY'));
        $this->assertTrue(AccountabilityStatusGroups::matches('RSLDDP_FOR_ACCOUNTING_PROCESSING', 'RSLDDP_FOR_ACCOUNTING_PROCESSING', 'PROPERTY'));
        $this->assertFalse(AccountabilityStatusGroups::matches('GROUP_RESOLVED', 'OPEN', 'PROPERTY'));
        $this->assertCount(6, AccountabilityStatusGroups::OPTIONS);
        $this->assertArrayHasKey('ALLOCATED', ReportCatalogue::filterDefinitions()['availability_status']['options']());
    }

    public function test_overview_downloads_and_print_include_every_matching_request_across_pages(): void
    {
        foreach (range(1, 12) as $number) $this->record('BR-MATCH-'.$number);
        $this->record('BR-EXCLUDED');
        $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU']);
        $scope = ['academic_period' => 'month', 'search' => 'BR-MATCH', 'page' => 2, 'per_page' => 10];
        $page = $this->get(route('reports.index', ['report' => 'all-transactions'] + $scope))->assertOk();
        $this->assertSame(12, $page->viewData('dataset')->count());
        $this->assertSame(2, $page->viewData('records')->count());
        foreach (['print', 'csv', 'pdf', 'docx', 'xlsx'] as $format) {
            $response = $this->get(route($format === 'print' ? 'reports.print' : 'reports.export', [
                'type' => 'all-transactions', 'format' => $format] + $scope))->assertOk();
            $body = $format === 'csv' ? $response->streamedContent() : $response->getContent();
            if ($format === 'print' || $format === 'csv') {
                foreach (range(1, 12) as $number) $this->assertStringContainsString('BR-MATCH-'.$number, $body);
                $this->assertStringNotContainsString('BR-EXCLUDED', $body);
            } elseif ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF', $body);
                $this->assertStringContainsString('%%EOF', $body);
            } else {
                $file = tempnam(sys_get_temp_dir(), 'control-report-');
                file_put_contents($file, $body);
                try {
                    $zip = new \ZipArchive;
                    $this->assertTrue($zip->open($file) === true);
                    $text = $zip->getFromName($format === 'docx' ? 'word/document.xml' : 'xl/sharedStrings.xml');
                    $zip->close();
                    foreach (range(1, 12) as $number) $this->assertStringContainsString('BR-MATCH-'.$number, $text);
                    $this->assertStringNotContainsString('BR-EXCLUDED', $text);
                } finally { unlink($file); }
            }
            if ($directory = getenv('CONTROL_REPORT_HTML_DIR')) file_put_contents($directory.'/overview.'.$format, $body);
        }
    }

    public function test_closed_custody_status_remains_returned_when_legacy_closed_timestamp_is_missing(): void
    {
        $request = $this->record('BR-LEGACY-CLOSED');
        CustodyTransaction::create(['custody_no' => 'CUS-CLOSED', 'request_id' => $request->id, 'request_version_id' => $request->currentVersion->id,
            'borrower_user_id' => $request->borrower_user_id, 'status' => 'CLOSED', 'released_at' => '2026-11-10 11:45:00', 'due_at' => '2026-11-21 17:00:00']);
        $this->assertSame('Returned', $this->dataset('all-transactions')->rows->first()['status']);
        $this->assertSame(1, $this->dataset('all-transactions', ['request_status' => 'RETURNED'])->count());
    }

    public function test_compact_toolbar_keeps_advanced_filters_in_a_single_popup_and_scopes_period_choices(): void
    {
        $this->seed(\Database\Seeders\OrganizationalUnitSeeder::class);
        $this->actingAs($this->head);
        $inventory = $this->get(route('reports.index', ['report' => 'inventory']))->assertOk();
        $inventory->assertSee('name="category"', false)->assertDontSee('name="equipment"', false)
            ->assertSeeText('Previous Month')->assertDontSeeText('This Week')->assertSeeText('Print')->assertSeeText('Generate');
        $cases = $this->get(route('reports.index', ['report' => 'accountability-cases']))->assertOk();
        $cases->assertSee('data-report-more-panel', false)->assertSee('aria-modal="false"', false)
            ->assertSee('data-report-picker="borrower"', false)->assertSee('data-report-picker="unit"', false)
            ->assertSeeText('Clear fields')->assertSeeText('Processing / Compliance')->assertDontSeeText('Payment Required (Legacy)');
        if ($directory = getenv('CONTROL_REPORT_HTML_DIR')) {
            file_put_contents($directory.'/inventory.html', $inventory->getContent());
            file_put_contents($directory.'/cases.html', $cases->getContent());
            foreach (['summary', 'all-transactions', 'utilization', 'billing-settlement'] as $report) {
                file_put_contents($directory.'/'.$report.'.html', $this->get(route('reports.index', ['report' => $report]))->assertOk()->getContent());
            }
        }
        $this->actingAs(User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]))
            ->get(route('reports.index', ['report' => 'all-transactions']))->assertForbidden();
    }
}
