<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AcademicPeriod;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OperationalDateException;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecordTableControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_movement_search_reaches_beyond_the_old_cap_and_pagination_keeps_stable_order(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        $category = InventoryCategory::create(['category_code' => 'TABLE', 'category_name' => 'Tables']);
        $unit = UnitOfMeasure::create(['unit_code' => 'TABLE-PC', 'unit_name' => 'Piece', 'decimal_scale' => 0]);
        $item = InventoryItem::create(['category_id' => $category->id, 'unit_id' => $unit->id,
            'unique_description' => 'Table test chairs', 'total_quantity' => 110,
            'condition_code' => 'SERVICEABLE', 'borrowable' => true, 'active' => true]);
        $ids = [];
        for ($number = 1; $number <= 110; $number++) {
            $id = DB::table('inventory_transactions')->insertGetId([
                'transaction_type' => $number === 1 ? 'INVENTORY_INITIAL_STOCK' : 'INVENTORY_ADJUSTMENT',
                'actor_user_id' => $head->id, 'reason' => $number === 1 ? 'Historical search target' : 'Routine movement',
                'source_type' => InventoryItem::class, 'source_id' => $item->id,
                'correlation_id' => (string) Str::uuid(), 'occurred_at' => '2026-10-28 08:00:00',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('inventory_transaction_lines')->insert(['inventory_transaction_id' => $id,
                'inventory_item_id' => $item->id, 'from_state' => 'OPENING_BALANCE', 'to_state' => 'AVAILABLE',
                'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $ids[] = $id;
        }
        $url = route('inventory.show', ['inventory' => $item, 'tab' => 'stock-card']);
        $this->actingAs($head);
        $first = $this->get($url)->assertOk()->viewData('movementPage');
        $this->assertSame(110, $first->total());
        $this->assertCount(10, $first->items());
        $this->assertSame($ids[109], $first->first()->id);
        $second = $this->get($first->nextPageUrl())->assertOk()->viewData('movementPage');
        $this->assertSame([], array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->assertStringContainsString('tab=stock-card', $first->nextPageUrl());
        $filtered = $this->get($url.'&movement_q=Historical&movement_type=INVENTORY_INITIAL_STOCK&movement_from=2026-10-28&movement_to=2026-10-28&movement_page=99')
            ->assertOk()->assertSee('name="movement_q"', false)->assertSeeText('Showing 1–1 of 1 records');
        $this->assertSame(1, $filtered->viewData('movementPage')->total());
        $this->assertSame($ids[0], $filtered->viewData('movementPage')->first()->id);
        $this->assertSame($ids[109], $filtered->viewData('lastStockEntry')->id);
        $this->assertDatabaseCount('inventory_transaction_lines', 110);
        $this->assertEquals(110, $item->fresh()->total_quantity);
    }

    public function test_academic_filters_use_displayed_status_and_do_not_change_the_active_period(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        AcademicPeriod::query()->delete();
        $active = AcademicPeriod::create(['academic_year' => '2026-2027', 'term_code' => 'FIRST_SEMESTER',
            'term_name' => 'First Semester', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);
        for ($year = 2010; $year < 2023; $year++) {
            AcademicPeriod::create(['academic_year' => $year.'-'.($year + 1), 'term_code' => 'FIRST_SEMESTER',
                'term_name' => 'First Semester', 'start_date' => $year.'-08-01', 'end_date' => $year.'-12-31', 'status' => 'INACTIVE']);
        }
        $url = route('policies.index', ['section' => 'academic-periods', 'period_status' => 'COMPLETED', 'period_sort' => 'oldest']);
        $this->actingAs($head);
        $response = $this->get($url)->assertOk();
        $page = $response->viewData('academicPeriods');
        $this->assertSame(13, $page->total());
        $this->assertSame('2010-2011', $page->first()->academic_year);
        $this->assertSame($active->id, $response->viewData('activePeriod')->id);
        $next = $this->get($page->nextPageUrl())->assertOk()->viewData('academicPeriods');
        $this->assertCount(3, $next->items());
        $this->assertStringContainsString('period_status=COMPLETED', $page->nextPageUrl());
        $searched = $this->get($url.'&period_q=2022-2023&period_page=9')->assertOk()->viewData('academicPeriods');
        $this->assertSame(1, $searched->total());
        $this->assertSame(1, $searched->currentPage());
        $this->assertSame('ACTIVE', $active->fresh()->status);
    }

    public function test_special_date_filters_include_historical_records_and_apply_before_pagination(): void
    {
        $head = User::factory()->create(['access_classification' => AccessClassification::SpmuHead]);
        OperationalDateException::query()->delete();
        for ($day = 1; $day <= 23; $day++) {
            OperationalDateException::create(['exception_date' => '2020-01-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'status' => $day % 2 ? 'CLOSED' : 'OPEN', 'allows_pickup' => false, 'allows_return' => false,
                'reason' => 'Archived closure '.$day]);
        }
        $this->actingAs($head);
        $url = route('policies.index', ['section' => 'special-dates', 'exception_status' => 'CLOSED', 'exception_to' => '2020-01-23']);
        $page = $this->get($url)->assertOk()->viewData('dateExceptions');
        $this->assertSame(12, $page->total());
        $next = $this->get($page->nextPageUrl())->assertOk()->viewData('dateExceptions');
        $this->assertCount(2, $next->items());
        $this->assertStringContainsString('exception_status=CLOSED', $page->nextPageUrl());
        $filtered = $this->get($url.'&exception_q=Archived+closure+23')->assertOk()->viewData('dateExceptions');
        $this->assertSame(1, $filtered->total());
        $this->assertSame('2020-01-23', $filtered->first()->exception_date->toDateString());
        $this->assertSame(12, $this->get($url.'&exception_q=Date-based')->assertOk()->viewData('dateExceptions')->total());
        $this->get($url.'&exception_from=2020-02-01')->assertSessionHasErrors('exception_to');
        $this->get($url.'&exception_per_page=999')->assertSessionHasErrors('exception_per_page');
        $this->assertDatabaseCount('operational_date_exceptions', 23);
    }

    public function test_configuration_filters_do_not_grant_borrower_access(): void
    {
        $borrower = User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
        $this->actingAs($borrower)->get(route('policies.index', ['section' => 'special-dates', 'exception_q' => 'test']))->assertForbidden();
    }
}
