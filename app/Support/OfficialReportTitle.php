<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The printed title of an official SPMU form is the configured Report Title
 * followed by a reporting-period phrase derived from the dates chosen in
 * Generate Official Report, e.g.
 *
 *   "Summary of Requests for Equipment" + "for the Month of October 2026"
 *
 * Revisions saved before the title and period were separated stored the period
 * as a placeholder ("... for the month of {month_year}"). Those revisions are
 * never rewritten; they are read through baseTitle() instead.
 */
final class OfficialReportTitle
{
    public const DEFAULT_TITLE = 'Summary of Requests for Equipment';

    /** Placeholders accepted by revisions saved before the separation. */
    public const LEGACY_TOKENS = ['{period}', '{month_year}', '{year}'];

    private const TOKEN_PATTERN = '\{(?:period|month_year|year)\}';

    /**
     * @return array{
     *     kind: 'month'|'months'|'year'|'range',
     *     label: string,
     *     phrase: string,
     *     text: string,
     *     coverage: string
     * }
     *
     * label    "Month of October 2026"            (shown after "for the")
     * phrase   "for the Month of October 2026"    (appended to the title)
     * text     "October 2026"                      (value for legacy placeholders)
     * coverage "October 1, 2026 – October 31, 2026" (exact inclusive dates)
     */
    public static function period(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $startsMonth = $from->day === 1;
        $endsMonth = $to->day === $to->daysInMonth;
        $sameYear = $from->year === $to->year;
        $sameMonth = $sameYear && $from->month === $to->month;

        if ($startsMonth && $endsMonth && $sameYear && $from->month === 1 && $to->month === 12) {
            $kind = 'year';
            $text = (string) $from->year;
            $label = 'Year '.$text;
        } elseif ($startsMonth && $endsMonth && $sameMonth) {
            $kind = 'month';
            $text = $from->format('F Y');
            $label = 'Month of '.$text;
        } elseif ($startsMonth && $endsMonth) {
            $kind = 'months';
            $text = $sameYear
                ? $from->format('F').' to '.$to->format('F Y')
                : $from->format('F Y').' to '.$to->format('F Y');
            $label = 'Period of '.$text;
        } else {
            $kind = 'range';
            $text = match (true) {
                $from->isSameDay($to) => $from->format('F j, Y'),
                $sameMonth => $from->format('F j').'–'.$to->format('j, Y'),
                $sameYear => $from->format('F j').' to '.$to->format('F j, Y'),
                default => $from->format('F j, Y').' to '.$to->format('F j, Y'),
            };
            $label = 'Period of '.$text;
        }

        return [
            'kind' => $kind,
            'label' => $label,
            'phrase' => 'for the '.$label,
            'text' => $text,
            'coverage' => $from->isSameDay($to)
                ? $from->format('F j, Y')
                : $from->format('F j, Y').' – '.$to->format('F j, Y'),
        ];
    }

    public static function isLegacy(?string $stored): bool
    {
        return (bool) preg_match('/'.self::TOKEN_PATTERN.'/', (string) $stored);
    }

    /**
     * The Report Title without any reporting period.
     *
     * "Summary of Requests for Equipment for the month of {month_year}"
     *   → "Summary of Requests for Equipment"
     */
    public static function baseTitle(?string $stored): string
    {
        $title = trim((string) $stored);

        if (self::isLegacy($title)) {
            $title = (string) preg_replace('/'.self::TOKEN_PATTERN.'/', '', $title);
            // Drop the period connector the placeholder used to complete.
            $title = (string) preg_replace('/\s+for(?:\s+the)?(?:\s+(?:month|period|year)(?:\s+of)?)?\s*$/i', '', $title);
        }

        $title = trim((string) preg_replace('/\s+/', ' ', $title));

        return $title !== '' ? $title : self::DEFAULT_TITLE;
    }

    /** The full printed title for the given reporting dates. */
    public static function compose(?string $stored, CarbonInterface $from, CarbonInterface $to): string
    {
        return self::baseTitle($stored).' '.self::period($from, $to)['phrase'];
    }
}
