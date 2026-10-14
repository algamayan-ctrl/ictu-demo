<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Reports\ReportDataset;
use App\Reports\ReportFilters;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Equipment Utilization: the CSPC division columns plus the summary line
 * "Released outside CSPC division columns" reconcile to Released Qty.
 *
 * The outside figure is actual released quantity: External Borrower releases
 * (never assigned a CSPC division) and employee releases whose request has no
 * recorded division.
 */
class ReportEquipmentUtilizationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const OUTSIDE = 'Released outside CSPC division columns (External Borrowers / no recorded division)';

    private OrganizationalUnit $unit;

    private InventoryItem $item;

    private Carbon $from;

    private Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 4, 20, 9));
        $this->from = Carbon::create(2026, 4, 1)->startOfDay();
        $this->to = Carbon::create(2026, 4, 30)->endOfDay();

        $this->unit = OrganizationalUnit::query()->create([
            'unit_code' => 'FIXTURE', 'unit_name' => 'Fixture Unit', 'unit_type' => 'OFFICE', 'active' => true,
        ]);

        $category = InventoryCategory::query()->firstOrCreate(['category_code' => 'FIXTURE'], ['category_name' => 'Fixture Category', 'active' => true]);
        $measure = UnitOfMeasure::query()->firstOrCreate(['unit_code' => 'PC'], ['unit_name' => 'Piece', 'active' => true]);
        $this->item = InventoryItem::query()->create([
            'category_id' => $category->id, 'unit_id' => $measure->id, 'unique_description' => 'Monoblock Chairs',
            'total_quantity' => 500, 'condition_code' => 'SERVICEABLE', 'borrowable' => true,
            'off_campus_allowed' => false, 'laundry_required' => false, 'provisional' => false, 'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Each release: [affiliation_type, division_code, quantity].
     * Expected: [released total, Academic, Administration, outside].
     */
    public static function scenarios(): array
    {
        return [
            'employee only' => [
                [['EMPLOYEE', 'ACADEMIC', 5], [null, 'ADMINISTRATION', 2]],
                [7, 5, 2, 0],
            ],
            'external only' => [
                [['EXTERNAL', null, 3], ['EXTERNAL', null, 4]],
                [7, 0, 0, 7],
            ],
            'mixed employee and external' => [
                [['EMPLOYEE', 'ACADEMIC', 5], ['EXTERNAL', null, 3], [null, 'ADMINISTRATION', 1]],
                [9, 5, 1, 3],
            ],
            'legacy employee with no recorded division' => [
                [[null, null, 2], [null, 'ACADEMIC', 6]],
                [8, 6, 0, 2],
            ],
            'external with a leftover division code is never a CSPC division' => [
                [['EXTERNAL', 'ACADEMIC', 4], ['EMPLOYEE', 'ACADEMIC', 1]],
                [5, 1, 0, 4],
            ],
        ];
    }

    #[DataProvider('scenarios')]
    public function test_division_columns_and_the_outside_line_reconcile_to_released_quantity(array $releases, array $expected): void
    {
        foreach ($releases as $index => [$type, $division, $quantity]) {
            $this->release($type, $division, $quantity, Carbon::create(2026, 4, 3 + $index));
        }

        [$total, $academic, $administration, $outside] = $expected;
        $report = $this->generate();
        $row = $report->rows->firstWhere('item', 'Monoblock Chairs');

        $this->assertSame((string) $total, $row['released_quantity']);
        $this->assertSame((string) $academic, $row['division_academic']);
        $this->assertSame((string) $administration, $row['division_administration']);
        $this->assertSame($total, $report->summary['Released quantity']);
        $this->assertEquals($outside, $report->summary[self::OUTSIDE]);

        /* The columns plus the outside line add up to the total, from released quantities alone. */
        $columns = collect($report->columns)
            ->filter(fn (array $column): bool => str_starts_with($column['key'], 'division_'))
            ->sum(fn (array $column): float => (float) $row[$column['key']]);
        $this->assertEquals($total, $columns + $report->summary[self::OUTSIDE]);
    }

    public function test_a_cspc_division_filter_lists_employee_releases_only(): void
    {
        $this->release('EMPLOYEE', 'ACADEMIC', 5, Carbon::create(2026, 4, 3));
        $this->release('EXTERNAL', 'ACADEMIC', 4, Carbon::create(2026, 4, 4));

        $report = $this->generate(['division' => 'ACADEMIC']);

        $this->assertSame('5', $report->rows->firstWhere('item', 'Monoblock Chairs')['released_quantity']);
        $this->assertEquals(0, $report->summary[self::OUTSIDE]);
    }

    public function test_the_outside_line_appears_wherever_the_report_summary_appears(): void
    {
        $this->release('EMPLOYEE', 'ACADEMIC', 5, Carbon::create(2026, 4, 3));
        $this->release('EXTERNAL', null, 3, Carbon::create(2026, 4, 4));
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $query = ['academic_period' => 'month'];
        $label = 'Released outside CSPC division columns';

        $preview = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.index', $query + ['report' => 'utilization', 'generated' => 1]))->assertOk();
        /*
         * The on-screen preview carries the figure in its dataset but, by the
         * existing design for every report, renders no summary block
         * (ReportSystemPreviewTest); the summary is a document feature.
         */
        $this->assertEquals(3, $preview->viewData('dataset')->summary[self::OUTSIDE]);
        $preview->assertDontSee('class="report-system-summary-card"', false);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.print', $query + ['type' => 'utilization']))->assertOk()->assertSee($label);

        foreach (['docx' => 'word/document.xml', 'xlsx' => 'xl/sharedStrings.xml'] as $format => $part) {
            $content = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
                ->get(route('reports.export', $query + ['type' => 'utilization', 'format' => $format]))
                ->assertOk()->getContent();
            $this->assertStringContainsString($label, $this->zipPart($content, $part), strtoupper($format).' summary');
        }

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', $query + ['type' => 'utilization', 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        /* The PDF's text is compressed; check the HTML PdfExporter hands to dompdf instead. */
        $options = \App\Reports\ReportExportOptions::fromRequest(Request::create('/reports/export', 'GET', ['format' => 'pdf']), 'utilization');
        $pdfHtml = view('reports.pdf', [
            'dataset' => $this->generate(),
            'options' => ['footer' => false] + $options->contentToggles(),
            'exportOptions' => $options,
            'sealSrc' => null,
        ])->render();
        $this->assertStringContainsString($label, $pdfHtml);

        /* CSV is headers and data only for every report; it carries no summary block. */
        $csv = $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', $query + ['type' => 'utilization', 'format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString($label, $csv);
        $this->assertStringContainsString('Monoblock Chairs', $csv);
    }

    private function generate(array $input = []): ReportDataset
    {
        return app(ReportService::class)->generate(
            ReportFilters::fromRequest(Request::create('/reports', 'GET', $input), 'utilization', $this->from, $this->to, 'month')
        );
    }

    private function zipPart(string $binary, string $part): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, $binary);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $content = (string) $zip->getFromName($part);
        $zip->close();
        @unlink($path);

        return $content;
    }

    private function release(?string $type, ?string $division, int $quantity, Carbon $releasedAt): void
    {
        $borrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $this->unit->id,
        ]);

        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-UT-'.fake()->unique()->numberBetween(1000, 999999),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $this->unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);

        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => 'Utilization fixture',
            'location' => 'Campus',
            'affiliation_type' => $type,
            'external_company_name' => $type === 'EXTERNAL' ? 'Bicol Events Cooperative' : null,
            'division_code' => $division,
            'office_unit' => $division === null ? null : 'College of Computer Studies',
            'schedule_date' => $releasedAt->toDateString(),
            'return_date' => $releasedAt->copy()->addDays(2)->toDateString(),
            'needed_from' => $releasedAt->copy()->startOfDay(),
            'return_due_at' => $releasedAt->copy()->addDays(2)->endOfDay(),
        ]);

        $custody = CustodyTransaction::query()->create([
            'custody_no' => 'CUS-UT-'.fake()->unique()->numberBetween(1000, 999999),
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id,
            'status' => 'CLOSED',
            'due_at' => $releasedAt->copy()->addDays(2)->endOfDay(),
            'released_at' => $releasedAt,
            'closed_at' => $releasedAt->copy()->addDay(),
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id, 'inventory_item_id' => $this->item->id,
            'description_snapshot' => $this->item->unique_description, 'unit_snapshot' => 'Piece',
            'requested_quantity' => $quantity, 'approved_quantity' => $quantity,
        ]);
        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => $releasedAt->copy()->startOfDay(), 'period_end' => $releasedAt->copy()->addDays(2)->endOfDay(),
            'allocated_quantity' => $quantity, 'released_quantity' => $quantity, 'restored_quantity' => 0,
            'status' => 'RELEASED', 'allocated_at' => $releasedAt,
        ]);
        CustodyLine::query()->create([
            'custody_transaction_id' => $custody->id, 'request_item_id' => $requestItem->id, 'allocation_id' => $allocation->id,
            'approved_quantity' => $quantity, 'quantity_to_receive' => $quantity,
            'actual_released_quantity' => $quantity, 'returned_quantity' => $quantity,
        ]);
    }
}
