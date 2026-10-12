<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Report Form Configuration and the Official Summary of Requests for
 * Equipment (CSPC-F-SPMU-22) are deferred to a future enhancement.
 *
 * The feature is no longer reachable anywhere in the application, while its
 * table, its migrations and every stored template revision stay in place so
 * it can be restored later without data loss.
 */
class ReportFormDecommissionTest extends TestCase
{
    use RefreshDatabase;

    private const RETIRED_ROUTES = [
        'reports.official.equipment',
        'reports.official.equipment.export',
        'policies.report-forms.index',
        'policies.report-forms.store',
        'policies.report-forms.activate',
        'policies.report-forms.discard',
        'policies.report-forms.duplicate',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_retired_routes_are_no_longer_registered(): void
    {
        foreach (self::RETIRED_ROUTES as $name) {
            $this->assertFalse(Route::has($name), "{$name} is still registered.");
        }

        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('report-forms', $route->uri());
            $this->assertStringNotContainsString('reports/official', $route->uri());
        }
    }

    public function test_the_retired_urls_answer_not_found_even_for_the_spmu_head(): void
    {
        $head = $this->as(AccessClassification::SpmuHead);

        $head()->get('/administration/policies/report-forms')->assertNotFound();
        $head()->post('/administration/policies/report-forms', ['_action_token' => 'retired-store'])->assertNotFound();
        foreach (['activate', 'duplicate', 'discard'] as $action) {
            $head()->post('/administration/policies/report-forms/1/'.$action, ['_action_token' => 'retired-'.$action])->assertNotFound();
        }

        $head()->get('/reports/official/equipment?from=2026-09-01&to=2026-09-30')->assertNotFound();
        foreach (['print', 'pdf', 'docx', 'xlsx', 'csv'] as $format) {
            $head()->get('/reports/official/equipment/export/'.$format.'?from=2026-09-01&to=2026-09-30')->assertNotFound();
        }
    }

    public function test_no_page_links_to_the_retired_feature(): void
    {
        $head = $this->as(AccessClassification::SpmuHead);

        $head()->get(route('policies.index'))
            ->assertOk()
            ->assertDontSee('Report Form Configuration')
            ->assertDontSee('/administration/policies/report-forms', false);

        $head()->get(route('reports.index', ['report' => 'borrowing']))
            ->assertOk()
            ->assertDontSee('Open Official Form')
            ->assertDontSee('Official SPMU form')
            ->assertDontSee('/reports/official/equipment', false);
    }

    public function test_the_feature_code_is_gone_but_its_data_and_migrations_are_kept(): void
    {
        foreach ([
            'App\Http\Controllers\ReportFormConfigurationController',
            'App\Http\Controllers\OfficialEquipmentSummaryController',
            'App\Models\ReportFormTemplate',
            'App\Services\OfficialEquipmentSummaryService',
            'App\Services\OfficialEquipmentSummaryExporter',
            'App\Support\OfficialReportTitle',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} still exists.");
        }

        /* Schema and history are preserved for a future restoration. */
        $this->assertTrue(Schema::hasTable('report_form_templates'));
        $this->assertFileExists(database_path('migrations/2026_10_08_223148_create_report_form_templates_table.php'));
        $this->assertFileExists(database_path('migrations/2026_10_08_225704_normalize_summary_requests_report_form.php'));
        $this->assertSame(1, DB::table('report_form_templates')->where('report_key', 'SUMMARY_REQUESTS_EQUIPMENT')->count());
    }

    public function test_the_operational_reports_still_preview_print_and_export(): void
    {
        $head = $this->as(AccessClassification::SpmuHead);

        $head()->get(route('reports.index'))->assertOk();
        $head()->get(route('reports.index', ['report' => 'borrowing', 'academic_period' => 'month']))->assertOk();
        $head()->get(route('reports.print', ['type' => 'borrowing', 'academic_period' => 'month']))->assertOk();

        foreach (['csv', 'xlsx'] as $format) {
            $head()->get(route('reports.export', ['type' => 'borrowing', 'format' => $format, 'academic_period' => 'month']))->assertOk();
        }
    }

    private function as(AccessClassification $classification): \Closure
    {
        $user = User::query()->where('access_classification', $classification->value)->firstOrFail();

        return fn () => $this->actingAs($user)->withSession(['active_workspace' => 'SPMU']);
    }
}
