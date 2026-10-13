<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\AuditEvent;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\OperationalWeeklySchedule;
use App\Models\OrganizationalUnit;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\CustodyService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the "Pickup Missed - For SPMU Review" workflow: a missed
 * pickup is NEVER automatically cancelled. It stays PREPARING_RELEASE /
 * PICKUP_EXPIRED indefinitely until an authorized SPMU actor explicitly
 * chooses Continue Release (CustodyService::rescheduleMissedPickup(), bounded
 * by the approved Items Needed From date) or Cancel Borrowing. Both missed-
 * pickup decisions are operational Action Officer actions; cancellation reuses
 * RequestWorkflowService::cancel() without a second Head/Admin approval. It
 * also covers the
 * two server-side guards that close a direct-POST bypass of the removed
 * borrower UI (a borrower can no longer request a reschedule or cancel a
 * request once pickup is missed - that decision belongs to SPMU alone).
 *
 * Two anchor date schemes are used:
 * - Monday 2026-09-21 09:00, with a forced Mon-Fri 08:00-17:00 Pickup /
 *   Release window, for the general detection/no-auto-cancel/server-side
 *   guard coverage.
 * - Friday 2026-10-09 (the last open weekday before Monday 2026-10-12) for
 *   the Items Needed From boundary coverage, matching the approved
 *   implementation plan's worked example exactly (2026-10-10/11 are the
 *   intervening closed Sat/Sun days).
 */
class PickupAutoCancellationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        OperationalWeeklySchedule::query()
            ->whereIn('weekday', [1, 2, 3, 4, 5])
            ->update([
                'is_open' => true,
                'allows_pickup' => true,
                'open_time' => '08:00',
                'close_time' => '17:00',
            ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 21, 9));

        $this->unit = OrganizationalUnit::query()->create([
            'unit_code' => 'PICKUPFX',
            'unit_name' => 'Pickup SPMU Review Test Unit',
            'unit_type' => 'OFFICE',
            'active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // No automatic cancellation, ever
    // ------------------------------------------------------------------

    public function test_a_missed_pickup_is_never_automatically_cancelled_no_matter_how_much_time_passes(): void
    {
        [$custody, , $request, $version] = $this->preparingReleaseCustody(
            'NOAUTO',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));
        app(CustodyService::class)->expirePickupWindows();

        $this->assertNotNull($custody->fresh()->pickup_expired_at);
        $this->assertSame('PICKUP_EXPIRED', $custody->fresh()->workflowStatus()['key']);

        // Travel far past every deadline the old auto-cancel logic used to
        // act on (original response deadline, the approved Expected Return
        // Date, and well beyond) and re-run the scheduler's detection step
        // repeatedly - nothing ever cancels.
        foreach ([
            Carbon::create(2026, 9, 23, 9),
            Carbon::create(2026, 9, 26, 9),
            Carbon::create(2026, 12, 1, 9),
        ] as $laterMoment) {
            $this->travelTo($laterMoment);
            app(CustodyService::class)->expirePickupWindows();

            $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
            $this->assertSame('PREPARING_RELEASE', $custody->fresh()->status);
            $this->assertNull($custody->fresh()->closed_at);

            $this->assertDatabaseMissing('audit_events', [
                'record_type' => CustodyTransaction::class,
                'record_id' => $custody->id,
                'action_code' => 'PICKUP_AUTO_CANCELLED_UNCLAIMED',
            ]);
        }

        // Reserved stock stays held throughout - never restored.
        $this->assertAllocationStillActiveAndUnrestored($version);
    }

    public function test_successful_physical_release_before_the_window_closes_is_never_marked_pickup_expired(): void
    {
        [$custody] = $this->preparingReleaseCustody(
            'RELEASEDINTIME',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $custody->update(['released_at' => Carbon::create(2026, 9, 21, 10)]);

        $this->travelTo(Carbon::create(2026, 9, 22, 9));
        $expired = app(CustodyService::class)->expirePickupWindows();

        $this->assertSame(0, $expired);
        $this->assertNull($custody->fresh()->pickup_expired_at);
    }

    public function test_preparation_exception_pending_release_protects_the_reservation(): void
    {
        [$custody, $line, $request] = $this->preparingReleaseCustody(
            'PREPISSUE',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 22, 23, 59, 59)
        );

        AuditEvent::query()->create([
            'record_type' => CustodyTransaction::class,
            'record_id' => $custody->id,
            'actor_user_id' => null,
            'action_code' => 'PREPARATION_ISSUE_REPORTED',
            'reason' => 'Reported inventory discrepancy.',
            'before_json' => [],
            'after_json' => ['custody_line_id' => $line->id],
            'occurred_at' => Carbon::create(2026, 9, 21, 10),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->travelTo(Carbon::create(2026, 9, 24, 9, 0));
        $expired = app(CustodyService::class)->expirePickupWindows();

        $this->assertSame(0, $expired);
        $this->assertNull($custody->fresh()->pickup_expired_at);
        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $this->assertSame('PREPARING_RELEASE', $custody->fresh()->status);

        $this->assertDatabaseHas('audit_events', [
            'record_type' => CustodyTransaction::class,
            'record_id' => $custody->id,
            'action_code' => 'PICKUP_HELD_FOR_PREPARATION_ISSUE',
        ]);
    }

    public function test_pickup_expired_notification_uses_neutral_spmu_review_wording(): void
    {
        [$custody, , , , $borrower] = $this->preparingReleaseCustody(
            'EXPNOTIFY',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));

        $expired = app(CustodyService::class)->expirePickupWindows();

        $this->assertSame(1, $expired);
        $this->assertNotNull($custody->fresh()->pickup_expired_at);

        $this->assertDatabaseHas('audit_events', [
            'record_type' => CustodyTransaction::class,
            'record_id' => $custody->id,
            'action_code' => 'PICKUP_WINDOW_EXPIRED',
        ]);

        $event = NotificationEvent::query()
            ->where('event_code', 'PICKUP_EXPIRED')
            ->where('source_type', $custody->getMorphClass())
            ->where('source_id', $custody->id)
            ->firstOrFail();

        $message = (string) $event->payload_snapshot_json['message'];
        $this->assertStringContainsString('SPMU is reviewing', $message);
        $this->assertStringContainsString('No action is required from you', $message);
        $this->assertStringNotContainsString('Request Reschedule', $message);
        $this->assertStringNotContainsString('Cancel Request', $message);
        $this->assertStringNotContainsString('cancelled automatically', $message);

        $deliveries = NotificationDelivery::query()
            ->where('notification_event_id', $event->id)
            ->where('recipient_user_id', $borrower->id)
            ->get();

        $channels = $deliveries->pluck('channel')->unique()->sort()->values()->all();
        $this->assertSame(['EMAIL', 'SYSTEM'], $channels);
    }

    public function test_repaired_pickup_expired_notification_uses_the_same_neutral_spmu_review_wording(): void
    {
        [$custody, , , , $borrower] = $this->preparingReleaseCustody(
            'REPAIREXPNOTIFY',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        // Simulate an older already-expired transaction whose original
        // PICKUP_EXPIRED notification event is missing. The repair path runs
        // before new expirations and must recreate the same neutral copy.
        $custody->update([
            'pickup_expired_at' => Carbon::create(2026, 9, 21, 18),
        ]);

        $this->assertDatabaseMissing('notification_events', [
            'event_code' => 'PICKUP_EXPIRED',
            'source_type' => $custody->getMorphClass(),
            'source_id' => $custody->id,
        ]);

        $this->travelTo(Carbon::create(2026, 9, 22, 9));
        $expired = app(CustodyService::class)->expirePickupWindows();

        // It was already marked expired, so this invocation only repairs the
        // missing notification and does not count another expiration.
        $this->assertSame(0, $expired);

        $event = NotificationEvent::query()
            ->where('event_code', 'PICKUP_EXPIRED')
            ->where('source_type', $custody->getMorphClass())
            ->where('source_id', $custody->id)
            ->latest('id')
            ->firstOrFail();

        $message = (string) $event->payload_snapshot_json['message'];
        $this->assertStringContainsString('SPMU is reviewing', $message);
        $this->assertStringContainsString('No action is required from you', $message);
        $this->assertStringNotContainsString('Request Reschedule', $message);
        $this->assertStringNotContainsString('Cancel Request', $message);
        $this->assertStringNotContainsString('cancelled automatically', $message);

        $channels = NotificationDelivery::query()
            ->where('notification_event_id', $event->id)
            ->where('recipient_user_id', $borrower->id)
            ->pluck('channel')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['EMAIL', 'SYSTEM'], $channels);
    }

    // ------------------------------------------------------------------
    // Server-side bypass guards (§2.2b / §2.3a)
    // ------------------------------------------------------------------

    public function test_borrower_direct_post_to_request_pickup_reschedule_is_rejected_once_pickup_is_missed(): void
    {
        [$custody, , , , $borrower] = $this->preparingReleaseCustody(
            'BYPASSRESCHED',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));
        app(CustodyService::class)->expirePickupWindows();

        $before = $custody->fresh();

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($borrower)
            ->post(route('custody.request-pickup-reschedule', $custody))
            ->assertSessionHasErrors(['pickup']);

        $this->assertDatabaseMissing('notification_events', [
            'event_code' => 'PICKUP_RESCHEDULE_REQUESTED',
            'source_type' => $custody->getMorphClass(),
            'source_id' => $custody->id,
        ]);

        $after = $custody->fresh();
        $this->assertEquals($before->pickup_expired_at, $after->pickup_expired_at);
        $this->assertEquals($before->scheduled_release_at, $after->scheduled_release_at);
        $this->assertEquals($before->pickup_expires_at, $after->pickup_expires_at);
    }

    public function test_borrower_direct_post_to_cancel_is_rejected_while_pickup_is_missed(): void
    {
        [$custody, , $request] = $this->preparingReleaseCustody(
            'BYPASSCANCEL',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));
        app(CustodyService::class)->expirePickupWindows();

        $this->withSession(['active_workspace' => 'BORROWER'])
            ->actingAs($request->borrower)
            ->post(route('requests.cancel', $request), ['reason' => 'I no longer need these items.'])
            ->assertSessionHasErrors(['cancel']);

        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $this->assertSame('PREPARING_RELEASE', $custody->fresh()->status);
        $this->assertDatabaseMissing('request_cancellations', [
            'request_id' => $request->id,
        ]);
        $this->assertAllocationStillActiveAndUnrestored($request->currentVersion);
    }

    public function test_spmu_head_cannot_replace_sao_cancellation_while_pickup_is_missed(): void
    {
        [$custody, , $request] = $this->preparingReleaseCustody(
            'HEADCANCELDURING',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuHead = User::query()->where('access_classification', AccessClassification::SpmuHead->value)->firstOrFail();

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuHead)
            ->post(route('requests.cancel', $request), ['reason' => 'SPMU decided the borrowing can no longer be fulfilled.'])
            ->assertForbidden();

        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $this->assertSame('PREPARING_RELEASE', $custody->fresh()->status);
        $this->assertDatabaseMissing('request_cancellations', [
            'request_id' => $request->id,
        ]);
        $this->assertAllocationStillActiveAndUnrestored($request->currentVersion);
    }

    public function test_regular_spmu_action_officer_can_cancel_a_missed_pickup_without_delegation(): void
    {
        [$custody, , $request] = $this->preparingReleaseCustody(
            'AOCANCELMISSED',
            Carbon::create(2026, 9, 21, 8),
            Carbon::create(2026, 9, 21, 17),
            Carbon::create(2026, 9, 25, 23, 59, 59)
        );

        $this->travelTo(Carbon::create(2026, 9, 21, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()
            ->where('access_classification', AccessClassification::SpmuOfficer->value)
            ->firstOrFail();

        $this->assertNull($spmuOfficer->activeDelegationFor('SPMU'));

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('requests.cancel', $request), [
                'reason' => 'Borrower missed pickup and SPMU can no longer proceed with physical issuance.',
                'return_to' => 'release',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame('CANCELLED', $custody->fresh()->status);
        $this->assertDatabaseHas('request_cancellations', [
            'request_id' => $request->id,
            'status' => 'CONFIRMED',
            'cancelled_by_user_id' => $spmuOfficer->id,
            'reviewed_by_user_id' => $spmuOfficer->id,
        ]);
        $this->assertAllocationFullyRestored($request->currentVersion);
    }

    // ------------------------------------------------------------------
    // Items Needed From boundary (§2.1a / §2.1b) - Fri Oct 9, 2026 missed,
    // needed_from Mon Oct 12, 2026 (Oct 10-11 are the intervening Sat/Sun).
    // ------------------------------------------------------------------

    public function test_spmu_can_continue_release_during_the_needed_from_days_valid_operating_window(): void
    {
        [$custody, , $request, $version] = $this->neededFromBoundaryCustody('CONTINUEOK');

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();
        $this->assertNotNull($custody->fresh()->pickup_expired_at);

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->travelTo(Carbon::create(2026, 10, 12, 10));
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasNoErrors();

        $fresh = $custody->fresh();
        $this->assertNull($fresh->pickup_expired_at);
        $this->assertTrue($fresh->scheduled_release_at->isSameDay(Carbon::create(2026, 10, 12)));
        $this->assertDatabaseHas('audit_events', [
            'record_type' => CustodyTransaction::class,
            'record_id' => $custody->id,
            'action_code' => 'PICKUP_RESCHEDULED',
        ]);

        // Approved items/quantities/dates are untouched - only the pickup
        // schedule timestamps changed.
        $requestItem = RequestItem::query()->where('request_version_id', $version->id)->firstOrFail();
        $this->assertEquals(1, (float) $requestItem->approved_quantity);
        $this->assertTrue($version->fresh()->needed_from->isSameDay(Carbon::create(2026, 10, 12)));
        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
    }

    public function test_continue_release_is_rejected_after_the_needed_from_days_operating_window_closes(): void
    {
        [$custody] = $this->neededFromBoundaryCustody('CONTINUELATE');

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        // After Oct 12's 17:00 close - the next valid window can only be
        // Oct 13 or later, which is after needed_from.
        $this->travelTo(Carbon::create(2026, 10, 12, 18));
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasErrors(['pickup']);

        $fresh = $custody->fresh();
        $this->assertNotNull($fresh->pickup_expired_at);
        $this->assertSame('PREPARING_RELEASE', $fresh->status);
        $this->assertNull($fresh->closed_at);
        $this->assertDatabaseMissing('audit_events', [
            'record_type' => CustodyTransaction::class,
            'record_id' => $custody->id,
            'action_code' => 'PICKUP_RESCHEDULED',
        ]);
    }

    public function test_continue_release_is_rejected_on_or_after_the_day_following_needed_from(): void
    {
        [$custody] = $this->neededFromBoundaryCustody('CONTINUENEXTDAY');

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->travelTo(Carbon::create(2026, 10, 13, 9));
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasErrors(['pickup']);

        $this->assertNotNull($custody->fresh()->pickup_expired_at);
    }

    public function test_continue_release_is_rejected_when_needed_from_itself_has_no_valid_operating_window(): void
    {
        // needed_from falls on a closed Saturday - no open PICKUP day can
        // ever be "on or before" it.
        [$custody] = $this->neededFromBoundaryCustody(
            'CONTINUENOWINDOW',
            neededFrom: Carbon::create(2026, 10, 10)
        );

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->travelTo(Carbon::create(2026, 10, 9, 19));
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasErrors(['pickup']);

        $this->assertNotNull($custody->fresh()->pickup_expired_at);
    }

    public function test_after_a_boundary_rejected_continue_release_spmu_can_still_explicitly_cancel(): void
    {
        [$custody, , $request] = $this->neededFromBoundaryCustody('CONTINUETHENCANCEL');

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->travelTo(Carbon::create(2026, 10, 13, 9));
        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasErrors(['pickup']);

        // Rejection alone never cancels or restores inventory.
        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $this->assertAllocationStillActiveAndUnrestored($request->currentVersion);

        $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->post(route('requests.cancel', $request), [
                'reason' => 'No valid pickup window remains; the borrowing can no longer be fulfilled.',
                'return_to' => 'release',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame('CANCELLED', $custody->fresh()->status);
        $this->assertDatabaseHas('request_cancellations', ['request_id' => $request->id]);
        $this->assertAllocationFullyRestored($request->currentVersion);
    }

    public function test_continue_release_control_is_absent_once_the_needed_from_boundary_has_passed(): void
    {
        [$custody] = $this->neededFromBoundaryCustody('CONTINUEUINOTOFFERED');

        $this->travelTo(Carbon::create(2026, 10, 9, 18));
        app(CustodyService::class)->expirePickupWindows();

        $spmuOfficer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->travelTo(Carbon::create(2026, 10, 13, 9));
        $response = $this->withSession(['active_workspace' => 'SPMU'])
            ->actingAs($spmuOfficer)
            ->get(route('custody.release.show', $custody));

        $response->assertOk();
        $response->assertDontSee('Continue Release');
        $response->assertSee('Cancel Borrowing');
        $response->assertSee('This approved release can no longer be continued', false);
    }

    // ------------------------------------------------------------------
    // Fixtures / assertion helpers
    // ------------------------------------------------------------------

    /**
     * @return array{0:CustodyTransaction,1:CustodyLine,2:BorrowingRequest,3:RequestVersion,4:User}
     */
    private function preparingReleaseCustody(
        string $suffix,
        Carbon $pickupStart,
        Carbon $pickupEnd,
        Carbon $dueAt,
        ?Carbon $neededFrom = null
    ): array {
        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'PICKUPFX'],
            ['category_name' => 'Pickup SPMU Review Fixture', 'active' => true]
        );
        $measure = UnitOfMeasure::query()->firstOrCreate(
            ['unit_code' => 'PC'],
            ['unit_name' => 'Piece', 'active' => true]
        );
        $item = InventoryItem::query()->firstOrCreate(
            ['unique_description' => 'Pickup Fixture Item '.$suffix],
            [
                'category_id' => $category->id,
                'unit_id' => $measure->id,
                'total_quantity' => 10,
                'condition_code' => 'SERVICEABLE',
                'borrowable' => true,
                'off_campus_allowed' => false,
                'laundry_required' => false,
                'provisional' => false,
                'active' => true,
            ]
        );

        $borrower = User::factory()->create([
            'access_classification' => AccessClassification::BorrowerOnly,
            'organizational_unit_id' => $this->unit->id,
        ]);

        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-PICKUP-'.$suffix,
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $this->unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);

        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => 'Pickup SPMU review fixture',
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => 'Test Office',
            'schedule_date' => $pickupStart->toDateString(),
            'return_date' => $dueAt->toDateString(),
            'needed_from' => ($neededFrom ?: $pickupStart)->copy(),
            'return_due_at' => $dueAt->copy(),
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description,
            'unit_snapshot' => 'Piece',
            'requested_quantity' => 1,
            'approved_quantity' => 1,
        ]);

        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => $pickupStart->copy(),
            'period_end' => $dueAt->copy(),
            'allocated_quantity' => 1,
            'released_quantity' => 0,
            'restored_quantity' => 0,
            'status' => 'ACTIVE',
            'allocated_at' => now(),
        ]);

        $custody = CustodyTransaction::query()->create([
            'custody_no' => 'CUS-PICKUP-'.$suffix,
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id,
            'status' => 'PREPARING_RELEASE',
            'scheduled_release_at' => $pickupStart,
            'pickup_expires_at' => $pickupEnd,
            'pickup_expired_at' => null,
            'pickup_scheduled_at' => $pickupStart,
            'due_at' => $dueAt,
            'original_due_at' => $dueAt,
        ]);

        $line = CustodyLine::query()->create([
            'custody_transaction_id' => $custody->id,
            'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id,
            'approved_quantity' => 1,
            'quantity_to_receive' => 1,
            'actual_released_quantity' => 0,
            'returned_quantity' => 0,
        ]);

        return [$custody->fresh(), $line, $request, $version, $borrower];
    }

    /**
     * Original pickup Friday 2026-10-09, needed_from Monday 2026-10-12 by
     * default (the exact worked example in the approved implementation
     * plan) - a return date far enough out that it never itself becomes the
     * binding boundary.
     *
     * @return array{0:CustodyTransaction,1:CustodyLine,2:BorrowingRequest,3:RequestVersion,4:User}
     */
    private function neededFromBoundaryCustody(string $suffix, ?Carbon $neededFrom = null): array
    {
        return $this->preparingReleaseCustody(
            $suffix,
            Carbon::create(2026, 10, 9, 8),
            Carbon::create(2026, 10, 9, 17),
            Carbon::create(2026, 10, 30, 23, 59, 59),
            $neededFrom ?: Carbon::create(2026, 10, 12)
        );
    }

    private function assertAllocationFullyRestored(RequestVersion $version): void
    {
        $requestItem = RequestItem::query()->where('request_version_id', $version->id)->firstOrFail();
        $allocation = Allocation::query()->where('request_item_id', $requestItem->id)->firstOrFail();

        $this->assertSame('CANCELLED', $allocation->status);
        $this->assertEquals((float) $allocation->allocated_quantity, (float) $allocation->restored_quantity);
    }

    private function assertAllocationStillActiveAndUnrestored(RequestVersion $version): void
    {
        $requestItem = RequestItem::query()->where('request_version_id', $version->id)->firstOrFail();
        $allocation = Allocation::query()->where('request_item_id', $requestItem->id)->firstOrFail();

        $this->assertSame('ACTIVE', $allocation->status);
        $this->assertEquals(0.0, (float) $allocation->restored_quantity);
    }
}
