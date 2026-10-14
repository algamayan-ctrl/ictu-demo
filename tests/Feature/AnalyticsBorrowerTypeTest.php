<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Reports\ReportDataset;
use App\Reports\ReportFilters;
use App\Services\AnalyticsService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Borrower Type (All Types / CSPC Employee / External Borrower) in Analytics,
 * and External Borrower rows in Reports.
 *
 * The type is read from the saved request-version snapshot exactly as
 * DocumentAffiliationService reads it: EXTERNAL is an External Borrower;
 * EMPLOYEE and legacy versions without affiliation_type are CSPC Employees.
 */
class AnalyticsBorrowerTypeTest extends TestCase
{
    use RefreshDatabase;

    private const COMPANY = 'Bicol Events Cooperative';

    private AnalyticsService $analytics;

    private OrganizationalUnit $unit;

    private Carbon $from;

    private Carbon $to;

    private User $head;

    private User $legacyEmployee;

    private User $employee;

    private User $external;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 4, 20, 9));

        $this->analytics = app(AnalyticsService::class);
        $this->from = Carbon::create(2026, 4, 1)->startOfDay();
        $this->to = Carbon::create(2026, 4, 30)->endOfDay();

        $this->unit = OrganizationalUnit::query()->create([
            'unit_code' => 'FIXTURE', 'unit_name' => 'Fixture Unit', 'unit_type' => 'OFFICE', 'active' => true,
        ]);
        $this->head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);

        $this->legacyEmployee = $this->borrower('Legacy Employee');
        $this->employee = $this->borrower('Recorded Employee');
        $this->external = $this->borrower('External Contact');

        $chairs = $this->item('Monoblock Chairs');

        /* Historical request: no affiliation_type was recorded. Returned on time. */
        $this->request($this->legacyEmployee, Carbon::create(2026, 4, 2), $chairs, 5, [], [
            'released_at' => Carbon::create(2026, 4, 3), 'due_at' => Carbon::create(2026, 4, 10), 'closed_at' => Carbon::create(2026, 4, 9),
        ]);

        /* Employee request recorded with EMPLOYEE. Returned late. */
        $this->request($this->employee, Carbon::create(2026, 4, 4), $chairs, 4, ['affiliation_type' => 'EMPLOYEE'], [
            'released_at' => Carbon::create(2026, 4, 5), 'due_at' => Carbon::create(2026, 4, 8), 'closed_at' => Carbon::create(2026, 4, 12),
        ]);

        /* External Borrower: saved company, no CSPC division or office. Returned on time, one open incident. */
        $externalRequest = $this->request($this->external, Carbon::create(2026, 4, 6), $chairs, 3, [
            'affiliation_type' => 'EXTERNAL', 'external_company_name' => self::COMPANY,
            'division_code' => null, 'office_unit' => null,
        ], [
            'released_at' => Carbon::create(2026, 4, 7), 'due_at' => Carbon::create(2026, 4, 14), 'closed_at' => Carbon::create(2026, 4, 13),
        ]);

        Incident::query()->create([
            'incident_no' => 'PC-EXTERNAL-OPEN',
            'custody_transaction_id' => $externalRequest->custody()->value('id'),
            'borrower_user_id' => $this->external->id,
            'reported_by_user_id' => $this->external->id,
            'incident_type' => 'DAMAGE',
            'status' => 'OPEN',
            'reported_at' => Carbon::create(2026, 4, 13),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */
    /* Classification and scope                                            */
    /* ------------------------------------------------------------------ */

    public function test_all_types_includes_both_borrower_classifications(): void
    {
        $this->assertSame(3, $this->overviewTotal(null));
        $this->assertSame(12.0, (float) $this->for(null, fn () => $this->analytics->demandTotals($this->from, $this->to, null, null)['released_quantity']));
    }

    public function test_employee_only_and_external_only_results(): void
    {
        $this->assertSame(2, $this->overviewTotal('EMPLOYEE'));
        $this->assertSame(1, $this->overviewTotal('EXTERNAL'));

        $this->assertSame(9.0, (float) $this->for('EMPLOYEE', fn () => $this->analytics->demandTotals($this->from, $this->to, null, null)['released_quantity']));
        $this->assertSame(3.0, (float) $this->for('EXTERNAL', fn () => $this->analytics->demandTotals($this->from, $this->to, null, null)['released_quantity']));

        $returns = fn (?string $type) => $this->for($type, fn () => $this->analytics->returns($this->from, $this->to, null, null));
        $this->assertSame([2, 1], [$returns(null)['on_time'], $returns(null)['late']]);
        $this->assertSame([1, 1], [$returns('EMPLOYEE')['on_time'], $returns('EMPLOYEE')['late']]);
        $this->assertSame([1, 0], [$returns('EXTERNAL')['on_time'], $returns('EXTERNAL')['late']]);

        /* Accountability: the only open case belongs to the External Borrower. */
        $open = fn (?string $type) => $this->for($type, fn () => $this->analytics->openAccountabilityRecords($this->from, $this->to, null, null)->count());
        $this->assertSame([1, 0, 1], [$open(null), $open('EMPLOYEE'), $open('EXTERNAL')]);
    }

    public function test_a_cspc_division_or_unit_filter_excludes_external_borrowers(): void
    {
        $this->assertSame(2, $this->overviewTotal(null, 'ACADEMIC'));
        $this->assertSame(2, $this->overviewTotal(null, 'ACADEMIC', 'College of Computer Studies'));
        $this->assertSame(0, $this->overviewTotal('EXTERNAL', 'ACADEMIC'));
        $this->assertSame(
            0,
            $this->for(null, fn () => $this->analytics->openAccountabilityRecords($this->from, $this->to, 'ACADEMIC', null)->count())
        );
    }

    public function test_historical_requests_without_a_recorded_type_count_as_cspc_employees(): void
    {
        $this->assertSame(1, $this->for('EMPLOYEE', fn () => $this->analytics->overview($this->from, $this->to, null, null, $this->legacyEmployee->id)['total']));
        $this->assertSame(0, $this->for('EXTERNAL', fn () => $this->analytics->overview($this->from, $this->to, null, null, $this->legacyEmployee->id)['total']));

        /* External Borrowers are their own distribution row, not "Unspecified division". */
        $groups = collect($this->for(null, fn () => $this->analytics->borrowerGroups($this->from, $this->to, null, null))['groups']);
        $this->assertSame(['ACADEMIC' => 2, 'EXTERNAL' => 1], $groups->pluck('count', 'code')->all());
        $this->assertSame('External Borrower', $groups->firstWhere('code', 'EXTERNAL')['label']);
        $this->assertNull($groups->firstWhere('label', 'Unspecified division'));
    }

    /* ------------------------------------------------------------------ */
    /* Analytics page                                                      */
    /* ------------------------------------------------------------------ */

    public function test_the_four_filters_are_in_order_and_borrower_type_replaces_the_borrower_picker(): void
    {
        $page = $this->page(['section' => 'overview'])->assertOk();
        $html = $page->getContent();

        $positions = array_map(fn (string $id) => strpos($html, 'id="'.$id.'"'), [
            'analytics-period', 'analytics-borrower-type', 'analytics-group', 'analytics-unit',
        ]);
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all(), 'Filters must read Period, Borrower Type, Division, Office.');

        $this->assertSame(1, preg_match('~<form class="analytics-filters".*?</form>~s', $html, $form));
        $filters = $form[0];

        foreach (['name="borrower_type"', '>All Types<', '>CSPC Employee<', '>External Borrower<'] as $expected) {
            $this->assertStringContainsString($expected, $filters);
        }

        /* Borrower Type replaced the borrower-name picker; filters still apply on change, with no Apply button. */
        $this->assertStringNotContainsString('data-borrower-picker', $filters);
        $this->assertStringNotContainsString('name="borrower"', $filters);
        $this->assertStringNotContainsString('type="submit"', $filters);
        $this->assertSame(4, substr_count($filters, 'onchange="this.form.submit()"'));
    }

    public function test_external_borrower_clears_and_disables_division_and_unit(): void
    {
        $page = $this->page(['section' => 'overview', 'borrower_type' => 'EXTERNAL', 'group' => 'ACADEMIC', 'unit' => 'College of Computer Studies'])
            ->assertOk()
            ->assertViewHas('selectedBorrowerType', 'EXTERNAL')
            ->assertViewHas('selectedDivision', 'all')
            ->assertViewHas('selectedUnit', 'all')
            ->assertViewHas('overview', fn (array $overview) => $overview['total'] === 1);

        $this->assertSame(2, substr_count($page->getContent(), '>Not applicable</option>'));
        $this->assertMatchesRegularExpression('~<select id="analytics-group"[^>]*disabled~', $page->getContent());
        $this->assertMatchesRegularExpression('~<select id="analytics-unit"[^>]*disabled~', $page->getContent());

        /* Tabs keep the Borrower Type. */
        $page->assertSee('borrower_type=EXTERNAL', false);
    }

    public function test_the_four_modules_agree_for_every_borrower_type(): void
    {
        foreach ([null => [3, 12.0, 3], 'EMPLOYEE' => [2, 9.0, 2], 'EXTERNAL' => [1, 3.0, 1]] as $type => [$requests, $released, $completed]) {
            $query = array_filter(['borrower_type' => $type ?: null]);

            $this->page($query + ['section' => 'overview'])->assertOk()
                ->assertViewHas('overview', fn ($overview) => $overview['total'] === $requests);
            $this->page($query + ['section' => 'demand'])->assertOk()
                ->assertViewHas('totals', fn ($totals) => (float) $totals['released_quantity'] === $released);
            $this->page($query + ['section' => 'inventory'])->assertOk()
                ->assertViewHas('released', fn ($equipment) => (float) collect($equipment['items'])->sum('released') === $released);
            $this->page($query + ['section' => 'returns'])->assertOk()
                ->assertViewHas('returns', fn ($returns) => $returns['completed'] === $completed);
        }
    }

    public function test_card_details_and_record_lists_reconcile_with_the_selected_type(): void
    {
        foreach ([null => 3, 'EMPLOYEE' => 2, 'EXTERNAL' => 1] as $type => $expected) {
            $query = array_filter(['borrower_type' => $type ?: null]);

            $records = $this->actingAs($this->head)->get(route('reports.analytics-records', $query + [
                'section' => 'overview', 'academic_period' => 'month', 'detail' => 'requests',
            ]))->assertOk();

            $rows = $records->viewData('detail')['table']['rows'] ?? null;
            $this->assertIsArray($rows, 'The records detail exposes its rows.');
            $this->assertCount($expected, $rows, 'Records for '.($type ?? 'All Types'));
        }

        /* An External row names the recorded company in its Office / Unit cell. */
        $this->actingAs($this->head)->get(route('reports.analytics-records', [
            'section' => 'overview', 'academic_period' => 'month', 'detail' => 'requests', 'borrower_type' => 'EXTERNAL',
        ]))->assertOk()->assertSee(self::COMPANY)->assertDontSee('Legacy Employee')->assertDontSee('Recorded Employee');

        /* The overview's own detail links carry the Borrower Type to the record list. */
        $this->page(['section' => 'overview', 'borrower_type' => 'EMPLOYEE'])
            ->assertSee('borrower_type=EMPLOYEE', false);
    }

    public function test_switching_back_from_external_restores_normal_division_and_unit_dropdowns(): void
    {
        $this->page(['section' => 'overview', 'borrower_type' => 'EXTERNAL'])->assertOk()
            ->assertSee('>Not applicable</option>', false);

        foreach (['EMPLOYEE', null] as $type) {
            $page = $this->page(array_filter(['section' => 'overview', 'borrower_type' => $type, 'group' => 'ACADEMIC']))
                ->assertOk()
                ->assertViewHas('selectedDivision', 'ACADEMIC')
                ->assertDontSee('>Not applicable</option>', false);
            $html = $page->getContent();

            $this->assertDoesNotMatchRegularExpression('~<select id="analytics-group"[^>]*disabled~', $html);
            $this->assertDoesNotMatchRegularExpression('~<select id="analytics-unit"[^>]*disabled~', $html);
            $this->assertStringContainsString('>All divisions</option>', $html);
            $this->assertMatchesRegularExpression('~<option value="ACADEMIC"\s+selected~', $html);
        }
    }

    public function test_on_custody_overdue_and_their_records_follow_the_type_while_stock_stays_system_wide(): void
    {
        /* One External custody still out and past due on 20 April. */
        $this->request($this->external, Carbon::create(2026, 4, 8), $this->item('Folding Tables'), 2, [
            'affiliation_type' => 'EXTERNAL', 'external_company_name' => self::COMPANY,
            'division_code' => null, 'office_unit' => null,
        ], [
            'status' => 'ACTIVE', 'returned' => 0,
            'released_at' => Carbon::create(2026, 4, 9), 'due_at' => Carbon::create(2026, 4, 15), 'closed_at' => null,
        ]);

        $overview = fn (?string $type) => $this->for($type, fn () => $this->analytics->overview($this->from, $this->to, null, null));
        $returns = fn (?string $type) => $this->for($type, fn () => $this->analytics->returns($this->from, $this->to, null, null));

        $this->assertSame([1, 0, 1], [$overview(null)['on_custody'], $overview('EMPLOYEE')['on_custody'], $overview('EXTERNAL')['on_custody']]);
        $this->assertSame([1, 0, 1], [$returns(null)['overdue'], $returns('EMPLOYEE')['overdue'], $returns('EXTERNAL')['overdue']]);

        /* The currently-out record list agrees with the KPI for each type. */
        foreach ([null => 1, 'EMPLOYEE' => 0, 'EXTERNAL' => 1] as $type => $expected) {
            $records = $this->actingAs($this->head)->get(route('reports.analytics-records', array_filter([
                'section' => 'overview', 'academic_period' => 'month', 'detail' => 'currently-out', 'borrower_type' => $type ?: null,
            ])))->assertOk();
            $this->assertCount($expected, $records->viewData('detail')['table']['rows'] ?? [], 'Currently out for '.($type ?: 'All Types'));
        }

        /* Current stock is a system-wide inventory figure, not a borrower figure. */
        $inventory = app(\App\Services\InventoryService::class);
        $stock = fn (?string $type) => $this->for($type, fn () => $this->analytics->inventory($inventory)['totals']);
        $this->assertSame($stock(null), $stock('EXTERNAL'));
        $this->assertSame($stock(null), $stock('EMPLOYEE'));
    }

    public function test_links_into_reports_never_carry_a_hidden_borrower_type(): void
    {
        $html = $this->page(['section' => 'demand', 'borrower_type' => 'EXTERNAL', 'detail' => 'requests'])->assertOk()->getContent();

        preg_match_all('~href="([^"]+)"~', $html, $links);
        $reportLinks = collect($links[1])
            ->map(fn (string $href): string => html_entity_decode($href))
            ->filter(fn (string $href): bool => str_starts_with($href, route('reports.index').'?'));

        $this->assertNotEmpty($reportLinks, 'The detail offers its Reports source link.');
        foreach ($reportLinks as $href) {
            $this->assertStringNotContainsString('borrower_type', $href);
            $this->assertStringNotContainsString('borrower=', $href);
        }
    }

    public function test_equipment_utilization_total_includes_external_releases_outside_the_division_columns(): void
    {
        $report = $this->generate('utilization');
        $chairs = $report->rows->firstWhere('item', 'Monoblock Chairs');

        /* Overall total: every release, employee and external. */
        $this->assertSame('12', $chairs['released_quantity']);
        $this->assertSame(12, $report->summary['Released quantity']);

        /* CSPC division columns hold employee releases only; External is never assigned a division. */
        $divisionTotal = collect($report->columns)
            ->filter(fn (array $column): bool => str_starts_with($column['key'], 'division_'))
            ->sum(fn (array $column): float => (float) $chairs[$column['key']]);
        $this->assertSame(9.0, (float) $divisionTotal);
        $this->assertSame('9', $chairs['division_academic']);

        /* The summary line reconciles the columns to the total: the External release. */
        $this->assertEquals(3, $report->summary['Released outside CSPC division columns (External Borrowers / no recorded division)']);
    }

    public function test_the_division_donut_names_external_demand_and_lists_only_applicable_rows(): void
    {
        $card = function (array $query): array {
            $html = $this->page($query + ['section' => 'demand'])->assertOk()->getContent();
            $this->assertSame(1, preg_match('~<section id="demand-by-division".*?</section>~s', $html, $section));
            preg_match_all('~<span class="analytics-donut-name">([^<]+)</span>\s*<span class="analytics-donut-figure">(\d+) <small> - (\d+)%~', $section[0], $rows, PREG_SET_ORDER);
            preg_match('~<h2>([^<]+)</h2>~', $section[0], $title);
            preg_match('~<span class="analytics-donut-centre">\s*<strong>(\d+)</strong>~', $section[0], $total);

            return [
                'title' => $title[1] ?? null,
                'rows' => collect($rows)->mapWithKeys(fn ($row) => [html_entity_decode(trim($row[1]), ENT_QUOTES) => [(int) $row[2], (int) $row[3]]])->all(),
                'total' => isset($total[1]) ? (int) $total[1] : null,
                'arcs' => substr_count($section[0], 'class="analytics-donut-arc'),
                'html' => $section[0],
            ];
        };

        /* All Types: CSPC divisions (zeros included, as before) plus External Borrower as its own row. */
        $all = $card([]);
        $this->assertSame('Demand by Division', $all['title']);
        $this->assertSame(3, $all['total']);
        $this->assertSame([2, 67], $all['rows']['Academic']);
        $this->assertSame([1, 33], $all['rows']['External Borrower']);
        foreach (AnalyticsService::divisions() as $divisionLabel) {
            $this->assertArrayHasKey($divisionLabel, $all['rows'], 'All Types keeps every CSPC division row.');
        }
        $this->assertSame(2, $all['arcs']);

        /* CSPC Employee: the CSPC division composition only. */
        $employee = $card(['borrower_type' => 'EMPLOYEE']);
        $this->assertSame('Demand by Division', $employee['title']);
        $this->assertSame(2, $employee['total']);
        $this->assertSame([2, 100], $employee['rows']['Academic']);
        $this->assertArrayNotHasKey('External Borrower', $employee['rows']);

        /* External Borrower: the same card, retitled, with only the External row and the actual total. */
        $external = $card(['borrower_type' => 'EXTERNAL']);
        $this->assertSame('External Borrower Demand', $external['title']);
        $this->assertSame(1, $external['total']);
        $this->assertSame(['External Borrower' => [1, 100]], $external['rows']);
        $this->assertSame(1, $external['arcs']);
        $this->assertStringContainsString('analytics-donut-arc is-external', $external['html']);
        $this->assertStringNotContainsString('analytics-donut-key is-academic', $external['html']);
    }

    public function test_external_demand_with_no_requests_shows_its_own_empty_state(): void
    {
        /* This week (20-26 April) holds no requests at all. */
        $html = $this->page(['section' => 'demand', 'borrower_type' => 'EXTERNAL', 'academic_period' => 'week'])->assertOk()->getContent();
        $this->assertSame(1, preg_match('~<section id="demand-by-division".*?</section>~s', $html, $section));

        $this->assertStringContainsString('<h2>External Borrower Demand</h2>', $section[0]);
        $this->assertStringContainsString('No External Borrower requests during this period.', $section[0]);
        $this->assertStringNotContainsString('analytics-donut-legend', $section[0]);
        $this->assertStringNotContainsString('analytics-donut-arc', $section[0]);

        /* All Types keeps its original empty state. */
        $this->page(['section' => 'demand', 'academic_period' => 'week'])->assertOk()
            ->assertSee('No borrowing activity by division during this period.');
    }

    public function test_analytics_still_offers_exactly_four_modules_and_four_filters(): void
    {
        $this->assertSame(['overview', 'demand', 'inventory', 'returns'], array_keys(\App\Http\Controllers\AnalyticsController::SECTIONS));

        $html = $this->page(['section' => 'overview'])->assertOk()->getContent();
        $this->assertSame(1, preg_match('~<form class="analytics-filters".*?</form>~s', $html, $form));
        $this->assertSame(4, substr_count($form[0], '<select '));
    }

    /* ------------------------------------------------------------------ */
    /* Reports                                                             */
    /* ------------------------------------------------------------------ */

    public function test_reports_include_external_rows_with_the_recorded_company(): void
    {
        $all = $this->generate('borrowing');
        $this->assertCount(3, $all->rows);

        $externalRow = $all->rows->first(fn (array $row): bool => ($row['division'] ?? null) === 'External Borrower');
        $this->assertNotNull($externalRow, 'The External request appears in Borrowing Activity.');
        $this->assertSame(self::COMPANY, $externalRow['office_unit']);

        /* A CSPC division in Reports lists that division's employee requests only. */
        $this->assertCount(2, $this->generate('borrowing', ['division' => 'ACADEMIC'])->rows);

        foreach (['custody', 'returns'] as $report) {
            $this->assertTrue(
                $this->generate($report)->rows->contains(fn (array $row): bool => str_contains(json_encode($row), self::COMPANY)),
                "{$report} lists the External Borrower with the recorded company."
            );
        }
    }

    public function test_report_preview_and_exports_carry_the_same_external_rows(): void
    {
        $query = ['academic_period' => 'month', 'report' => 'borrowing', 'generated' => 1];

        $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.index', $query))->assertOk()->assertSee(self::COMPANY);

        $csv = $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', ['type' => 'borrowing', 'format' => 'csv', 'academic_period' => 'month']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString(self::COMPANY, $csv);
        foreach (['Legacy Employee', 'Recorded Employee', 'External Contact'] as $name) {
            $this->assertStringContainsString($name, $csv);
        }

        foreach (['docx' => 'word/document.xml', 'xlsx' => 'xl/sharedStrings.xml'] as $format => $part) {
            $content = $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU'])
                ->get(route('reports.export', ['type' => 'borrowing', 'format' => $format, 'academic_period' => 'month']))
                ->assertOk()->getContent();
            $this->assertStringContainsString(self::COMPANY, $this->zipPart($content, $part), strtoupper($format).' export');
        }

        $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', ['type' => 'borrowing', 'format' => 'pdf', 'academic_period' => 'month']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function for(?string $type, callable $callback): mixed
    {
        $this->analytics->forBorrowerType($type);

        try {
            return $callback();
        } finally {
            $this->analytics->forBorrowerType(null);
        }
    }

    private function overviewTotal(?string $type, ?string $division = null, ?string $unit = null): int
    {
        return $this->for($type, fn () => $this->analytics->overview($this->from, $this->to, $division, $unit)['total']);
    }

    private function page(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->head)->get(route('analytics.index', $query + ['academic_period' => 'month']));
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

    private function generate(string $report, array $input = []): ReportDataset
    {
        return app(ReportService::class)->generate(
            ReportFilters::fromRequest(Request::create('/reports', 'GET', $input), $report, $this->from, $this->to, 'month')
        );
    }

    private function borrower(string $name): User
    {
        return User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $this->unit->id,
            'full_name' => $name,
        ]);
    }

    private function item(string $description): InventoryItem
    {
        $category = InventoryCategory::query()->firstOrCreate(['category_code' => 'FIXTURE'], ['category_name' => 'Fixture Category', 'active' => true]);
        $measure = UnitOfMeasure::query()->firstOrCreate(['unit_code' => 'PC'], ['unit_name' => 'Piece', 'active' => true]);

        return InventoryItem::query()->create([
            'category_id' => $category->id, 'unit_id' => $measure->id, 'unique_description' => $description,
            'total_quantity' => 500, 'condition_code' => 'SERVICEABLE', 'borrowable' => true,
            'off_campus_allowed' => false, 'laundry_required' => false, 'provisional' => false, 'active' => true,
        ]);
    }

    private function request(User $borrower, Carbon $createdAt, InventoryItem $item, int $quantity, array $version, array $custody): BorrowingRequest
    {
        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-'.$createdAt->format('YmdHis').'-'.fake()->unique()->numberBetween(1000, 99999),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $this->unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);
        $request->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $saved = RequestVersion::query()->create(array_merge([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => 'Fixture activity',
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => 'College of Computer Studies',
            'schedule_date' => $createdAt->copy()->addDay()->toDateString(),
            'return_date' => $createdAt->copy()->addDays(3)->toDateString(),
            'needed_from' => $createdAt->copy()->addDay()->startOfDay(),
            'return_due_at' => $createdAt->copy()->addDays(3)->endOfDay(),
        ], $version));

        $transaction = CustodyTransaction::query()->create([
            'custody_no' => 'CUS-'.$createdAt->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 99999),
            'request_id' => $request->id,
            'request_version_id' => $saved->id,
            'borrower_user_id' => $borrower->id,
            'status' => $custody['status'] ?? 'CLOSED',
            'due_at' => $custody['due_at'],
            'released_at' => $custody['released_at'],
            'closed_at' => $custody['closed_at'],
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $saved->id, 'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description, 'unit_snapshot' => 'Piece',
            'requested_quantity' => $quantity, 'approved_quantity' => $quantity,
        ]);
        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => $createdAt->copy()->addDay()->startOfDay(), 'period_end' => $createdAt->copy()->addDays(3)->endOfDay(),
            'allocated_quantity' => $quantity, 'released_quantity' => $quantity, 'restored_quantity' => 0,
            'status' => 'RELEASED', 'allocated_at' => $createdAt,
        ]);
        CustodyLine::query()->create([
            'custody_transaction_id' => $transaction->id, 'request_item_id' => $requestItem->id, 'allocation_id' => $allocation->id,
            'approved_quantity' => $quantity, 'quantity_to_receive' => $quantity,
            'actual_released_quantity' => $quantity, 'returned_quantity' => $custody['returned'] ?? $quantity,
        ]);

        return $request;
    }
}
