<?php

namespace App\Support;

/**
 * Links from an Analytics figure to the Records drilldown inside Reports.
 *
 * The primary click opens the exact Analytics-backed detail dataset without
 * forcing that dataset through a formal Report Type. This keeps Analytics
 * figures auditable even when no catalogue report can represent the same
 * population exactly.
 *
 * The current section, reporting period, division, unit and borrower are
 * carried forward so Back to Analytics returns to the same dashboard state.
 */
final class AnalyticsDetailLink
{
    /**
     * @param  array<string, string|int|null>  $parameters  detail-specific keys
     */
    public static function to(
        string $detail,
        string $section,
        string $periodSelection,
        ?string $division = null,
        ?string $unit = null,
        array $parameters = []
    ): string {
        $query = array_filter(
            array_merge(
                [
                    'section' => $section,
                    'academic_period' => $periodSelection,
                    'group' => self::scalar($division),
                    'unit' => self::scalar($unit),
                    /*
                     * The global Borrower filter is not one of this link's
                     * explicit parameters: every caller already reflects the
                     * currently selected division/unit for its own row, and
                     * borrower is never a per-row dimension the way those two
                     * are. It is carried forward from the current request
                     * instead, so opening a card or bucket detail can never
                     * silently drop the reader's borrower selection.
                     */
                    'borrower' => self::scalar((string) request()->query('borrower', '')),
                    'detail' => $detail,
                ],
                $parameters
            ),
            static fn ($value): bool => $value !== null && $value !== '' && $value !== 'all'
        );

        return route('reports.analytics-records', $query);
    }

    private static function scalar(?string $value): ?string
    {
        return $value === null || $value === '' || $value === 'all' ? null : $value;
    }
}
