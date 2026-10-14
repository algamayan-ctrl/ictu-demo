<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Reporting period resolution shared by Reports and Analytics.
 *
 * Both modules must answer "which dates does 'This Semester' mean?" the same
 * way, otherwise the same filter would produce different numbers on the two
 * pages. This is the single answer; the logic is unchanged from the version
 * that previously lived inside ReportController.
 */
class ReportingPeriodService
{
    public const REPORT_PERIODS = [
        'week' => 'This Week',
        'month' => 'This Month',
        'semester' => 'Current Semester',
        'previous_semester' => 'Previous Semester',
        'academic_year' => 'Current Academic Year',
        'last_month' => 'Last Month',
        'quarter' => 'This Quarter',
        'year' => 'This Year',
        'custom' => 'Custom Range',
    ];

    /** Existing next-window display convention, independent of any prediction. */
    public function nextScheduledWindow(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        if ($start->year === $end->year && $start->month === $end->month
            && $start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($end->copy()->endOfMonth())) {
            $next = $start->copy()->addMonth()->startOfMonth();
            return [$next, $next->copy()->endOfMonth()->endOfDay()];
        }
        $days = max(1, (int) $start->diffInDays($end->copy()->startOfDay()) + 1);
        $next = $end->copy()->addDay()->startOfDay();
        return [$next, $next->copy()->addDays($days - 1)->endOfDay()];
    }

    /** Report-only ranges; the shared Analytics resolver keeps its existing behavior. */
    public function resolveForReport(Request $request, $academicPeriods, ?AcademicPeriod $activeAcademicPeriod): array
    {
        $selection = strtolower(trim((string) $request->input('academic_period', '')));
        $required = $selection === 'custom' ? ['required'] : [];
        $dates = $request->validate([
            'start_date' => [...$required, 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => [...$required, 'required_with:start_date', 'date_format:Y-m-d'],
        ]);
        // Validate both date values before Laravel compares them (including malformed arrays).
        $request->validate(['end_date' => ['after_or_equal:start_date']]);

        if ($selection === 'custom') {
            return [
                Carbon::parse($dates['start_date'])->startOfDay(),
                Carbon::parse($dates['end_date'])->endOfDay(),
                null,
                'custom',
            ];
        }

        if ($selection === 'previous_semester') {
            $previous = $this->previousSemester($academicPeriods, $activeAcademicPeriod);

            if ($previous) {
                return [
                    Carbon::parse($previous->start_date)->startOfDay(),
                    Carbon::parse($previous->end_date)->endOfDay(),
                    $previous,
                    'previous_semester',
                ];
            }
        }

        $today = now();
        $range = match ($selection) {
            'last_month' => [$today->copy()->startOfMonth()->subMonth(), $today->copy()->startOfMonth()->subDay()->endOfDay()],
            'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => null,
        };

        return $range
            ? [$range[0], $range[1], null, $selection]
            : $this->resolve($request, $academicPeriods, $activeAcademicPeriod);
    }

    /**
     * The configured academic period immediately before the active one: the
     * latest period that ended before the active period started. Null when no
     * period is active or none precedes it, so the builder can say the range
     * is not configured instead of showing another period's records.
     *
     * @param  \Illuminate\Support\Collection<int, AcademicPeriod>  $academicPeriods
     */
    public function previousSemester($academicPeriods, ?AcademicPeriod $activeAcademicPeriod): ?AcademicPeriod
    {
        if (! $activeAcademicPeriod) {
            return null;
        }

        $activeStart = Carbon::parse($activeAcademicPeriod->start_date)->startOfDay();

        return $academicPeriods
            ->filter(fn (AcademicPeriod $period): bool => $period->id !== $activeAcademicPeriod->id
                && Carbon::parse($period->end_date)->startOfDay()->lt($activeStart))
            ->sortByDesc(fn (AcademicPeriod $period): int => Carbon::parse($period->end_date)->getTimestamp())
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AcademicPeriod>  $academicPeriods
     * @return array{0:Carbon,1:Carbon,2:?AcademicPeriod,3:string}
     */
    public function resolve(
        Request $request,
        $academicPeriods,
        ?AcademicPeriod $activeAcademicPeriod
    ): array {
        $selection = strtolower(trim((string) $request->input('academic_period', '')));

        // Backward-compatible handling for old links that stored a period ID.
        if ($selection !== '' && ctype_digit($selection)) {
            $legacyPeriod = $academicPeriods->first(
                fn (AcademicPeriod $period): bool => (string) $period->id === $selection
            );

            if ($legacyPeriod) {
                return [
                    Carbon::parse($legacyPeriod->start_date)->startOfDay(),
                    Carbon::parse($legacyPeriod->end_date)->endOfDay(),
                    $legacyPeriod,
                    'semester',
                ];
            }
        }

        if (! in_array($selection, ['week', 'month', 'semester', 'academic_year'], true)) {
            $selection = $activeAcademicPeriod ? 'semester' : 'month';
        }

        if ($selection === 'week') {
            return [now()->startOfWeek()->startOfDay(), now()->endOfWeek()->endOfDay(), null, 'week'];
        }

        if ($selection === 'month') {
            return [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay(), null, 'month'];
        }

        if ($selection === 'semester' && $activeAcademicPeriod) {
            return [
                Carbon::parse($activeAcademicPeriod->start_date)->startOfDay(),
                Carbon::parse($activeAcademicPeriod->end_date)->endOfDay(),
                $activeAcademicPeriod,
                'semester',
            ];
        }

        if ($selection === 'academic_year' && $activeAcademicPeriod) {
            $yearPeriods = $academicPeriods->where('academic_year', $activeAcademicPeriod->academic_year);
            $fromDate = $yearPeriods->min('start_date') ?: $activeAcademicPeriod->start_date;
            $toDate = $yearPeriods->max('end_date') ?: $activeAcademicPeriod->end_date;

            return [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
                null,
                'academic_year',
            ];
        }

        // When no active period is configured, semester/year safely fall back
        // to the current calendar month instead of exposing arbitrary dates.
        return [now()->startOfMonth()->startOfDay(), now()->endOfMonth()->endOfDay(), null, 'month'];
    }
}
