<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Allocation;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Borrower My Borrowings list: server-side pagination, search, status
 * filter, and sort (ICTU RSC: "Include Pagination, Search and Filters in
 * all the tables"), plus the borrower ownership restriction.
 */
class MyBorrowingsListTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 9, 15, 9));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */
    /* Fixture                                                             */
    /* ------------------------------------------------------------------ */

    private function borrower(): User
    {
        return User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
        ])->fresh();
    }

    private function officer(): User
    {
        $user = User::factory()->create(['access_classification' => AccessClassification::SpmuOfficer]);
        $role = Role::query()->firstOrCreate(
            ['role_code' => UserRole::Spmu->value],
            ['role_name' => 'SPMU', 'active' => true]
        );
        $user->roles()->syncWithoutDetaching([$role->id => ['assigned_at' => now()]]);

        return $user->fresh();
    }

    /**
     * Create one custody record. Each record gets an increasing updated_at,
     * so the newest-first order is the reverse of creation order.
     *
     * @param  array<string, mixed>  $custodyOverrides
     * @param  array{request_no?:string,purpose?:string,item?:string,request_status?:RequestStatus,returned?:int}  $options
     */
    private function custody(User $borrower, array $custodyOverrides = [], array $options = []): CustodyTransaction
    {
        $this->sequence++;

        $unit = OrganizationalUnit::query()->firstOrCreate(
            ['unit_code' => 'MYBORFIX'],
            ['unit_name' => 'My Borrowings Fixture Unit', 'unit_type' => 'OFFICE', 'active' => true]
        );
        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'MYBOR'],
            ['category_name' => 'My Borrowings Fixture', 'active' => true]
        );
        $measure = UnitOfMeasure::query()->firstOrCreate(
            ['unit_code' => 'PC'],
            ['unit_name' => 'Piece', 'active' => true]
        );

        $itemName = $options['item'] ?? 'Fixture Equipment '.$this->sequence;

        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'unit_id' => $measure->id,
            'unique_description' => $itemName.' #'.$this->sequence,
            'total_quantity' => 50,
            'condition_code' => 'SERVICEABLE',
            'borrowable' => true,
            'off_campus_allowed' => false,
            'laundry_required' => false,
            'provisional' => false,
            'active' => true,
        ]);

        $request = BorrowingRequest::query()->create([
            'request_no' => $options['request_no'] ?? sprintf('BR-MB-%05d', $this->sequence),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $unit->id,
            'current_version_no' => 1,
            'status' => $options['request_status'] ?? RequestStatus::ApprovedReadyForRelease,
        ]);

        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => $options['purpose'] ?? 'Fixture event '.$this->sequence,
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => 'College of Computer Studies',
            'schedule_date' => now()->subDays(5)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'needed_from' => now()->subDays(5)->startOfDay(),
            'return_due_at' => now()->addDays(3)->endOfDay(),
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $itemName,
            'unit_snapshot' => 'Piece',
            'requested_quantity' => 5,
            'approved_quantity' => 5,
        ]);

        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => now()->subDays(5)->startOfDay(),
            'period_end' => now()->addDays(3)->endOfDay(),
            'allocated_quantity' => 5,
            'released_quantity' => 5,
            'restored_quantity' => 0,
            'status' => 'RELEASED',
            'allocated_at' => now()->subDays(5),
        ]);

        $custody = CustodyTransaction::query()->create(array_merge([
            'custody_no' => sprintf('CUS-MB-%05d', $this->sequence),
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id,
            'status' => 'BORROWED',
            'due_at' => now()->addDays(3),
            'released_at' => now()->subDays(5),
        ], $custodyOverrides));

        CustodyLine::query()->create([
            'custody_transaction_id' => $custody->id,
            'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id,
            'approved_quantity' => 5,
            'quantity_to_receive' => 5,
            'actual_released_quantity' => 5,
            'returned_quantity' => $options['returned'] ?? 0,
        ]);

        DB::table('custody_transactions')
            ->where('id', $custody->id)
            ->update(['updated_at' => now()->subDays(30)->addMinutes($this->sequence)]);

        return $custody->fresh();
    }

    private function pickupSchedulingCustody(User $borrower, array $options = []): CustodyTransaction
    {
        return $this->custody($borrower, [
            'status' => 'PREPARING_RELEASE',
            'released_at' => null,
            'scheduled_release_at' => null,
            'pickup_expires_at' => null,
        ], $options);
    }

    private function completedCustody(User $borrower, array $options = []): CustodyTransaction
    {
        return $this->custody($borrower, [
            'status' => 'CLOSED',
            'closed_at' => now()->subDay(),
        ], ['returned' => 5] + $options);
    }

    private function cancelledCustody(User $borrower, array $options = []): CustodyTransaction
    {
        return $this->custody($borrower, [
            'status' => 'CANCELLED',
            'released_at' => null,
            'closed_at' => now()->subDay(),
        ], ['request_status' => RequestStatus::Cancelled] + $options);
    }

    /** @param array<string, mixed> $query */
    private function myBorrowings(User $borrower, array $query = []): TestResponse
    {
        return $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('custody.index', $query));
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return collect($response->viewData('custodies')->items())->pluck('id')->all();
    }

    /* ------------------------------------------------------------------ */
    /* Ownership                                                           */
    /* ------------------------------------------------------------------ */

    public function test_borrower_sees_their_own_borrowing_records(): void
    {
        $borrower = $this->borrower();
        $first = $this->custody($borrower);
        $second = $this->pickupSchedulingCustody($borrower);

        $response = $this->myBorrowings($borrower);

        $response->assertOk()
            ->assertSee($first->custody_no)
            ->assertSee($second->custody_no)
            ->assertSee('Showing 1 to 2 of 2 borrowings');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->ids($response));
    }

    public function test_borrower_cannot_see_another_borrowers_records(): void
    {
        $borrower = $this->borrower();
        $other = $this->borrower();
        $own = $this->custody($borrower);
        $foreign = $this->custody($other, [], ['item' => 'Projector Foreign']);

        $response = $this->myBorrowings($borrower);
        $response->assertOk()->assertDontSee($foreign->custody_no);
        $this->assertSame([$own->id], $this->ids($response));
        $this->assertSame(1, $response->viewData('custodies')->total());

        // Searching or filtering for the other borrower's record never reveals it.
        foreach ([
            ['search' => $foreign->custody_no],
            ['search' => 'Projector Foreign'],
            ['status' => 'BORROWED'],
            ['search' => $foreign->custody_no, 'status' => 'BORROWED'],
        ] as $query) {
            // (The search box echoes the typed text back, so check the record
            // link and data rather than the raw custody number.)
            $filtered = $this->myBorrowings($borrower, $query);
            $filtered->assertOk()->assertDontSee(route('custody.show', $foreign), false);
            $this->assertNotContains($foreign->id, $this->ids($filtered));
        }

        // The detail page stays protected too.
        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('custody.show', $foreign))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* Pagination                                                          */
    /* ------------------------------------------------------------------ */

    public function test_records_are_split_into_pages_of_ten(): void
    {
        $borrower = $this->borrower();
        $records = collect(range(1, 23))->map(fn () => $this->custody($borrower));

        $page1 = $this->myBorrowings($borrower);
        $page1->assertOk()->assertSee('Showing 1 to 10 of 23 borrowings')->assertSee('23 borrowings');
        $paginator = $page1->viewData('custodies');
        $this->assertSame(23, $paginator->total());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(10, $paginator->items());

        $page2 = $this->myBorrowings($borrower, ['page' => 2]);
        $page2->assertSee('Showing 11 to 20 of 23 borrowings');
        $this->assertCount(10, $this->ids($page2));

        $page3 = $this->myBorrowings($borrower, ['page' => 3]);
        $page3->assertSee('Showing 21 to 23 of 23 borrowings');
        $this->assertCount(3, $this->ids($page3));

        // Every record appears exactly once across the pages.
        $allIds = array_merge($this->ids($page1), $this->ids($page2), $this->ids($page3));
        $this->assertEqualsCanonicalizing($records->pluck('id')->all(), $allIds);

        // Newest first: page 1 starts with the most recently updated record.
        $this->assertSame($records->last()->id, $this->ids($page1)[0]);
        $page1->assertSee('aria-label="My borrowings pagination"', false);
    }

    public function test_single_page_hides_page_links(): void
    {
        $borrower = $this->borrower();
        $this->custody($borrower);

        $this->myBorrowings($borrower)
            ->assertOk()
            ->assertSee('Showing 1 to 1 of 1 borrowing')
            ->assertDontSee('aria-label="My borrowings pagination"', false);
    }

    /* ------------------------------------------------------------------ */
    /* Search                                                              */
    /* ------------------------------------------------------------------ */

    public function test_search_finds_records_across_all_pages(): void
    {
        $borrower = $this->borrower();

        // The target is the oldest record, so unfiltered it sits on page 2.
        $target = $this->custody($borrower, [], [
            'request_no' => 'BR-TARGET-777',
            'purpose' => 'Founders Day Program',
            'item' => 'Wireless Microphone',
        ]);
        collect(range(1, 14))->each(fn () => $this->custody($borrower));

        $this->assertNotContains($target->id, $this->ids($this->myBorrowings($borrower)));

        foreach ([
            $target->custody_no,          // custody no.
            'target-777',                 // request no. (partial, case-insensitive)
            'founders day',               // purpose / event
            'wireless micro',             // item name
        ] as $term) {
            $response = $this->myBorrowings($borrower, ['search' => $term]);
            $response->assertOk();
            $this->assertSame([$target->id], $this->ids($response), "Search for [$term] should find only the target.");
            $this->assertSame(1, $response->viewData('custodies')->total());
        }
    }

    public function test_search_treats_wildcard_characters_literally(): void
    {
        $borrower = $this->borrower();
        $this->custody($borrower);

        $response = $this->myBorrowings($borrower, ['search' => '%']);

        $response->assertOk()->assertSee('No matching borrowings.');
        $this->assertSame([], $this->ids($response));
    }

    public function test_empty_search_result_shows_no_matching_state(): void
    {
        $borrower = $this->borrower();
        $this->custody($borrower);

        $this->myBorrowings($borrower, ['search' => 'does-not-exist-xyz'])
            ->assertOk()
            ->assertSee('No matching borrowings.')
            ->assertSee('Clear filters')
            ->assertSee('value="does-not-exist-xyz"', false)
            ->assertDontSee('No borrowing records yet.');
    }

    public function test_borrower_with_no_records_sees_first_time_empty_state(): void
    {
        $this->myBorrowings($this->borrower())
            ->assertOk()
            ->assertSee('No borrowing records yet.')
            ->assertDontSee('No matching borrowings.');
    }

    /* ------------------------------------------------------------------ */
    /* Status filter                                                       */
    /* ------------------------------------------------------------------ */

    public function test_status_filters_return_the_matching_records(): void
    {
        $borrower = $this->borrower();
        $borrowed = $this->custody($borrower);
        $pickup = $this->pickupSchedulingCustody($borrower);
        $completed = $this->completedCustody($borrower);
        $cancelled = $this->cancelledCustody($borrower);
        $overdue = $this->custody($borrower, ['status' => 'OVERDUE', 'due_at' => now()->subDays(2)]);

        // Open accountability case: the badge reads "Compliance Required", so
        // it belongs to Accountability Pending, not On Custody.
        $withIncident = $this->custody($borrower);
        Incident::query()->create([
            'incident_no' => 'INC-MB-0001',
            'custody_transaction_id' => $withIncident->id,
            'borrower_user_id' => $borrower->id,
            'reported_by_user_id' => $this->officer()->id,
            'incident_type' => 'DAMAGED',
            'reported_at' => now(),
            'status' => 'COMPLIANCE_REQUIRED',
        ]);

        $expectations = [
            'BORROWED' => [$borrowed->id],
            'PICKUP_SCHEDULING' => [$pickup->id],
            'COMPLETED' => [$completed->id],
            'CANCELLED' => [$cancelled->id],
            'OVERDUE' => [$overdue->id],
            'ACCOUNTABILITY' => [$withIncident->id],
            'RETURN_PROCESSING' => [],
        ];

        foreach ($expectations as $status => $expectedIds) {
            $response = $this->myBorrowings($borrower, ['status' => $status]);
            $response->assertOk();
            $this->assertEqualsCanonicalizing($expectedIds, $this->ids($response), "Status filter [$status]");

            // Every returned row shows the badge the filter promised.
            foreach ($response->viewData('custodies')->items() as $custody) {
                $key = $custody->borrowerListStatus()['key'];
                $status === 'ACCOUNTABILITY'
                    ? $this->assertSame('attention', $custody->borrowerListStatus()['group'])
                    : $this->assertSame($status, $key);
            }
        }

        $this->myBorrowings($borrower, ['status' => 'COMPLETED'])
            ->assertSee('<option value="COMPLETED" selected>', false);
    }

    public function test_unknown_status_and_sort_values_fall_back_to_defaults(): void
    {
        $borrower = $this->borrower();
        $older = $this->custody($borrower);
        $newer = $this->custody($borrower);

        $response = $this->myBorrowings($borrower, ['status' => "BORROWED' OR 1=1 --", 'sort' => 'custody_no; DROP TABLE users']);

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $this->ids($response));
        $this->assertSame('all', $response->viewData('borrowerFilters')['status']);
        $this->assertSame('newest', $response->viewData('borrowerFilters')['sort']);
    }

    /** @return array<string, array{string}> */
    public static function arrayQueryStrings(): array
    {
        return [
            'search[]' => ['search[]=x'],
            'status[]' => ['status[]=x'],
            'sort[]' => ['sort[]=x'],
            'all three' => ['search[]=x&status[]=x&sort[]=x'],
        ];
    }

    #[DataProvider('arrayQueryStrings')]
    public function test_array_query_values_fall_back_to_defaults(string $queryString): void
    {
        $borrower = $this->borrower();
        $older = $this->custody($borrower);
        $newer = $this->custody($borrower);
        $this->custody($this->borrower());

        // Raw bracket notation, so the controller receives real arrays.
        $response = $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('custody.index').'?'.$queryString);

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $this->ids($response));
        $this->assertSame(
            ['search' => '', 'status' => 'all', 'sort' => 'newest', 'active' => false],
            $response->viewData('borrowerFilters')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Sort                                                                */
    /* ------------------------------------------------------------------ */

    public function test_sort_orders_records_newest_or_oldest_first(): void
    {
        $borrower = $this->borrower();
        $a = $this->custody($borrower);
        $b = $this->custody($borrower);
        $c = $this->custody($borrower);

        $this->assertSame([$c->id, $b->id, $a->id], $this->ids($this->myBorrowings($borrower)));
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids($this->myBorrowings($borrower, ['sort' => 'oldest'])));
    }

    /* ------------------------------------------------------------------ */
    /* Combined                                                            */
    /* ------------------------------------------------------------------ */

    public function test_search_and_status_filter_work_together(): void
    {
        $borrower = $this->borrower();
        $match = $this->custody($borrower, [], ['item' => 'Sound System']);
        $wrongStatus = $this->completedCustody($borrower, ['item' => 'Sound System']);
        $wrongSearch = $this->custody($borrower, [], ['item' => 'Folding Table']);

        $response = $this->myBorrowings($borrower, ['search' => 'sound system', 'status' => 'BORROWED']);

        $response->assertOk();
        $this->assertSame([$match->id], $this->ids($response));
        $response->assertDontSee($wrongStatus->custody_no)->assertDontSee($wrongSearch->custody_no);
    }

    public function test_search_status_and_sort_are_kept_in_pagination_links(): void
    {
        $borrower = $this->borrower();
        $matching = collect(range(1, 12))->map(fn () => $this->custody($borrower, [], ['item' => 'Event Tent']));
        // Same item, different status: must never appear on any page.
        $this->completedCustody($borrower, ['item' => 'Event Tent']);

        $query = ['search' => 'Event Tent', 'status' => 'BORROWED', 'sort' => 'oldest'];

        $page1 = $this->myBorrowings($borrower, $query);
        $page1->assertOk()->assertSee('Showing 1 to 10 of 12 borrowings');

        $nextUrl = $page1->viewData('custodies')->nextPageUrl();
        $this->assertStringContainsString('search=Event%20Tent', $nextUrl);
        $this->assertStringContainsString('status=BORROWED', $nextUrl);
        $this->assertStringContainsString('sort=oldest', $nextUrl);
        $this->assertStringContainsString('page=2', $nextUrl);
        $page1->assertSee(e($nextUrl), false);

        $page2 = $this->myBorrowings($borrower, $query + ['page' => 2]);
        $page2->assertOk()
            ->assertSee('Showing 11 to 12 of 12 borrowings')
            ->assertSee('value="Event Tent"', false)
            ->assertSee('<option value="BORROWED" selected>', false)
            ->assertSee('<option value="oldest" selected>', false);

        // Oldest first: page 2 holds the two newest matching records.
        $this->assertSame($matching->slice(10)->pluck('id')->values()->all(), $this->ids($page2));
    }

    public function test_filter_form_starts_again_at_page_one(): void
    {
        $borrower = $this->borrower();
        collect(range(1, 12))->each(fn () => $this->custody($borrower));

        // The toolbar form never carries the current page, so submitting a new
        // search/status/sort always lands on page 1.
        $this->myBorrowings($borrower, ['page' => 2])
            ->assertOk()
            ->assertSee('id="borrowings-filters"', false)
            ->assertDontSee('name="page"', false);
    }

    public function test_dashboard_kpi_drilldown_still_works_with_search(): void
    {
        $borrower = $this->borrower();
        $active = $this->custody($borrower, [], ['item' => 'Laptop']);
        $otherActive = $this->custody($borrower, [], ['item' => 'Speaker']);
        $this->pickupSchedulingCustody($borrower, ['item' => 'Laptop']);

        $response = $this->myBorrowings($borrower, ['kpi' => 'active_borrowings', 'search' => 'laptop']);

        $response->assertOk()->assertSee('name="kpi" value="active_borrowings"', false);
        $this->assertSame([$active->id], $this->ids($response));
        $this->assertNotContains($otherActive->id, $this->ids($response));
    }

    /* ------------------------------------------------------------------ */
    /* Staff views unaffected                                              */
    /* ------------------------------------------------------------------ */

    public function test_spmu_release_and_return_lists_are_not_paginated(): void
    {
        $officer = $this->officer();
        $borrower = $this->borrower();
        collect(range(1, 12))->each(fn () => $this->custody($borrower));

        $response = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($officer)
            ->get(route('custody.return.index'));

        $response->assertOk();
        $this->assertCount(12, $response->viewData('custodies'));
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $response->viewData('custodies'));
    }
}
