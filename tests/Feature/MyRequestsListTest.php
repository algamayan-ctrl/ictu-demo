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
 * Borrower My Requests list: server-side pagination, search, status filter,
 * and sort (ICTU RSC: "Include Pagination, Search and Filters in all the
 * tables"), plus the borrower ownership restriction.
 */
class MyRequestsListTest extends TestCase
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
     * Create one request. Each request gets a later created_at (and, when
     * submitted, a later submitted_at), so newest-first is the reverse of
     * creation order.
     *
     * @param  array{request_no?:string,purpose?:string,item?:string,office_unit?:string,submitted?:bool,custody?:array<string,mixed>|null,returned?:int}  $options
     */
    private function request(User $borrower, RequestStatus $status = RequestStatus::UnderSpmu, array $options = []): BorrowingRequest
    {
        $this->sequence++;

        $unit = OrganizationalUnit::query()->firstOrCreate(
            ['unit_code' => 'MYREQFIX'],
            ['unit_name' => 'My Requests Fixture Unit', 'unit_type' => 'OFFICE', 'active' => true]
        );
        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'MYREQ'],
            ['category_name' => 'My Requests Fixture', 'active' => true]
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
            'request_no' => $options['request_no'] ?? sprintf('BR-MR-%05d', $this->sequence),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $unit->id,
            'current_version_no' => 1,
            'status' => $status,
        ]);

        $submitted = $options['submitted'] ?? ($status !== RequestStatus::Draft);
        $timestamp = now()->subDays(30)->addMinutes($this->sequence);

        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => $options['purpose'] ?? 'Fixture event '.$this->sequence,
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => $options['office_unit'] ?? 'College of Computer Studies',
            'schedule_date' => now()->addDays(5)->toDateString(),
            'return_date' => now()->addDays(8)->toDateString(),
            'needed_from' => now()->addDays(5)->startOfDay(),
            'return_due_at' => now()->addDays(8)->endOfDay(),
            'submitted_at' => $submitted ? $timestamp : null,
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $itemName,
            'unit_snapshot' => 'Piece',
            'requested_quantity' => 5,
            'approved_quantity' => 5,
        ]);

        if (($options['custody'] ?? null) !== null) {
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
                'custody_no' => sprintf('CUS-MR-%05d', $this->sequence),
                'request_id' => $request->id,
                'request_version_id' => $version->id,
                'borrower_user_id' => $borrower->id,
                'status' => 'BORROWED',
                'due_at' => now()->addDays(3),
                'released_at' => now()->subDays(5),
            ], $options['custody']));

            CustodyLine::query()->create([
                'custody_transaction_id' => $custody->id,
                'request_item_id' => $requestItem->id,
                'allocation_id' => $allocation->id,
                'approved_quantity' => 5,
                'quantity_to_receive' => 5,
                'actual_released_quantity' => 5,
                'returned_quantity' => $options['returned'] ?? 0,
            ]);
        }

        DB::table('borrowing_requests')
            ->where('id', $request->id)
            ->update(['created_at' => $timestamp, 'updated_at' => $timestamp]);

        return $request->fresh();
    }

    /** @param array<string, mixed> $query */
    private function myRequests(User $borrower, array $query = []): TestResponse
    {
        return $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('requests.index', $query));
    }

    /** @return list<int> */
    private function ids(TestResponse $response): array
    {
        return collect($response->viewData('requests')->items())->pluck('id')->all();
    }

    /* ------------------------------------------------------------------ */
    /* Ownership                                                           */
    /* ------------------------------------------------------------------ */

    public function test_borrower_sees_only_their_own_requests(): void
    {
        $borrower = $this->borrower();
        $other = $this->borrower();
        $own = $this->request($borrower);
        $foreign = $this->request($other, RequestStatus::UnderSpmu, ['item' => 'Foreign Projector']);

        $response = $this->myRequests($borrower);
        $response->assertOk()->assertSee($own->request_no)->assertDontSee(route('requests.show', $foreign), false);
        $this->assertSame([$own->id], $this->ids($response));

        foreach ([
            ['search' => $foreign->request_no],
            ['search' => 'Foreign Projector'],
            ['status' => 'FOR_REVIEW'],
            ['search' => $foreign->request_no, 'status' => 'FOR_REVIEW'],
        ] as $query) {
            $filtered = $this->myRequests($borrower, $query);
            $filtered->assertOk()->assertDontSee(route('requests.show', $foreign), false);
            $this->assertNotContains($foreign->id, $this->ids($filtered));
        }
    }

    /* ------------------------------------------------------------------ */
    /* Pagination                                                          */
    /* ------------------------------------------------------------------ */

    public function test_requests_are_split_into_pages_of_ten(): void
    {
        $borrower = $this->borrower();
        $records = collect(range(1, 23))->map(fn () => $this->request($borrower));

        $page1 = $this->myRequests($borrower);
        $page1->assertOk()->assertSee('Showing 1 to 10 of 23 requests')->assertSee('23 requests found');
        $this->assertSame(3, $page1->viewData('requests')->lastPage());

        $page2 = $this->myRequests($borrower, ['page' => 2]);
        $page2->assertSee('Showing 11 to 20 of 23 requests');

        $page3 = $this->myRequests($borrower, ['page' => 3]);
        $page3->assertSee('Showing 21 to 23 of 23 requests');

        $allIds = array_merge($this->ids($page1), $this->ids($page2), $this->ids($page3));
        $this->assertEqualsCanonicalizing($records->pluck('id')->all(), $allIds);
        $this->assertSame($records->last()->id, $this->ids($page1)[0]);
    }

    public function test_page_past_the_last_page_offers_the_first_page(): void
    {
        $borrower = $this->borrower();
        $this->request($borrower);

        $this->myRequests($borrower, ['page' => 5])
            ->assertOk()
            ->assertSee('This page has no requests.')
            ->assertSee('Go to first page')
            ->assertDontSee('No borrowing requests yet.');
    }

    /* ------------------------------------------------------------------ */
    /* Search                                                              */
    /* ------------------------------------------------------------------ */

    public function test_search_finds_requests_across_all_pages(): void
    {
        $borrower = $this->borrower();

        // The oldest request, so unfiltered it sits on page 2.
        $target = $this->request($borrower, RequestStatus::UnderSpmu, [
            'request_no' => 'BR-TARGET-4242',
            'purpose' => 'Intramurals Opening Ceremony',
            'item' => 'Portable Stage Light',
            'office_unit' => 'Office of Student Affairs',
        ]);
        collect(range(1, 14))->each(fn () => $this->request($borrower));

        $this->assertNotContains($target->id, $this->ids($this->myRequests($borrower)));

        foreach (['target-4242', 'intramurals opening', 'stage light', 'student affairs'] as $term) {
            $response = $this->myRequests($borrower, ['search' => $term]);
            $response->assertOk();
            $this->assertSame([$target->id], $this->ids($response), "Search for [$term] should find only the target.");
            $response->assertSee('1 request found');
        }
    }

    public function test_no_matching_requests_shows_clear_filters(): void
    {
        $borrower = $this->borrower();
        $this->request($borrower);

        $this->myRequests($borrower, ['search' => 'nothing-matches-this'])
            ->assertOk()
            ->assertSee('No matching requests.')
            ->assertSee('Clear filters')
            ->assertSee('href="'.route('requests.index').'"', false)
            ->assertSee('value="nothing-matches-this"', false)
            ->assertDontSee('No borrowing requests yet.');
    }

    public function test_borrower_with_no_requests_sees_first_time_empty_state(): void
    {
        $this->myRequests($this->borrower())
            ->assertOk()
            ->assertSee('No borrowing requests yet.')
            ->assertDontSee('No matching requests.');
    }

    /* ------------------------------------------------------------------ */
    /* Status filter                                                       */
    /* ------------------------------------------------------------------ */

    public function test_status_filters_match_the_row_badges(): void
    {
        $borrower = $this->borrower();

        $draft = $this->request($borrower, RequestStatus::Draft);
        $forReview = $this->request($borrower, RequestStatus::UnderSpmu);
        $submitted = $this->request($borrower, RequestStatus::Submitted);
        $revision = $this->request($borrower, RequestStatus::ReturnedForRevision);
        $rejected = $this->request($borrower, RequestStatus::Rejected);
        $cancelled = $this->request($borrower, RequestStatus::Cancelled);
        $approvedNoCustody = $this->request($borrower, RequestStatus::ApprovedReadyForRelease);
        $preparing = $this->request($borrower, RequestStatus::ApprovedReadyForRelease, ['custody' => [
            'status' => 'PREPARING_RELEASE',
            'released_at' => null,
        ]]);
        $onCustody = $this->request($borrower, RequestStatus::ApprovedReadyForRelease, ['custody' => []]);
        $overdue = $this->request($borrower, RequestStatus::ApprovedReadyForRelease, ['custody' => [
            'status' => 'OVERDUE',
            'due_at' => now()->subDays(2),
        ]]);
        $closed = $this->request($borrower, RequestStatus::ApprovedReadyForRelease, [
            'custody' => ['status' => 'CLOSED', 'closed_at' => now()->subDay()],
            'returned' => 5,
        ]);

        // An open accountability case: My Requests reports the borrowing as
        // Completed (the case itself is followed in My Obligations).
        $withCase = $this->request($borrower, RequestStatus::ApprovedReadyForRelease, ['custody' => []]);
        Incident::query()->create([
            'incident_no' => 'INC-MR-0001',
            'custody_transaction_id' => $withCase->custody->id,
            'borrower_user_id' => $borrower->id,
            'reported_by_user_id' => $this->officer()->id,
            'incident_type' => 'DAMAGED',
            'reported_at' => now(),
            'status' => 'COMPLIANCE_REQUIRED',
        ]);

        $expectations = [
            'DRAFT' => [$draft->id],
            'FOR_REVIEW' => [$forReview->id, $submitted->id],
            'NEEDS_REVISION' => [$revision->id],
            'REJECTED' => [$rejected->id],
            'CANCELLED' => [$cancelled->id],
            'FOR_ISSUANCE' => [$approvedNoCustody->id, $preparing->id],
            'ON_CUSTODY' => [$onCustody->id],
            'OVERDUE' => [$overdue->id],
            'COMPLETED' => [$closed->id, $withCase->id],
            'MISSED_PICKUP' => [],
        ];

        foreach ($expectations as $status => $expectedIds) {
            $response = $this->myRequests($borrower, ['status' => $status]);
            $response->assertOk();
            $this->assertEqualsCanonicalizing($expectedIds, $this->ids($response), "Status filter [$status]");

            foreach ($response->viewData('requests')->items() as $request) {
                $this->assertSame($status, $request->borrowerRecordStatus()['key']);
            }
        }

        $this->myRequests($borrower, ['status' => 'COMPLETED'])
            ->assertSee('<option value="COMPLETED" selected>', false);
    }

    public function test_unknown_status_and_sort_values_fall_back_to_defaults(): void
    {
        $borrower = $this->borrower();
        $older = $this->request($borrower);
        $newer = $this->request($borrower);

        $response = $this->myRequests($borrower, ['status' => "DRAFT' OR 1=1 --", 'sort' => 'request_no; DROP TABLE users']);

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $this->ids($response));
        $this->assertSame('all', $response->viewData('borrowerRequestFilters')['status']);
        $this->assertSame('newest', $response->viewData('borrowerRequestFilters')['sort']);
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
        $older = $this->request($borrower);
        $newer = $this->request($borrower);
        $this->request($this->borrower());

        // Raw bracket notation, so the controller receives real arrays.
        $response = $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->get(route('requests.index').'?'.$queryString);

        $response->assertOk();
        $this->assertSame([$newer->id, $older->id], $this->ids($response));
        $this->assertSame(
            ['search' => '', 'status' => 'all', 'sort' => 'newest', 'active' => false],
            $response->viewData('borrowerRequestFilters')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Sort                                                                */
    /* ------------------------------------------------------------------ */

    public function test_sort_uses_the_submitted_date_shown_on_the_row(): void
    {
        $borrower = $this->borrower();
        $a = $this->request($borrower);
        $b = $this->request($borrower);
        $c = $this->request($borrower);

        $this->assertSame([$c->id, $b->id, $a->id], $this->ids($this->myRequests($borrower)));
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids($this->myRequests($borrower, ['sort' => 'oldest'])));

        // Created first but submitted last: sorts as the newest.
        RequestVersion::query()->where('request_id', $a->id)->update(['submitted_at' => now()]);
        $this->assertSame([$a->id, $c->id, $b->id], $this->ids($this->myRequests($borrower)));
    }

    /* ------------------------------------------------------------------ */
    /* Combined                                                            */
    /* ------------------------------------------------------------------ */

    public function test_search_and_status_work_together_and_stay_in_page_links(): void
    {
        $borrower = $this->borrower();
        $matching = collect(range(1, 12))->map(fn () => $this->request($borrower, RequestStatus::UnderSpmu, ['item' => 'Folding Chair']));
        $this->request($borrower, RequestStatus::Draft, ['item' => 'Folding Chair']);
        $this->request($borrower, RequestStatus::UnderSpmu, ['item' => 'Round Table']);

        $query = ['search' => 'Folding Chair', 'status' => 'FOR_REVIEW', 'sort' => 'oldest'];

        $page1 = $this->myRequests($borrower, $query);
        $page1->assertOk()->assertSee('Showing 1 to 10 of 12 requests');

        $nextUrl = $page1->viewData('requests')->nextPageUrl();
        $this->assertStringContainsString('search=Folding%20Chair', $nextUrl);
        $this->assertStringContainsString('status=FOR_REVIEW', $nextUrl);
        $this->assertStringContainsString('sort=oldest', $nextUrl);
        $page1->assertSee(e($nextUrl), false);

        $page2 = $this->myRequests($borrower, $query + ['page' => 2]);
        $page2->assertOk()
            ->assertSee('Showing 11 to 12 of 12 requests')
            ->assertSee('value="Folding Chair"', false)
            ->assertSee('<option value="FOR_REVIEW" selected>', false)
            ->assertSee('<option value="oldest" selected>', false);

        $this->assertSame($matching->slice(10)->pluck('id')->values()->all(), $this->ids($page2));

        // The filter form never carries the page, so a new filter starts at page 1.
        $page2->assertSee('id="request-filters"', false)->assertDontSee('name="page"', false);
    }

    /* ------------------------------------------------------------------ */
    /* Staff view unaffected                                               */
    /* ------------------------------------------------------------------ */

    public function test_staff_request_records_are_not_paginated(): void
    {
        $borrower = $this->borrower();
        collect(range(1, 12))->each(fn () => $this->request($borrower, RequestStatus::ApprovedReadyForRelease, ['custody' => []]));
        // Action Officers see requests after final approval.
        DB::table('borrowing_requests')->update(['final_approved_at' => now()->subDay()]);

        $response = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($this->officer())
            ->get(route('requests.index', ['search' => 'nothing', 'status' => 'DRAFT']));

        $response->assertOk();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $response->viewData('requests'));
        $this->assertCount(12, $response->viewData('requests'));
    }
}
