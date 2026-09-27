<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OperationalDateException;
use App\Models\OperationalWeeklySchedule;
use App\Models\OrganizationalUnit;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestOperationalDateValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(Carbon::create(2026, 9, 14, 9, 0)); // Monday
        $this->configureWeekdays();

        $this->borrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::query()
                ->where('unit_code', 'CHS')
                ->value('id'),
        ]);

        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'REQUEST-DATE-VALIDATION'],
            ['category_name' => 'Request Date Validation', 'active' => true]
        );
        $unit = UnitOfMeasure::query()->firstOrCreate(
            ['unit_code' => 'PC'],
            ['unit_name' => 'Piece', 'active' => true]
        );

        $this->item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'unique_description' => 'Request date validation fixture',
            'total_quantity' => 10,
            'condition_code' => 'SERVICEABLE',
            'borrowable' => true,
            'off_campus_allowed' => false,
            'laundry_required' => false,
            'provisional' => false,
            'active' => true,
        ]);
    }

    public function test_direct_post_rejects_a_normally_closed_weekend_items_needed_from_date(): void
    {
        $this->postRequest('2026-09-20', '2026-09-21')
            ->assertSessionHasErrors(['schedule_date']);

        $this->assertDatabaseCount('borrowing_requests', 0);
    }

    public function test_special_date_can_open_a_weekend_for_request_dates(): void
    {
        $this->openSpecialDate('2026-09-20');

        $this->postRequest('2026-09-20', '2026-09-21')
            ->assertSessionHasNoErrors();

        $version = RequestVersion::query()->latest('id')->firstOrFail();

        $this->assertSame('2026-09-20', $version->schedule_date->toDateString());
        $this->assertSame('2026-09-21', $version->return_date->toDateString());
    }

    public function test_normally_open_weekdays_are_accepted_for_request_dates(): void
    {
        $this->postRequest('2026-09-21', '2026-09-22')
            ->assertSessionHasNoErrors();
    }

    public function test_special_date_can_close_a_normally_open_weekday_for_request_dates(): void
    {
        $this->closeSpecialDate('2026-09-21');

        $this->postRequest('2026-09-21', '2026-09-22')
            ->assertSessionHasErrors(['schedule_date']);
    }

    public function test_direct_post_cannot_bypass_a_closed_expected_return_date_with_legacy_field_names(): void
    {
        $this->postRequest('2026-09-18', '2026-09-20', [
            'schedule_date' => null,
            'return_date' => null,
            'needed_from' => '2026-09-18T00:00',
            'return_due_at' => '2026-09-20T23:59',
        ])->assertSessionHasErrors(['return_date']);
    }

    public function test_draft_edits_follow_operational_date_validation(): void
    {
        $this->postRequest('2026-09-21', '2026-09-22')
            ->assertSessionHasNoErrors();

        $request = BorrowingRequest::query()->sole();

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($this->borrower)
            ->put(route('requests.update', $request), $this->payload('2026-09-20', '2026-09-21'))
            ->assertSessionHasErrors(['schedule_date']);

        $this->assertSame('2026-09-21', $request->fresh()->currentVersion->schedule_date->toDateString());
    }

    public function test_returned_for_revision_updates_follow_operational_date_validation(): void
    {
        $this->postRequest('2026-09-21', '2026-09-22')
            ->assertSessionHasNoErrors();

        $request = BorrowingRequest::query()->sole();
        $request->update(['status' => RequestStatus::ReturnedForRevision]);
        $this->closeSpecialDate('2026-09-21');

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($this->borrower)
            ->put(route('requests.update', $request), $this->payload('2026-09-21', '2026-09-22'))
            ->assertSessionHasErrors(['schedule_date']);

        $this->assertSame(1, $request->fresh()->versions()->count());
        $this->assertSame(RequestStatus::ReturnedForRevision, $request->fresh()->status);
    }

    public function test_operational_date_validation_endpoint_reports_effective_day_availability(): void
    {
        $this->closeSpecialDate('2026-09-21');
        $this->openSpecialDate('2026-09-20');

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($this->borrower)
            ->getJson(route('requests.operational-date-availability', [
                'schedule_date' => '2026-09-20',
                'return_date' => '2026-09-21',
            ]))
            ->assertOk()
            ->assertJsonPath('schedule_date.open', true)
            ->assertJsonPath('return_date.open', false);
    }

    /** @param array<string, mixed> $overrides */
    private function postRequest(string $scheduleDate, string $returnDate, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($this->borrower)
            ->post(route('requests.store'), $this->payload($scheduleDate, $returnDate, $overrides));
    }

    /** @param array<string, mixed> $overrides
     *  @return array<string, mixed>
     */
    private function payload(string $scheduleDate, string $returnDate, array $overrides = []): array
    {
        return array_replace([
            'purpose_event' => 'Request date validation fixture',
            'location' => 'Campus',
            'requesting_organizational_unit_id' => $this->borrower->organizational_unit_id,
            'schedule_date' => $scheduleDate,
            'return_date' => $returnDate,
            'intent' => 'draft',
            'item_ids' => [$this->item->id],
            'quantities' => [$this->item->id => 1],
            'locations' => [$this->item->id => 'ON_CAMPUS'],
        ], $overrides);
    }

    private function configureWeekdays(): void
    {
        foreach (range(1, 7) as $weekday) {
            $open = $weekday <= 5;

            OperationalWeeklySchedule::query()->updateOrCreate(
                ['weekday' => $weekday],
                [
                    'is_open' => $open,
                    'accepts_requests' => $open,
                    'allows_pickup' => $open,
                    'allows_return' => $open,
                    'open_time' => $open ? '13:00' : null,
                    'close_time' => $open ? '16:00' : null,
                ]
            );
        }
    }

    private function openSpecialDate(string $date): void
    {
        OperationalDateException::query()->updateOrCreate(
            ['exception_date' => $date],
            [
                'status' => 'OPEN',
                'accepts_requests' => true,
                'allows_pickup' => true,
                'allows_return' => true,
                'open_time' => '13:00',
                'close_time' => '16:00',
                'reason' => 'Special occasion.',
            ]
        );
    }

    private function closeSpecialDate(string $date): void
    {
        OperationalDateException::query()->updateOrCreate(
            ['exception_date' => $date],
            [
                'status' => 'CLOSED',
                'accepts_requests' => false,
                'allows_pickup' => false,
                'allows_return' => false,
                'open_time' => null,
                'close_time' => null,
                'reason' => 'Special closure.',
            ]
        );
    }
}
