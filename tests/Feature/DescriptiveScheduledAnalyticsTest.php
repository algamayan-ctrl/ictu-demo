<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RegistrationType;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\OrganizationalUnit;
use App\Models\RequestVersion;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\ReportingPeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DescriptiveScheduledAnalyticsTest extends TestCase
{
    public function test_predictive_runtime_and_templates_have_been_removed(): void
    {
        $this->assertFalse(class_exists(\App\Services\ForecastService::class));
        $this->assertFileDoesNotExist(app_path('Services/ForecastService.php'));
        $this->assertFileDoesNotExist(resource_path('views/analytics/partials/predictive.blade.php'));
        // Main's coverage API contains recorded releases and current stock only.
        $this->assertTrue(method_exists(\App\Services\AnalyticsService::class, 'stockCoverage'));
        $this->assertFalse(defined(AnalyticsService::class.'::STOCKOUT_RISK_HIGH_DAYS'));
    }

    use RefreshDatabase;

    private OrganizationalUnit $unit;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 4, 20, 9));
        $this->unit = OrganizationalUnit::query()->create([
            'unit_code' => 'ANALYTICS-QA', 'unit_name' => 'Analytics QA', 'unit_type' => 'OFFICE', 'active' => true,
        ]);
        $this->head = User::factory()->create([
            'access_classification' => AccessClassification::SpmuHead, 'organizational_unit_id' => $this->unit->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function booking(string $date = '2026-05-10', RequestStatus $status = RequestStatus::UnderSpmu, array $versionFields = []): BorrowingRequest
    {
        $borrower = User::factory()->create(['organizational_unit_id' => $this->unit->id]);
        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-AN-'.fake()->unique()->numberBetween(1000, 9999999),
            'borrower_user_id' => $borrower->id, 'accountable_unit_id' => $this->unit->id,
            'current_version_no' => 1, 'status' => $status,
        ]);
        RequestVersion::query()->create(array_merge([
            'request_id' => $request->id, 'version_no' => 1, 'purpose_event' => 'Analytics fixture',
            'location' => 'Campus', 'division_code' => 'ACADEMIC', 'office_unit' => 'College of Computer Studies',
            'affiliation_type' => RegistrationType::Employee, 'schedule_date' => $date,
            'return_date' => Carbon::parse($date)->addDays(2)->toDateString(),
            'needed_from' => Carbon::parse($date)->startOfDay(), 'return_due_at' => Carbon::parse($date)->addDays(2)->endOfDay(),
            'submitted_at' => now(), 'created_by_user_id' => $borrower->id,
        ], $versionFields));

        return $request;
    }

    private function scheduled(?string $division = null, ?string $unit = null, ?int $borrower = null): array
    {
        return app(AnalyticsService::class)->scheduledDemand(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31')->endOfDay(), $division, $unit, $borrower);
    }

    public static function sections(): array
    {
        return array_map(fn ($section) => [$section], ['overview', 'demand', 'inventory', 'returns', 'scheduled', 'forecast', 'predictive']);
    }

    #[DataProvider('sections')]
    public function test_active_sections_never_resolve_forecasts_or_show_projections(string $section): void
    {
        $this->assertFalse(class_exists(\App\Services\ForecastService::class));
        $response = $this->actingAs($this->head)->get(route('analytics.index', ['section' => $section, 'academic_period' => 'month']));
        $response->assertOk()->assertSee('Scheduled Demand')->assertDontSee('Forecast &amp; Planning', false)
            ->assertDontSee('Forecast Methodology')->assertDontSee('Forecast Readiness')->assertDontSee('Forecasted Demand')
            ->assertDontSee('days coverage');
    }

    public static function unsupportedDetails(): array
    {
        return [
            ['forecast', 'demand'], ['predictive', ''], ['card', 'forecast.outlook'], ['card', 'forecast.equipment'],
            ['card', 'forecast.busy'], ['card', 'forecast.methodology'], ['card', 'forecast.coverage'],
        ];
    }

    #[DataProvider('unsupportedDetails')]
    public function test_legacy_detail_and_report_links_cannot_execute_projections(string $detail, string $key): void
    {
        $this->assertFalse(class_exists(\App\Services\ForecastService::class));
        $params = ['section' => 'inventory', 'detail' => $detail, 'for' => $key, 'metric' => 'demand', 'academic_period' => 'month'];
        $this->actingAs($this->head)->get(route('analytics.index', $params))->assertOk()
            ->assertDontSee('Forecast Methodology')->assertDontSee('days coverage')->assertDontSee('Forecasted Demand');
        $this->get(route('reports.analytics-records', $params))->assertNotFound();
    }

    public function test_scheduled_records_reconcile_pending_approved_external_and_unclassified_without_writes(): void
    {
        $this->booking();
        $this->booking('2026-05-11', RequestStatus::ApprovedReadyForRelease);
        $external = $this->booking('2026-05-12', RequestStatus::UnderSpmu, [
            'affiliation_type' => RegistrationType::External, 'external_company_name' => 'Reviewed Agency',
            'division_code' => null, 'office_unit' => null,
        ]);
        $this->booking('2026-05-13', RequestStatus::Submitted, ['affiliation_type' => null, 'division_code' => null, 'office_unit' => null]);
        $external->borrower->forceFill(['full_name' => 'Changed profile'])->save();
        $before = DB::table('request_versions')->orderBy('id')->get()->toJson();
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $scheduled = $this->scheduled();
        $this->assertSame(4, $scheduled['requests']);
        $this->assertSame(3, $scheduled['pending']);
        $this->assertSame(1, $scheduled['approved']);
        $this->assertSame(4, collect($scheduled['divisions']['groups'])->sum('count'));
        $this->assertSame(['ACADEMIC' => 2, 'EXTERNAL' => 1, 'UNCLASSIFIED' => 1], collect($scheduled['divisions']['groups'])->pluck('count', 'code')->all());
        $this->assertSame('Reviewed Agency', $scheduled['external']['companies'][0]['name']);
        $this->assertSame(2, collect($scheduled['units']['columns'])->flatMap(fn ($column) => $column['units'])->sum('count'));
        $this->assertSame($before, DB::table('request_versions')->orderBy('id')->get()->toJson());
        $this->assertSame([], $writes);
    }

    public static function excludedStatuses(): array
    {
        return array_map(fn ($status) => [$status], [RequestStatus::Draft, RequestStatus::Signed, RequestStatus::Rejected, RequestStatus::Cancelled, RequestStatus::Expired]);
    }

    #[DataProvider('excludedStatuses')]
    public function test_non_scheduled_states_are_excluded(RequestStatus $status): void
    {
        $this->booking('2026-05-10', $status);
        $this->assertSame(0, $this->scheduled()['requests']);
    }

    public function test_corrected_version_is_counted_once_and_old_schedule_never_leaks(): void
    {
        $request = $this->booking('2026-05-10');
        $current = $request->versions()->first()->replicate();
        $current->version_no = 2;
        $current->schedule_date = '2026-06-01';
        $current->needed_from = '2026-06-01 00:00:00';
        $current->save();
        $request->update(['current_version_no' => 2]);
        $this->assertSame(0, $this->scheduled()['requests']);
        $current->update(['schedule_date' => '2026-05-31']);
        $this->assertSame(1, $this->scheduled()['requests']);
    }

    public function test_date_boundaries_and_legacy_needed_from_are_inclusive(): void
    {
        foreach (['2026-04-30', '2026-05-01', '2026-05-31', '2026-06-01'] as $date) {
            $this->booking($date);
        }
        $this->booking('2026-05-31', RequestStatus::Submitted, ['schedule_date' => null, 'needed_from' => '2026-05-31 23:59:59']);
        $this->assertSame(3, $this->scheduled()['requests']);
        $this->assertSame(0, $this->scheduled('ADMINISTRATIVE')['requests']);
        $this->assertSame(3, $this->scheduled('ACADEMIC', 'College of Computer Studies')['requests']);
    }

    public function test_external_affiliation_never_becomes_an_employee_unit_even_with_inconsistent_legacy_fields(): void
    {
        $this->booking('2026-05-10', RequestStatus::UnderSpmu, [
            'affiliation_type' => RegistrationType::External, 'external_company_name' => 'Saved Agency',
        ]);
        $this->assertSame(1, $this->scheduled()['requests']);
        $this->assertSame('EXTERNAL', $this->scheduled()['divisions']['groups'][0]['code']);
        $this->assertSame(0, $this->scheduled('ACADEMIC', 'College of Computer Studies')['requests']);
        $this->assertCount(0, $this->scheduled()['units']['columns']);
        $this->assertCount(0, app(AnalyticsService::class)->scheduledUnitOptions(Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31')));
    }

    public function test_already_issued_or_closed_custody_is_not_outstanding_scheduled_demand(): void
    {
        foreach (['RELEASED', 'CLOSED', 'CANCELLED'] as $status) {
            $request = $this->booking('2026-05-10', RequestStatus::ApprovedReadyForRelease);
            CustodyTransaction::query()->create([
                'custody_no' => 'CT-AN-'.$request->id, 'request_id' => $request->id,
                'request_version_id' => $request->versions()->first()->id,
                'borrower_user_id' => $request->borrower_user_id, 'status' => $status,
                'due_at' => '2026-05-12 23:59:59', 'released_at' => $status === 'RELEASED' ? now() : null,
            ]);
        }
        $this->assertSame(0, $this->scheduled()['requests']);
    }

    public function test_scheduled_picker_and_selected_borrower_follow_schedule_not_filing_date(): void
    {
        $request = $this->booking();
        $request->versions()->first()->update(['submitted_at' => '2026-03-15 09:00:00']);
        $this->booking('2026-06-01');
        $params = ['section' => 'scheduled', 'academic_period' => 'month', 'borrower' => $request->borrower_user_id];
        $this->actingAs($this->head)->get(route('analytics.index', $params))->assertOk()
            ->assertViewHas('selectedBorrower', $request->borrower_user_id)
            ->assertViewHas('scheduled', fn ($data) => $data['requests'] === 1)
            ->assertSee('01 May 2026')->assertSee('31 May 2026');
        $this->getJson(route('analytics.index', $params + ['borrower_options' => 1]))->assertOk()
            ->assertJsonPath('count', 1)->assertJsonPath('options.0.value', (string) $request->borrower_user_id);
        $this->get(route('analytics.index', array_merge($params, ['borrower' => 999999])))->assertOk()->assertViewHas('selectedBorrower', null);
    }

    public function test_calendar_window_uses_actual_next_month_length_without_forecasting(): void
    {
        $periods = app(ReportingPeriodService::class);
        foreach ([['2028-01-01', '2028-01-31', '2028-02-01', '2028-02-29'], ['2026-04-01', '2026-04-30', '2026-05-01', '2026-05-31'], ['2026-04-20', '2026-04-26', '2026-04-27', '2026-05-03']] as [$from, $to, $nextFrom, $nextTo]) {
            [$start, $end] = $periods->nextScheduledWindow(Carbon::parse($from), Carbon::parse($to));
            $this->assertSame($nextFrom, $start->toDateString());
            $this->assertSame($nextTo, $end->toDateString());
        }
    }

    public function test_malformed_array_filters_are_validation_errors_and_not_server_errors(): void
    {
        foreach (['section', 'group', 'unit', 'borrower', 'detail', 'for'] as $field) {
            $this->actingAs($this->head)->getJson(route('analytics.index', [$field => ['bad']]))->assertUnprocessable();
            $this->getJson(route('reports.analytics-records', array_merge(['detail' => 'requests'], [$field => ['bad']])))->assertUnprocessable();
        }
    }

    public function test_saved_external_company_is_escaped_in_the_actual_scheduled_view(): void
    {
        $this->booking('2026-05-10', RequestStatus::UnderSpmu, [
            'affiliation_type' => RegistrationType::External, 'external_company_name' => '<script>company()</script>',
            'division_code' => null, 'office_unit' => null,
        ]);
        $this->actingAs($this->head)->get(route('analytics.index', ['section' => 'scheduled', 'academic_period' => 'month']))
            ->assertOk()->assertSee('&lt;script&gt;company()&lt;/script&gt;', false)->assertDontSee('<script>company()</script>', false);
    }

    public function test_scheduled_access_preserves_head_only_authority(): void
    {
        foreach ([AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer] as $classification) {
            $user = User::factory()->create(['access_classification' => $classification, 'organizational_unit_id' => $this->unit->id]);
            $this->actingAs($user)->get(route('analytics.index', ['section' => 'scheduled']))->assertForbidden();
        }
    }
}
