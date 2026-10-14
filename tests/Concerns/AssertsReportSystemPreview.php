<?php

namespace Tests\Concerns;

use App\Enums\AccessClassification;
use App\Models\User;
use App\Reports\ReportDataset;

/**
 * Asserts that the Reports page shows a populated report as the system
 * preview, and that the CSV export of the same scope carries the same records.
 */
trait AssertsReportSystemPreview
{
    /**
     * @param  ReportDataset  $expected  the report as its builder produces it for the current month
     * @param  array<string, string>  $filters  the report filters that produced it
     */
    protected function assertSystemPreviewShows(ReportDataset $expected, array $filters = []): void
    {
        $this->assertGreaterThan(0, $expected->count(), 'The fixture must produce records for this check to mean anything.');

        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $scope = ['report' => $expected->reportKey, 'academic_period' => 'month'] + $filters;

        $page = $this->actingAs($head)
            ->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.index', $scope + ['per_page' => 100]))
            ->assertOk();

        /* The same records, in the same order, as the builder produced. */
        $this->assertSame($expected->reportKey, $page->viewData('selectedReport'));
        $this->assertSame($expected->records(), $page->viewData('dataset')->records());
        $this->assertSame($expected->count(), $page->viewData('records')->total());
        $this->assertSame($filters, $page->viewData('reportFilters')->all());

        /* Main's compact preview keeps actual rows/counts; summary values belong to report output. */
        $page->assertSee('class="report-system-table"', false);
        $page->assertDontSee('id="report-no-results-title"', false);
        $page->assertDontSee('Republic of the Philippines', false);
        $page->assertDontSee('doc-sheet', false);
        $this->assertGreaterThan(0, count($expected->summary));
        $this->assertSame($expected->summary, $page->viewData('dataset')->summary);
        $page->assertSee('data-report-count="'.$expected->count().'"', false);

        foreach ($expected->columnLabels() as $label) {
            $page->assertSee(e($label), false);
        }

        foreach ($expected->records() as $record) {
            $page->assertSee(e((string) $record[0]), false);
        }

        /* The export of this scope carries exactly these records. */
        $csv = $this->actingAs($head)
            ->withSession(['active_workspace' => 'SPMU'])
            ->get(route('reports.export', ['type' => $expected->reportKey, 'format' => 'csv'] + $scope))
            ->assertOk()
            ->streamedContent();

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $lines = [];
        while (($line = fgetcsv($handle)) !== false) {
            $lines[] = $line;
        }
        fclose($handle);

        $headerIndex = array_search($expected->columnLabels(), $lines, true);

        $this->assertNotFalse($headerIndex, 'The CSV export has no header row matching the report columns.');
        $this->assertSame($expected->records(), array_values(array_slice($lines, $headerIndex + 1)));
    }
}
