<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\Allocation;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\InventoryItem;
use App\Models\OverdueCase;
use App\Models\ReturnTransaction;
use App\Models\User;
use App\Support\ActionOfficerStatusPresentation;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActionOfficerStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(12, 0));
        $this->seed(DatabaseSeeder::class);
    }

    #[DataProvider('pickupStates')]
    public function test_release_filter_groups_preparation_but_preserves_pickup_timing(string $state, string $expected): void
    {
        $custody = $this->custody('PREPARING_RELEASE', false);
        $changes = match ($state) {
            'no_schedule' => [],
            'unprepared' => ['scheduled_release_at' => now()->subHour(), 'pickup_expires_at' => now()->addHour()],
            'scheduled' => ['prepared_at' => now(), 'scheduled_release_at' => now()->addHour(), 'pickup_expires_at' => now()->addHours(2)],
            'ready' => ['prepared_at' => now(), 'scheduled_release_at' => now()->subHour(), 'pickup_expires_at' => now()->addHour()],
            'missed' => ['prepared_at' => now()->subDay(), 'scheduled_release_at' => now()->subHours(2), 'pickup_expires_at' => now()->subHour()],
        };
        $custody->update($changes);
        $before = $this->snapshot();
        $response = $this->officerRequest(route('custody.release.index'));
        $response->assertOk()->assertSee($expected)
            ->assertDontSee('value="PICKUP_SCHEDULING"', false)
            ->assertDontSee('value="ITEM_PREPARATION"', false)
            ->assertDontSee('value="PREPARING_RELEASE"', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(5, $xpath->query('//select[@id="operational-status"]/option')->length);
        $row = $xpath->query('//*[@data-operational-record]')->item(0);
        $this->assertStringContainsString($expected, $row->textContent);
        $this->assertSame($before, $this->snapshot());
    }

    public static function pickupStates(): array
    {
        return [
            ['no_schedule', 'For Preparation'], ['unprepared', 'For Preparation'],
            ['scheduled', 'Pickup Scheduled'], ['ready', 'Ready for Release'], ['missed', 'Pickup Missed'],
        ];
    }

    public function test_review_category_retains_the_actual_reviewer_without_changing_the_review_steps(): void
    {
        $borrower = User::where('access_classification', AccessClassification::BorrowerOnly->value)->firstOrFail();
        $request = BorrowingRequest::create([
            'request_no' => 'BR-REVIEW-'.uniqid(), 'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id, 'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);
        $version = $request->versions()->create([
            'version_no' => 1, 'purpose_event' => 'Review stage', 'location' => 'Off-campus venue',
            'schedule_date' => now()->addDay()->toDateString(), 'return_date' => now()->addDays(2)->toDateString(),
            'needed_from' => now()->addDay(), 'return_due_at' => now()->addDays(2)->endOfDay(),
            'off_campus' => true, 'created_by_user_id' => $borrower->id,
        ]);
        $step = $version->approvalSteps()->create(['sequence_no' => 1, 'stage_code' => 'SPMU', 'decision' => 'PENDING']);
        $version->approvalSteps()->create(['sequence_no' => 2, 'stage_code' => 'SPMU', 'decision' => 'PENDING']);
        $pending = ActionOfficerStatusPresentation::forRequest($request->fresh());
        $this->assertSame('UNDER_REVIEW', $pending['filter']);
        $this->assertSame('For Verification', $pending['label']);
        $step->update(['decision' => 'VERIFIED']);
        $verified = ActionOfficerStatusPresentation::forRequest($request->fresh());
        $this->assertSame('UNDER_REVIEW', $verified['filter']);
        $this->assertSame('For Approval', $verified['label']);
        $this->assertSame(['VERIFIED', 'PENDING'], $version->approvalSteps()->orderBy('sequence_no')->pluck('decision')->all());
    }

    public function test_accountability_group_keeps_property_action_and_simultaneous_late_return_visible(): void
    {
        $custody = $this->custody('OBLIGATION_OPEN', true, true);
        $this->incident($custody, 'COMPLIANCE_REQUIRED', 'DAMAGED', 'REPAIR');
        $this->lateCase($custody, 'FOR_HEAD_APPROVAL');
        $before = $this->snapshot();
        $response = $this->officerRequest(route('requests.index'));
        $response->assertOk()->assertSee('For Compliance')->assertSee('Damage Finding')
            ->assertSee('Repair Required')->assertSee('For Late Return Approval')->assertSee('Returned Late');
        $this->assertRecord($response->getContent(), 'ACCOUNTABILITY_PROCESSING', 'COMPLIANCE_REQUIRED', true);
        $response->assertDontSee('value="SLDDP"', false)->assertDontSee('value="RETURNED_LATE"', false);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_overdue_is_not_mistaken_for_a_completed_late_return_or_hidden_by_a_property_case(): void
    {
        $custody = $this->custody('OVERDUE');
        $this->incident($custody, 'COMPLIANCE_REQUIRED', 'LOST', 'REPLACEMENT');
        $this->lateCase($custody, 'OVERDUE');
        $response = $this->officerRequest(route('requests.index'));
        $response->assertOk()->assertSee('Overdue')->assertSee('Loss Finding')->assertSee('Replacement Required')
            ->assertSee('For Compliance')->assertDontSee('Returned Late');
        $this->assertRecord($response->getContent(), 'ON_CUSTODY', 'OVERDUE', true);
    }

    public function test_a_cleared_late_return_remains_completed_with_a_historical_late_indicator(): void
    {
        $custody = $this->custody('CLOSED', true, true);
        $this->lateCase($custody, 'RESOLVED');
        $response = $this->officerRequest(route('requests.index'));
        $response->assertOk()->assertSee('Returned Late');
        $this->assertRecord($response->getContent(), 'COMPLETED', 'COMPLETED', false);
    }

    public function test_payment_stage_retains_its_property_case_reason(): void
    {
        $custody = $this->custody('OBLIGATION_OPEN', true, true);
        $this->incident($custody, 'RSLDDP_PAYMENT_REQUIRED', 'LOST');
        $response = $this->officerRequest(route('requests.index'));
        $response->assertOk()->assertSee('For Payment')->assertSee('Loss Finding');
        $this->assertRecord($response->getContent(), 'ACCOUNTABILITY_PROCESSING', 'RSLDDP_PAYMENT_REQUIRED', true);
    }

    private function assertRecord(string $html, string $filter, string $status, bool $attention): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $row = $xpath->query('//tr[@data-request-record]')->item(0);
        $this->assertNotNull($row);
        $this->assertSame($filter, $row->getAttribute('data-status'));
        $this->assertSame($status, $row->getAttribute('data-status-key'));
        $this->assertSame($attention ? '1' : '0', $row->getAttribute('data-attention'));
        $this->assertSame(count(ActionOfficerStatusPresentation::filters()) + 2, $xpath->query('//select[@data-record-status-filter]/option')->length);
    }

    private function officerRequest(string $url)
    {
        $officer = User::where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();
        return $this->withSession(['active_workspace' => 'SPMU', 'last_user_activity_at' => now()->timestamp])->actingAs($officer)->get($url);
    }

    private function custody(string $status, bool $released = true, bool $returned = false): CustodyTransaction
    {
        $borrower = User::where('access_classification', AccessClassification::BorrowerOnly->value)->firstOrFail();
        $item = InventoryItem::where('laundry_required', false)->with('unit')->firstOrFail();
        $request = BorrowingRequest::create([
            'request_no' => 'BR-STATUS-'.uniqid(), 'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id, 'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease, 'final_approved_at' => now()->subDays(2),
        ]);
        $version = $request->versions()->create([
            'version_no' => 1, 'purpose_event' => 'Status review', 'location' => 'Campus',
            'schedule_date' => now()->subDays(2)->toDateString(), 'return_date' => now()->subDay()->toDateString(),
            'needed_from' => now()->subDays(2), 'return_due_at' => now()->subDay()->endOfDay(),
            'off_campus' => false, 'created_by_user_id' => $borrower->id,
        ]);
        $itemRecord = $version->items()->create([
            'inventory_item_id' => $item->id, 'description_snapshot' => $item->unique_description,
            'unit_snapshot' => $item->unit->unit_name, 'requested_quantity' => 1, 'approved_quantity' => 1,
            'use_location' => 'ON_CAMPUS',
        ]);
        $custody = CustodyTransaction::create([
            'custody_no' => 'CUS-STATUS-'.uniqid(), 'request_id' => $request->id, 'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id, 'status' => $status, 'due_at' => now()->subDay()->endOfDay(),
            'released_at' => $released ? now()->subDays(2) : null, 'closed_at' => $status === 'CLOSED' ? now() : null,
        ]);
        $allocation = Allocation::create([
            'request_item_id' => $itemRecord->id, 'period_start' => $version->needed_from,
            'period_end' => $version->return_due_at, 'allocated_quantity' => 1,
            'released_quantity' => $released ? 1 : 0, 'restored_quantity' => $returned ? 1 : 0,
            'status' => $released ? 'RELEASED' : 'ACTIVE', 'allocated_at' => now()->subDays(2),
        ]);
        CustodyLine::create([
            'custody_transaction_id' => $custody->id, 'request_item_id' => $itemRecord->id, 'allocation_id' => $allocation->id,
            'approved_quantity' => 1, 'quantity_to_receive' => 1, 'actual_released_quantity' => $released ? 1 : 0,
            'returned_quantity' => $returned ? 1 : 0,
        ]);
        if ($returned) {
            ReturnTransaction::create([
                'return_no' => 'RT-STATUS-'.uniqid(), 'custody_transaction_id' => $custody->id,
                'received_by_user_id' => User::where('access_classification', AccessClassification::SpmuOfficer->value)->value('id'),
                'return_type' => 'FULL', 'received_at' => now(), 'status' => 'RECORDED',
            ]);
        }
        return $custody;
    }

    private function incident(CustodyTransaction $custody, string $status, string $type, ?string $action = null): void
    {
        Incident::create([
            'incident_no' => 'INC-STATUS-'.uniqid(), 'custody_transaction_id' => $custody->id,
            'borrower_user_id' => $custody->borrower_user_id, 'reported_by_user_id' => $custody->borrower_user_id,
            'incident_type' => $type, 'reported_at' => now(), 'status' => $status, 'compliance_action' => $action,
        ]);
    }

    private function lateCase(CustodyTransaction $custody, string $status): void
    {
        OverdueCase::create([
            'custody_transaction_id' => $custody->id, 'borrower_user_id' => $custody->borrower_user_id,
            'grace_expires_at' => $custody->due_at, 'overdue_started_at' => $custody->due_at->copy()->addDay(),
            'offense_level' => 1, 'rate_snapshot' => 75, 'accrued_amount' => 75, 'status' => $status,
        ]);
    }

    private function snapshot(): array
    {
        return collect(['borrowing_requests', 'custody_transactions', 'custody_lines', 'return_transactions', 'incidents', 'overdue_cases', 'inventory_items'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }
}
