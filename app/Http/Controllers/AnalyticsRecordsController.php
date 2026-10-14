<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Models\AcademicPeriod;
use App\Services\AnalyticsDetailService;
use App\Services\AnalyticsService;
use App\Services\ReportingPeriodService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Record drilldown for Analytics.
 *
 * This route deliberately does not resolve a ReportCatalogue key. It reuses
 * AnalyticsDetailService so the rows come from the same queries that produced
 * the clicked analytics figure, even when no formal report can represent that
 * population exactly.
 */
class AnalyticsRecordsController extends Controller
{
    public function __invoke(
        Request $request,
        AnalyticsService $analytics,
        AnalyticsDetailService $details,
        ReportingPeriodService $periods
    ): View {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::SpmuHead,
            403
        );

        \App\Support\AnalyticsFilters::validate($request);

        $academicPeriods = AcademicPeriod::query()
            ->orderByRaw("CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->get();

        $activeAcademicPeriod = $academicPeriods->first(
            fn (AcademicPeriod $period): bool => $period->status === 'ACTIVE'
        );

        [$from, $to, $selectedAcademicPeriod, $periodSelection] = $periods->resolve(
            $request,
            $academicPeriods,
            $activeAcademicPeriod
        );

        /* Same Borrower Type rule as the Analytics page that linked here. */
        $borrowerType = (string) $request->input('borrower_type', 'all');
        if (! array_key_exists($borrowerType, AnalyticsService::borrowerTypes())) {
            $borrowerType = 'all';
        }
        $analytics->forBorrowerType($borrowerType);
        $details = $details->using($analytics);
        $externalOnly = $borrowerType === \App\Enums\RegistrationType::External->value;

        $division = $externalOnly ? 'all' : (string) $request->input('group', 'all');
        if (! array_key_exists($division, AnalyticsService::divisions())) {
            $division = 'all';
        }

        $scheduledScope = $request->input('for') === 'scheduled.summary';
        [$scopeFrom, $scopeTo] = $scheduledScope ? $periods->nextScheduledWindow($from, $to) : [$from, $to];
        $unitOptions = $scheduledScope ? $analytics->scheduledUnitOptions($scopeFrom, $scopeTo) : $analytics->unitOptions($from, $to);
        $unit = $externalOnly ? 'all' : (string) $request->input('unit', 'all');
        $selectableUnits = $division === 'all'
            ? $unitOptions->flatten()->unique()->sort()->values()->all()
            : ($unitOptions[$division] ?? []);

        if ($unit !== 'all' && ! in_array($unit, $selectableUnits, true)) {
            $unit = 'all';
        }

        $divisionFilter = $division === 'all' ? null : $division;
        $unitFilter = $unit === 'all' ? null : $unit;

        /* Borrower Type replaced the individual Borrower filter in Analytics. */
        $borrowerFilter = null;

        $detailKey = trim((string) $request->input('detail', ''));
        abort_if($detailKey === '', 404);

        $detail = $details->resolve(
            $detailKey,
            $request,
            $from,
            $to,
            $divisionFilter,
            $unitFilter,
            $periodSelection,
            $borrowerFilter,
            PHP_INT_MAX
        );

        abort_if($detail === null, 404);

        $backQuery = $request->query();
        unset($backQuery['detail']);

        return view('reports.analytics-records', [
            'detail' => $detail,
            'detailKey' => $detailKey,
            'from' => $from,
            'to' => $to,
            'periodSelection' => $periodSelection,
            'selectedAcademicPeriod' => $selectedAcademicPeriod,
            'selectedBorrowerLabel' => null,
            'backToAnalyticsUrl' => route('analytics.index', $backQuery),
        ]);
    }
}
