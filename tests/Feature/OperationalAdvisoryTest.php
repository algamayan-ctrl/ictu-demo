<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\OperationalDateException;
use App\Models\OperationalWeeklySchedule;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The advisory against real Operational Calendar records (weekly schedule
 * and Special Dates) in the isolated test database.
 */
class OperationalAdvisoryTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::create(2026, 9, 14, 9, 0)); // Monday

        foreach (range(1, 7) as $weekday) {
            $open = $weekday <= 5;
            OperationalWeeklySchedule::query()->updateOrCreate(['weekday' => $weekday], [
                'is_open' => $open, 'accepts_requests' => $open, 'allows_pickup' => $open, 'allows_return' => $open,
                'open_time' => $open ? '08:00' : null, 'close_time' => $open ? '17:00' : null,
            ]);
        }

        $this->borrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::query()->where('unit_code', 'CHS')->value('id'),
        ]);
    }

    private function asBorrower(): static
    {
        return $this->withSession(['active_workspace' => 'BORROWER'])->actingAs($this->borrower);
    }

    private function advisory(): array
    {
        return $this->asBorrower()->getJson(route('operational-advisory.status'))->assertOk()->json();
    }

    private function special(string $date, string $status, array $extra = []): void
    {
        $open = $status === 'OPEN';
        OperationalDateException::query()->updateOrCreate(['exception_date' => $date], $extra + [
            'status' => $status, 'accepts_requests' => $open, 'allows_pickup' => $open, 'allows_return' => $open,
            'open_time' => $open ? '09:00' : null, 'close_time' => $open ? '12:00' : null,
            // The Special Dates table requires a reason, so one is always on record.
            'reason' => 'Special date',
        ]);
    }

    public function test_status_endpoint_requires_a_signed_in_user_and_is_never_cached(): void
    {
        $this->getJson(route('operational-advisory.status'))->assertUnauthorized();

        $response = $this->asBorrower()->getJson(route('operational-advisory.status'))->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(
            ['date', 'tone', 'badge', 'message', 'reason', 'hours', 'upcoming', 'next_available'],
            array_keys($response->json())
        );
    }

    public function test_regular_day_then_calendar_changes_are_reflected_on_the_next_refresh(): void
    {
        $regular = $this->advisory();
        $this->assertSame('2026-09-14', $regular['date']);
        $this->assertSame('On-site Open', $regular['badge']);
        $this->assertSame('8:00 AM–5:00 PM', $regular['hours']);
        $this->assertNull($regular['upcoming']);

        // SPMU adds a closure for Wednesday: the very next poll announces it.
        $this->special('2026-09-16', 'CLOSED', ['reason' => 'Water interruption']);
        $this->assertSame('Temporary closure · Wed, Sep 16 · Water interruption', $this->advisory()['upcoming']);

        // Today itself is then closed.
        $this->special('2026-09-14', 'CLOSED', ['reason' => 'Emergency closure']);
        $closed = $this->advisory();
        $this->assertSame('On-site Closed', $closed['badge']);
        $this->assertSame('Emergency closure', $closed['reason']);
        $this->assertSame('Tue, Sep 15 at 8:00 AM', $closed['next_available']);
        $this->assertNull($closed['hours']);

        // Removing the Special Dates restores the regular advisory.
        OperationalDateException::query()->delete();
        $this->assertSame('On-site Open', $this->advisory()['badge']);
        $this->assertNull($this->advisory()['upcoming']);

        // A change to the weekly Transaction Schedule is picked up as well.
        OperationalWeeklySchedule::query()->where('weekday', 1)->update(['open_time' => '10:00', 'close_time' => '15:00']);
        $this->assertSame('10:00 AM–3:00 PM', $this->advisory()['hours']);
    }

    public function test_weekend_days_follow_the_configured_schedule_and_special_openings_override_them(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 19, 9, 0)); // Saturday

        $saturday = $this->advisory();
        $this->assertSame('On-site Closed', $saturday['badge']);
        $this->assertSame('No on-site transactions are scheduled today. Next on-site opening: Mon, Sep 21 at 8:00 AM. Online borrowing requests remain available.', $saturday['message']);
        $this->assertSame('Mon, Sep 21 at 8:00 AM', $saturday['next_available']);
        $this->assertNull($saturday['upcoming']);

        // A special opening today makes on-site transactions available.
        $this->special('2026-09-19', 'OPEN', ['reason' => 'Enrollment support']);
        $opened = $this->advisory();
        $this->assertSame('special', $opened['tone']);
        $this->assertSame('On-site Open', $opened['badge']);
        $this->assertSame('9:00 AM–12:00 PM', $opened['hours']);
        $this->assertSame('Enrollment support', $opened['reason']);
        $this->assertNull($opened['next_available']);
    }

    public function test_upcoming_special_opening_and_consecutive_closures_use_real_calendar_dates(): void
    {
        $this->special('2026-09-19', 'OPEN', ['reason' => 'Saturday service']);
        $this->assertSame('Special opening · Sat, Sep 19 · 9:00 AM–12:00 PM · Saturday service', $this->advisory()['upcoming']);

        // An earlier multi-day closure takes over as the nearer notice.
        foreach (['2026-09-16', '2026-09-17', '2026-09-18'] as $date) {
            $this->special($date, 'CLOSED', ['reason' => 'Foundation Week']);
        }
        $this->assertSame('Temporary closure · Wed, Sep 16 – Fri, Sep 18 · Foundation Week', $this->advisory()['upcoming']);
    }

    public function test_advisory_reads_the_calendar_without_writing_and_with_a_fixed_number_of_queries(): void
    {
        $this->special('2026-09-16', 'CLOSED');
        $this->asBorrower();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'operational_')) {
                $queries[] = $query->sql;
            }
        });

        app(\App\Services\OperationalAdvisoryService::class)->snapshot();

        // One read of the weekly schedule and one of the Special Dates in range.
        $this->assertCount(2, $queries);
        foreach ($queries as $sql) {
            $this->assertStringStartsWith('select', strtolower($sql));
        }
    }

    public function test_dashboard_shows_advisory_but_create_request_does_not_duplicate_it(): void
    {
        $this->travelTo(Carbon::create(2026, 9, 19, 9, 0)); // Saturday

        $this->asBorrower()->get(route('dashboard'))->assertOk()
            ->assertSee('SPMU Transaction Advisory')
            ->assertSee('On-site Closed')
            ->assertSee('No on-site transactions are scheduled today. Next on-site opening: Mon, Sep 21 at 8:00 AM. Online borrowing requests remain available.')
            ->assertSee('Next available: Mon, Sep 21')
            ->assertSee('View Calendar →')
            ->assertSee('href="'.route('calendar.index').'"', false)
            ->assertSee('data-refresh-url="'.route('operational-advisory.status').'"', false)
            ->assertDontSee('Next closed');

        $this->asBorrower()->get(route('requests.create'))->assertOk()
            ->assertDontSee('SPMU Transaction Advisory')
            ->assertDontSee('data-spmu-operational-advisory')
            ->assertSee('id="schedule_date"', false)
            ->assertSee('id="spmu-schedule-live-error"', false)
            ->assertSee('id="spmu-return-live-error"', false);
    }

    public function test_a_closed_day_never_blocks_opening_the_request_form_or_checking_dates(): void
    {
        $this->special('2026-09-14', 'CLOSED', ['reason' => 'Emergency closure']);

        // Today is closed, yet the borrower can still start an online request.
        $this->asBorrower()->get(route('requests.create'))->assertOk()
            ->assertDontSee('SPMU Transaction Advisory')
            ->assertSee('id="schedule_date"', false)
            ->assertSee('id="spmu-schedule-live-error"', false)
            ->assertSee('id="spmu-return-live-error"', false);

        // The form's inline check explains a closed date from the calendar, and
        // accepts a normally closed Saturday once a Special Date opens it.
        $this->special('2026-09-17', 'CLOSED', ['reason' => 'Water interruption']);
        $this->special('2026-09-19', 'OPEN');

        $this->asBorrower()->getJson(route('requests.operational-date-availability', [
            'schedule_date' => '2026-09-17', 'return_date' => '2026-09-19',
        ]))->assertOk()
            ->assertJsonPath('schedule_date.open', false)
            ->assertJsonPath('schedule_date.message', 'SPMU is closed on September 17, 2026 (Water interruption). Please choose a different date.')
            ->assertJsonPath('return_date.open', true);

        // A regular closed Sunday is explained without inventing a reason.
        $this->asBorrower()->getJson(route('requests.operational-date-availability', [
            'schedule_date' => '2026-09-18', 'return_date' => '2026-09-20',
        ]))->assertOk()
            ->assertJsonPath('schedule_date.open', true)
            ->assertJsonPath('return_date.open', false)
            ->assertJsonPath('return_date.message', 'SPMU is closed on September 20, 2026. Please choose a different date.');
    }
}
