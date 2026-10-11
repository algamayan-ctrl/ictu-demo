<?php

namespace Tests\Unit;

use App\Support\OfficialReportTitle;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OfficialReportTitleTest extends TestCase
{
    public static function periods(): array
    {
        return [
            'whole month' => ['2026-10-01', '2026-10-31', 'month', 'for the Month of October 2026'],
            'leap february' => ['2028-02-01', '2028-02-29', 'month', 'for the Month of February 2028'],
            'partial month' => ['2026-10-01', '2026-10-15', 'range', 'for the Period of October 1–15, 2026'],
            'single day' => ['2026-10-09', '2026-10-09', 'range', 'for the Period of October 9, 2026'],
            'across months' => ['2026-09-15', '2026-10-14', 'range', 'for the Period of September 15 to October 14, 2026'],
            'across years' => ['2025-12-15', '2026-01-14', 'range', 'for the Period of December 15, 2025 to January 14, 2026'],
            'whole months' => ['2026-01-01', '2026-03-31', 'months', 'for the Period of January to March 2026'],
            'whole months across years' => ['2025-11-01', '2026-02-28', 'months', 'for the Period of November 2025 to February 2026'],
            'whole year' => ['2026-01-01', '2026-12-31', 'year', 'for the Year 2026'],
        ];
    }

    #[DataProvider('periods')]
    public function test_period_phrase(string $from, string $to, string $kind, string $phrase): void
    {
        // The service passes the end date as end-of-day.
        $period = OfficialReportTitle::period(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());

        $this->assertSame($kind, $period['kind']);
        $this->assertSame($phrase, $period['phrase']);
    }

    public function test_compose_preserves_the_official_wording(): void
    {
        $this->assertSame(
            'Summary of Requests for Equipment for the Month of October 2026',
            OfficialReportTitle::compose('Summary of Requests for Equipment', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'))
        );
    }

    public function test_legacy_placeholder_titles_reduce_to_the_report_title(): void
    {
        foreach ([
            'Summary of Requests for Equipment for the month of {month_year}',
            'Summary of Requests for Equipment for the {period}',
            'Summary of Requests for Equipment {year}',
            'Summary of Requests for Equipment',
        ] as $stored) {
            $this->assertSame('Summary of Requests for Equipment', OfficialReportTitle::baseTitle($stored), $stored);
        }

        $this->assertTrue(OfficialReportTitle::isLegacy('X for the {period}'));
        $this->assertFalse(OfficialReportTitle::isLegacy('Summary of Requests for Equipment'));
        $this->assertSame(OfficialReportTitle::DEFAULT_TITLE, OfficialReportTitle::baseTitle('{month_year}'));
    }
}
