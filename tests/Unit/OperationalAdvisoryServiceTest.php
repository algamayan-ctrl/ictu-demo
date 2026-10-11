<?php

namespace Tests\Unit;

use App\Services\OperationalAdvisoryService;
use App\Services\OperationalCalendarService;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class OperationalAdvisoryServiceTest extends TestCase
{
    private const CLOSED = [
        'is_open' => false,
        'allows_pickup' => false,
        'allows_return' => false,
        'source' => 'EXCEPTION',
        'open_time' => null,
        'close_time' => null,
    ];

    private const OPEN = [
        'is_open' => true,
        'allows_pickup' => true,
        'allows_return' => true,
        'source' => 'EXCEPTION',
    ];

    /** A Monday–Friday 8–5 schedule from $today, with date-specific overrides. */
    private function snapshot(array $overrides, string $today = '2026-10-12'): array
    {
        $start = CarbonImmutable::parse($today, 'Asia/Manila');
        $profiles = collect();
        for ($n = 0; $n <= 28; $n++) {
            $day = $start->addDays($n);
            $open = $day->dayOfWeekIso <= 5;
            $profiles->put($day->toDateString(), [
                'is_open' => $open,
                'allows_pickup' => $open,
                'allows_return' => $open,
                'source' => 'WEEKLY',
                'reason' => null,
                'open_time' => '08:00:00',
                'close_time' => '17:00:00',
            ]);
        }
        foreach ($overrides as $date => $values) {
            if ($profiles->has($date)) {
                $profiles->put($date, array_merge($profiles->get($date), $values));
            }
        }
        $calendar = Mockery::mock(OperationalCalendarService::class)->makePartial();
        $calendar->shouldReceive('profilesForRange')->once()->andReturn($profiles);

        return (new OperationalAdvisoryService($calendar))->snapshot($start);
    }

    public function test_regular_open_day_shows_hours_and_no_routine_weekend_reminder(): void
    {
        $result = $this->snapshot([]);

        $this->assertSame('normal', $result['tone']);
        $this->assertSame('Open Today', $result['badge']);
        $this->assertSame('On-site transactions are available today.', $result['message']);
        $this->assertSame('8:00 AM–5:00 PM', $result['hours']);
        // The coming weekend is routine: it is not announced.
        $this->assertNull($result['upcoming']);
        $this->assertNull($result['next_available']);
        $this->assertNull($result['reason']);
        $this->assertArrayNotHasKey('regular_closure', $result);
    }

    public function test_regular_closed_saturday_uses_the_concise_closed_wording(): void
    {
        $result = $this->snapshot([], '2026-10-17');

        $this->assertSame('closed', $result['tone']);
        $this->assertSame('Closed Today', $result['badge']);
        $this->assertSame('No on-site transactions today. Online requests remain available.', $result['message']);
        $this->assertSame('Mon, Oct 19', $result['next_available']);
        $this->assertNull($result['hours']);
        $this->assertNull($result['reason']);
        $this->assertNull($result['upcoming']);
    }

    public function test_regular_closed_sunday_points_to_the_next_available_day(): void
    {
        $result = $this->snapshot([], '2026-10-18');

        $this->assertSame('Closed Today', $result['badge']);
        $this->assertSame('Mon, Oct 19', $result['next_available']);
    }

    public function test_weekly_schedule_decides_which_days_are_routine_not_the_weekday_name(): void
    {
        // An office configured to close on Wednesdays and open on Saturdays.
        $overrides = [];
        $start = CarbonImmutable::parse('2026-10-12', 'Asia/Manila');
        for ($n = 0; $n <= 28; $n++) {
            $day = $start->addDays($n);
            if ($day->dayOfWeekIso === 3) {
                $overrides[$day->toDateString()] = ['is_open' => false, 'allows_pickup' => false, 'allows_return' => false];
            } elseif ($day->dayOfWeekIso === 6) {
                $overrides[$day->toDateString()] = ['is_open' => true, 'allows_pickup' => true, 'allows_return' => true];
            }
        }

        $wednesday = $this->snapshot($overrides, '2026-10-14');
        $this->assertSame('Closed Today', $wednesday['badge']);
        $this->assertSame('Thu, Oct 15', $wednesday['next_available']);

        $saturday = $this->snapshot($overrides, '2026-10-17');
        $this->assertSame('Open Today', $saturday['badge']);
        $this->assertNull($saturday['upcoming']);
    }

    public function test_unexpected_weekday_closure_today_shows_reason_and_next_available(): void
    {
        $result = $this->snapshot([
            '2026-10-12' => self::CLOSED + ['reason' => 'Emergency closure'],
        ]);

        $this->assertSame('closed', $result['tone']);
        $this->assertSame('Temporary Closure', $result['badge']);
        $this->assertSame('On-site transactions are suspended today. Online requests remain available.', $result['message']);
        $this->assertSame('Emergency closure', $result['reason']);
        $this->assertSame('Tue, Oct 13', $result['next_available']);
    }

    public function test_consecutive_closures_from_today_give_the_last_date_and_the_true_next_available_day(): void
    {
        $closed = self::CLOSED + ['reason' => 'Inventory audit'];
        $result = $this->snapshot([
            '2026-10-12' => $closed,
            '2026-10-13' => $closed,
            '2026-10-14' => $closed,
        ]);

        $this->assertSame('On-site transactions are suspended through Wed, Oct 14. Online requests remain available.', $result['message']);
        $this->assertSame('Thu, Oct 15', $result['next_available']);
        // The rest of the same closure is not repeated as an upcoming notice.
        $this->assertNull($result['upcoming']);
    }

    public function test_closure_running_into_the_weekend_skips_to_the_next_open_weekday(): void
    {
        $result = $this->snapshot([
            '2026-10-15' => self::CLOSED,
            '2026-10-16' => self::CLOSED,
        ], '2026-10-15');

        $this->assertSame('On-site transactions are suspended through Fri, Oct 16. Online requests remain available.', $result['message']);
        $this->assertSame('Mon, Oct 19', $result['next_available']);
    }

    public function test_upcoming_weekday_closure_is_announced_with_its_reason(): void
    {
        $result = $this->snapshot([
            '2026-10-14' => self::CLOSED + ['reason' => 'Special maintenance'],
        ]);

        $this->assertSame('Open Today', $result['badge']);
        $this->assertSame('Temporary closure · Wed, Oct 14 · Special maintenance', $result['upcoming']);
    }

    public function test_upcoming_consecutive_closures_are_one_notice_with_a_date_range(): void
    {
        $closed = self::CLOSED + ['reason' => 'Foundation Week'];
        $result = $this->snapshot([
            '2026-10-14' => $closed,
            '2026-10-15' => $closed,
            '2026-10-16' => $closed,
        ]);

        $this->assertSame('Temporary closure · Wed, Oct 14 – Fri, Oct 16 · Foundation Week', $result['upcoming']);
    }

    public function test_upcoming_special_opening_on_a_normally_closed_day_shows_its_hours_and_reason(): void
    {
        $result = $this->snapshot([
            '2026-10-17' => self::OPEN + ['open_time' => '09:00:00', 'close_time' => '12:00:00', 'reason' => 'Saturday service'],
        ]);

        $this->assertSame('Special opening · Sat, Oct 17 · 9:00 AM–12:00 PM · Saturday service', $result['upcoming']);
    }

    public function test_special_dates_take_priority_over_the_routine_weekend(): void
    {
        // Friday: the weekend starts tomorrow, but the notice is the special closure after it.
        $result = $this->snapshot([
            '2026-10-20' => self::CLOSED + ['reason' => 'Holiday'],
        ], '2026-10-16');

        $this->assertSame('Open Today', $result['badge']);
        $this->assertSame('Temporary closure · Tue, Oct 20 · Holiday', $result['upcoming']);
    }

    public function test_special_date_that_only_changes_hours_is_a_special_schedule_not_an_opening(): void
    {
        $result = $this->snapshot([
            '2026-10-14' => self::OPEN + ['open_time' => '13:00:00', 'close_time' => '15:00:00'],
        ]);

        $this->assertSame('Special schedule · Wed, Oct 14 · 1:00 PM–3:00 PM', $result['upcoming']);
    }

    public function test_closing_a_day_that_is_closed_anyway_is_not_announced(): void
    {
        $upcoming = $this->snapshot(['2026-10-18' => self::CLOSED + ['reason' => 'Holiday']]);
        $this->assertNull($upcoming['upcoming']);

        // On the day itself it stays a routine closed day, with the calendar's reason.
        $today = $this->snapshot(['2026-10-18' => self::CLOSED + ['reason' => 'Holiday']], '2026-10-18');
        $this->assertSame('Closed Today', $today['badge']);
        $this->assertSame('Holiday', $today['reason']);
        $this->assertSame('Mon, Oct 19', $today['next_available']);
    }

    public function test_special_saturday_opening_today_shows_its_own_hours(): void
    {
        $result = $this->snapshot([
            '2026-10-17' => array_merge(self::OPEN, [
                'allows_return' => false,
                'open_time' => '09:00:00',
                'close_time' => '12:00:00',
                'reason' => 'Scheduled Saturday service',
            ]),
        ], '2026-10-17');

        $this->assertSame('special', $result['tone']);
        $this->assertSame('Special Opening', $result['badge']);
        $this->assertSame('Special opening today. On-site transactions are available.', $result['message']);
        $this->assertSame('9:00 AM–12:00 PM', $result['hours']);
        $this->assertSame('Scheduled Saturday service', $result['reason']);
        $this->assertNull($result['next_available']);
    }

    public function test_special_opening_without_pickup_or_return_is_not_shown_as_open(): void
    {
        $result = $this->snapshot([
            '2026-10-17' => array_merge(self::OPEN, ['allows_pickup' => false, 'allows_return' => false]),
        ], '2026-10-17');

        $this->assertSame('closed', $result['tone']);
        $this->assertSame('Closed Today', $result['badge']);
        $this->assertNull($result['hours']);
    }

    public function test_missing_operating_hours_do_not_show_a_false_open_status(): void
    {
        $result = $this->snapshot([
            '2026-10-12' => ['open_time' => null, 'close_time' => null],
        ]);

        $this->assertSame('closed', $result['tone']);
        $this->assertSame('Closed Today', $result['badge']);
        $this->assertSame('Tue, Oct 13', $result['next_available']);
    }

    public function test_no_available_day_in_the_lookahead_leaves_next_available_empty(): void
    {
        $overrides = [];
        $start = CarbonImmutable::parse('2026-10-12', 'Asia/Manila');
        for ($n = 0; $n <= 28; $n++) {
            $overrides[$start->addDays($n)->toDateString()] = self::CLOSED;
        }

        $result = $this->snapshot($overrides);

        $this->assertSame('closed', $result['tone']);
        $this->assertNull($result['next_available']);
    }
}
