<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AcademicPeriod;
use App\Models\AuditEvent;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AcademicPeriodResolver;
use App\Services\AcademicYearRequestNumbers;
use App\Services\PolicyService;
use App\Services\ReportingPeriodService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutomaticAcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-16 10:00', 'Asia/Manila'));
    }

    private function period(array $attributes = []): AcademicPeriod
    {
        return AcademicPeriod::create($attributes + [
            'academic_year' => '2026-2027', 'term_code' => 'FIRST_SEMESTER',
            'term_name' => '1st Semester', 'start_date' => '2026-08-01',
            'end_date' => '2026-12-20', 'status' => 'UPCOMING',
        ]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
    }

    private function payload(array $attributes = []): array
    {
        return $attributes + ['academic_year' => '2026-2027', 'term_code' => 'FIRST_SEMESTER',
            'start_date' => '2026-08-01', 'end_date' => '2026-12-20'];
    }

    public function test_boundaries_use_manila_calendar_days_including_utc_timestamps(): void
    {
        $period = $this->period(['status' => 'COMPLETED']);
        $calendar = app(AcademicPeriodResolver::class);
        foreach ([
            '2026-07-31T15:59:59Z' => 'UPCOMING',
            '2026-07-31T16:00:00Z' => 'ACTIVE',
            '2026-12-20T15:59:59Z' => 'ACTIVE',
            '2026-12-20T16:00:00Z' => 'COMPLETED',
        ] as $timestamp => $status) {
            $date = CarbonImmutable::parse($timestamp);
            $this->assertSame($status, $calendar->statusFor($period, $date));
            $this->assertSame($status, $calendar->statusFor($period, $timestamp));
            $this->assertSame($status === 'ACTIVE' ? $period->id : null, $calendar->current($date)?->id);
        }
        $this->assertSame('COMPLETED', $period->fresh()->status);
    }

    public function test_rollover_and_offline_catchup_do_not_need_a_job_or_write_on_read(): void
    {
        $old = $this->period(['status' => 'ACTIVE']);
        $next = $this->period(['term_code' => 'SECOND_SEMESTER', 'term_name' => '2nd Semester',
            'start_date' => '2027-01-04', 'end_date' => '2027-05-31']);
        $before = DB::table('academic_periods')->orderBy('id')->get()->toJson();
        $calendar = app(AcademicPeriodResolver::class);
        $this->assertSame($old->id, $calendar->current()?->id);
        $this->assertNull($calendar->current('2026-12-21'));
        $this->assertSame($next->id, $calendar->current('2027-01-04')?->id);
        $this->travelTo(CarbonImmutable::parse('2027-03-01', 'Asia/Manila'));
        $this->assertSame($next->id, app(PolicyService::class)->activePeriodFor()?->id);
        $this->assertSame($old->id, app(PolicyService::class)->activePeriodFor('2026-09-01')?->id);
        $this->assertSame($before, DB::table('academic_periods')->orderBy('id')->get()->toJson());
    }

    public function test_save_configures_current_future_and_summer_without_manual_activation(): void
    {
        $this->actingAs($this->adminUser());
        $url = route('policies.academic-periods.store');
        $this->post($url, $this->payload())->assertSessionHasNoErrors();
        $current = AcademicPeriod::firstOrFail();
        $this->assertSame('ACTIVE', $current->status);
        $this->post($url, $this->payload(['term_code' => 'SECOND_SEMESTER',
            'start_date' => '2027-01-04', 'end_date' => '2027-05-31']))->assertSessionHasNoErrors();
        $this->post($url, $this->payload(['term_code' => 'SUMMER_MIDYEAR',
            'start_date' => '2027-06-01', 'end_date' => '2027-07-15']))->assertSessionHasNoErrors();
        $this->assertSame($current->id, app(AcademicPeriodResolver::class)->current()?->id);
        $this->assertSame('ACTIVE', $current->fresh()->status);
        $this->assertDatabaseCount('academic_periods', 3);
        $this->assertSame(3, AuditEvent::where('action_code', 'ACADEMIC_PERIOD_CONFIGURED')->count());
        $this->assertFalse(app(AcademicYearRequestNumbers::class)->enabled());
    }

    public function test_saving_the_same_term_updates_its_dates_without_changing_its_identity(): void
    {
        $period = $this->period();
        $this->actingAs($this->adminUser())->post(route('policies.academic-periods.store'),
            $this->payload(['end_date' => '2026-12-21']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('academic_periods', 1);
        $this->assertSame($period->id, AcademicPeriod::firstOrFail()->id);
        $this->assertSame('2026-12-21', $period->fresh()->end_date->toDateString());
        $this->put(route('policies.academic-periods.update', $period), $this->payload(['end_date' => '2026-12-22']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['action_code' => 'ACADEMIC_PERIOD_UPDATED', 'record_id' => $period->id]);
    }

    public static function invalidDates(): array
    {
        return [
            'nonconsecutive year' => [['academic_year' => '2026-2028'], 'academic_year'],
            'malformed year' => [['academic_year' => '2026/2027'], 'academic_year'],
            'year array' => [['academic_year' => ['2026-2027']], 'academic_year'],
            'reversed dates' => [['end_date' => '2026-07-31'], 'end_date'],
            'invalid calendar date' => [['start_date' => '2026-02-30'], 'start_date'],
            'start array' => [['start_date' => ['2026-08-01']], 'start_date'],
            'end array' => [['end_date' => ['2026-12-20']], 'end_date'],
            'invalid term' => [['term_code' => 'FOURTH_SEMESTER'], 'term_code'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_calendar_input_is_rejected_without_writes(array $changes, string $field): void
    {
        $this->actingAs($this->adminUser())->post(route('policies.academic-periods.store'), $this->payload($changes))
            ->assertSessionHasErrors($field);
        $this->assertDatabaseCount('academic_periods', 0);
        $this->assertSame(0, AuditEvent::where('record_type', AcademicPeriod::class)->count());
    }

    public function test_inclusive_overlap_is_rejected_within_and_across_academic_years(): void
    {
        $period = $this->period();
        $this->actingAs($this->adminUser());
        foreach (['2026-2027', '2027-2028'] as $year) {
            $this->post(route('policies.academic-periods.store'), $this->payload([
                'academic_year' => $year, 'term_code' => 'SECOND_SEMESTER',
                'start_date' => '2026-12-20', 'end_date' => '2027-05-31',
            ]))->assertSessionHasErrors('start_date');
        }
        $this->assertDatabaseCount('academic_periods', 1);
        $this->assertSame('UPCOMING', $period->fresh()->status);
    }

    public function test_academic_year_spans_cannot_interleave_even_when_individual_terms_do_not_overlap(): void
    {
        $this->period();
        $this->period(['academic_year' => '2027-2028', 'start_date' => '2027-01-01', 'end_date' => '2027-05-31']);
        $this->actingAs($this->adminUser())->post(route('policies.academic-periods.store'), $this->payload([
            'term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-06-01', 'end_date' => '2027-07-15',
        ]))->assertSessionHasErrors('academic_year');
        $this->assertDatabaseCount('academic_periods', 2);
    }

    public function test_update_cannot_create_a_duplicate_year_and_term(): void
    {
        $this->period();
        $next = $this->period(['term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-01-04', 'end_date' => '2027-05-31']);
        $this->actingAs($this->adminUser())->put(route('policies.academic-periods.update', $next), $this->payload())
            ->assertSessionHasErrors('term_code');
        $this->assertSame('SECOND_SEMESTER', $next->fresh()->term_code);
    }

    public function test_old_activation_submissions_cannot_override_dates_or_change_other_rows(): void
    {
        $old = $this->period(['status' => 'ACTIVE']);
        $future = $this->period(['term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-01-04', 'end_date' => '2027-05-31']);
        $before = DB::table('academic_periods')->orderBy('id')->get()->toJson();
        $this->actingAs($this->adminUser())->put(route('policies.academic-periods.update', $future), ['activate' => 1])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame($before, DB::table('academic_periods')->orderBy('id')->get()->toJson());
        $this->assertSame($old->id, app(AcademicPeriodResolver::class)->current()?->id);
        $this->assertSame(0, AuditEvent::where('action_code', 'ACADEMIC_PERIOD_ACTIVATED')->count());
    }

    public function test_legacy_overlapping_rows_are_reported_without_arbitrarily_selecting_a_semester(): void
    {
        $this->period(['status' => 'ACTIVE']);
        $this->period(['academic_year' => '2025-2026', 'status' => 'ACTIVE']);
        $this->assertNull(app(AcademicPeriodResolver::class)->current());
        $this->assertNull(app(AcademicPeriodResolver::class)->academicYear());
        $this->actingAs($this->adminUser())->get(route('policies.index', ['section' => 'academic-periods']))
            ->assertOk()->assertViewHas('activePeriod', null)->assertSeeText('Academic calendar dates overlap');
    }

    public function test_current_filter_uses_dates_instead_of_stale_stored_flags(): void
    {
        $current = $this->period();
        $future = $this->period(['term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-01-04',
            'end_date' => '2027-05-31', 'status' => 'ACTIVE']);
        $before = DB::table('academic_periods')->orderBy('id')->get()->toJson();
        $response = $this->actingAs($this->adminUser())->get(route('policies.index', [
            'section' => 'academic-periods', 'period_status' => 'ACTIVE', 'period_q' => 'Current',
        ]))->assertOk()->assertSeeText('Current · Auto')->assertDontSee('name="activate"', false);
        $this->assertSame([$current->id], $response->viewData('academicPeriods')->pluck('id')->all());
        $this->assertSame($current->id, $response->viewData('activePeriod')->id);
        $this->assertSame('ACTIVE', $future->fresh()->status);
        $this->assertSame($before, DB::table('academic_periods')->orderBy('id')->get()->toJson());
    }

    public function test_semester_break_has_no_current_term_but_retains_unambiguous_year_scope(): void
    {
        $this->period(['status' => 'ACTIVE']);
        $this->period(['term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-01-04', 'end_date' => '2027-05-31']);
        $this->travelTo(CarbonImmutable::parse('2026-12-28', 'Asia/Manila'));
        $calendar = app(AcademicPeriodResolver::class);
        $this->assertNull($calendar->current());
        $this->assertSame('2026-2027', $calendar->academicYear());
        $periods = AcademicPeriod::all();
        $service = app(ReportingPeriodService::class);
        foreach (['resolve', 'resolveForReport'] as $method) {
            [$from, $to, $selected, $selection] = $service->$method(Request::create('/', 'GET', ['academic_period' => 'academic_year']), $periods, null);
            $this->assertSame(['2026-08-01', '2027-05-31', null, 'academic_year'], [$from->toDateString(), $to->toDateString(), $selected, $selection]);
        }
        $this->actingAs($this->adminUser())->get(route('policies.index', ['section' => 'academic-periods']))
            ->assertOk()->assertSeeText('No current semester');
        $analytics = $this->get(route('analytics.index', ['academic_period' => 'academic_year']))->assertOk();
        $this->assertFalse($analytics->viewData('academicPeriodMissing'));
        $this->get(route('analytics.index', ['academic_period' => 'semester']))->assertOk()->assertViewHas('academicPeriodMissing', true);
    }

    public function test_reports_and_analytics_share_the_effective_current_semester_and_legacy_links(): void
    {
        $current = $this->period();
        $future = $this->period(['term_code' => 'SECOND_SEMESTER', 'start_date' => '2027-01-04',
            'end_date' => '2027-05-31', 'status' => 'ACTIVE']);
        $this->actingAs($this->adminUser());
        foreach (['reports.index' => ['report' => 'all-transactions'], 'analytics.index' => []] as $route => $extra) {
            $response = $this->get(route($route, $extra + ['academic_period' => 'semester']))->assertOk();
            $this->assertSame($current->id, $response->viewData('activeAcademicPeriod')->id);
            $this->assertSame('2026-08-01', $response->viewData('from')->toDateString());
            $this->assertSame('2026-12-20', $response->viewData('to')->toDateString());
        }
        [$from, $to, $selected] = app(ReportingPeriodService::class)->resolve(
            Request::create('/', 'GET', ['academic_period' => (string) $future->id]), AcademicPeriod::all(), $current
        );
        $this->assertSame([$future->id, '2027-01-04', '2027-05-31'], [$selected->id, $from->toDateString(), $to->toDateString()]);
    }

    public function test_configuration_uses_one_flat_submenu_with_the_original_five_destinations(): void
    {
        $this->period();
        $this->actingAs($this->adminUser());
        $features = [
            'transaction-schedule' => 'policies.index', 'special-dates' => 'policies.index',
            'academic-periods' => 'policies.index', 'late-return-fee' => 'administration.settings.index',
            'sanction-rules' => 'policies.index',
        ];
        foreach ($features as $section => $route) {
            $response = $this->get(route($route, ['section' => $section]))->assertOk();
            $html = $response->getContent();
            $dom = new DOMDocument;
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $links = $xpath->query('//*[@id="sidebar-section-configuration"]/a');
            $this->assertCount(5, $links);
            $this->assertCount(1, $xpath->query('//*[@id="sidebar-section-configuration"]/a[@aria-current="page"]'));
            $this->assertSame(route($route, ['section' => $section]), $xpath->query('//*[@id="sidebar-section-configuration"]/a[@aria-current="page"]')->item(0)->getAttribute('href'));
            $this->assertCount(0, $xpath->query('//*[@id="sidebar-section-configuration"]//*[@data-sidebar-section-toggle]'));
            if ($capture = getenv('SPMU_ACADEMIC_CAPTURE_DIR')) {
                file_put_contents($capture.'/'.$section.'.html', $html);
            }
        }
        $root = $this->get(route('policies.index'))->assertOk();
        $root->assertSee('data-weekly-schedule-form', false);
        $rootDom = new DOMDocument;
        @$rootDom->loadHTML($root->getContent());
        $this->assertCount(0, (new DOMXPath($rootDom))->query('//*[contains(concat(" ", normalize-space(@class), " "), " operational-config-hub ")]'));
        $this->assertDatabaseCount('academic_periods', 1);
    }

    public function test_original_academic_fields_and_native_dates_remain_and_activation_column_is_removed(): void
    {
        $html = $this->actingAs($this->adminUser())->get(route('policies.index', ['section' => 'academic-periods']))->assertOk()->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $form = '//form[@action="'.route('policies.academic-periods.store').'"]';
        $this->assertCount(1, $xpath->query($form.'//input[@name="academic_year"]'));
        $this->assertCount(1, $xpath->query($form.'//select[@name="term_code"]'));
        $this->assertCount(2, $xpath->query($form.'//input[@type="date"]'));
        $this->assertCount(4, $xpath->query('//*[@class="table-wrap periods-table"]//thead//th'));
        $this->assertCount(0, $xpath->query('//input[@name="activate"]'));
        $this->assertStringContainsString('name="period_q"', $html);
        $this->assertStringContainsString('name="period_status"', $html);
        $this->assertStringContainsString('name="period_per_page"', $html);
    }

    public function test_borrower_and_officer_cannot_access_or_modify_configuration(): void
    {
        $period = $this->period();
        foreach ([AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer] as $role) {
            $user = User::factory()->create(['access_classification' => $role]);
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('sidebar-section-configuration', false);
            $this->get(route('policies.index'))->assertForbidden();
            $this->post(route('policies.academic-periods.store'), $this->payload())->assertForbidden();
            $this->put(route('policies.academic-periods.update', $period), ['activate' => 1])->assertForbidden();
        }
        $this->assertSame('UPCOMING', $period->fresh()->status);
        $this->assertNull(SystemSetting::value(AcademicYearRequestNumbers::SETTING));
    }
}
