<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Allocation;
use App\Models\ApprovalStep;
use App\Models\AuditEvent;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\InventoryItem;
use App\Models\NotificationEvent;
use App\Models\OperationalDateException;
use App\Models\OperationalWeeklySchedule;
use App\Models\RequestItem;
use App\Models\RequestSupportingDocument;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\CustodyService;
use App\Services\RequestWorkflowService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Delayed approval versus a genuinely missed pickup.
 *
 * - Items Needed From is when the borrower needs the equipment.
 * - The suggested pickup date (last open day before it) is tentative: before
 *   approval nothing is allocated, scheduled or communicated.
 * - A confirmed pickup schedule exists only after approval and allocation.
 * - "Pickup Missed" requires a confirmed schedule whose window expired.
 *
 * The week used: Monday 2026-09-21 to Friday 2026-09-25, open 6:00 AM to
 * 11:59 PM as configured in the Operational Calendar, weekends closed.
 */
class DelayedApprovalPickupTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;
    private User $head;
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        foreach (range(1, 7) as $weekday) {
            $open = $weekday <= 5;
            OperationalWeeklySchedule::query()->updateOrCreate(['weekday' => $weekday], [
                'is_open' => $open, 'accepts_requests' => $open, 'allows_pickup' => $open, 'allows_return' => $open,
                'open_time' => $open ? '06:00' : null, 'close_time' => $open ? '23:59' : null,
            ]);
        }

        $this->travelTo(Carbon::create(2026, 9, 21, 9, 0)); // Monday

        $this->borrower = User::query()->whereHas('roles', fn ($query) => $query
            ->where('role_code', UserRole::Borrower->value)->whereNull('user_roles.revoked_at'))->firstOrFail();
        $this->head = User::query()->where('access_classification', AccessClassification::SpmuHead->value)->firstOrFail();
        $this->officer = User::query()->where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        foreach ([$this->head, $this->officer] as $user) {
            $this->registerSignature($user);
        }
    }

    // ------------------------------------------------------------------
    // 1. Approval before the suggested pickup date
    // ------------------------------------------------------------------

    public function test_approval_before_the_suggested_pickup_date_confirms_the_calendar_window(): void
    {
        $request = $this->submittedRequest('DA-EARLY', '2026-09-23'); // needed Wednesday

        $this->approveAt($request, Carbon::create(2026, 9, 21, 10, 0)); // Monday

        $custody = $this->custody($request);
        // Tuesday is the last open day before Items Needed From; hours come from the calendar.
        $this->assertSame('2026-09-22 06:00', $custody->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-22 23:59', $custody->pickup_expires_at->format('Y-m-d H:i'));
        $this->assertNotNull($custody->pickup_scheduled_at);
        $this->assertNull($custody->pickup_expired_at);
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));
        $this->assertSame(0, $this->auditCount($custody, 'PICKUP_SCHEDULE_REVIEW_REQUIRED'));
        $this->assertStringContainsString('Pickup is scheduled for September 22, 2026 6:00 AM to 11:59 PM', $this->borrowerMessage('REQUEST_APPROVED', $request));
    }

    // ------------------------------------------------------------------
    // 2. Approval delayed until Items Needed From
    // ------------------------------------------------------------------

    public function test_approval_on_items_needed_from_requires_schedule_review_and_is_not_a_missed_pickup(): void
    {
        $request = $this->submittedRequest('DA-SAMEDAY', '2026-09-22'); // needed Tuesday, suggested pickup Monday

        $this->approveAt($request, Carbon::create(2026, 9, 22, 9, 0)); // approved Tuesday

        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $custody = $this->custody($request);

        // No pickup schedule was created, confirmed or expired.
        $this->assertNull($custody->scheduled_release_at);
        $this->assertNull($custody->pickup_expires_at);
        $this->assertNull($custody->pickup_scheduled_at);
        $this->assertNull($custody->pickup_expired_at);
        $this->assertSame('PICKUP_SCHEDULING', $custody->workflowStatus()['key']);
        $this->assertTrue(app(CustodyService::class)->pickupScheduleReviewPending($custody));

        $review = AuditEvent::query()->where('record_type', CustodyTransaction::class)
            ->where('record_id', $custody->id)->where('action_code', 'PICKUP_SCHEDULE_REVIEW_REQUIRED')->sole();
        $this->assertSame('DELAYED_APPROVAL', $review->after_json['reason']);
        $this->assertSame('2026-09-21', $review->after_json['original_suggested_pickup_date']);
        $this->assertSame('2026-09-22', $review->after_json['items_needed_from']);
        $this->assertFalse($review->after_json['borrower_missed_pickup']);

        // Items Needed From itself is unchanged, and stock is reserved.
        $this->assertSame('2026-09-22', $request->fresh()->currentVersion->schedule_date->toDateString());
        $this->assertSame('ACTIVE', $this->allocation($request)->status);

        // The borrower is told no schedule exists yet - not given a pickup time.
        $message = $this->borrowerMessage('REQUEST_APPROVED', $request);
        $this->assertStringContainsString('no pickup schedule has been set yet', $message);
        $this->assertStringContainsString('not recorded as a missed pickup', $message);
        $this->assertStringNotContainsString('Pickup is scheduled for', $message);

        // The existing card shows the review state to the Action Officer.
        $this->releasePage($custody)
            ->assertSee('Pickup Schedule Needs Attention')
            ->assertSee('Schedule Review Required')
            ->assertSee('The original pickup schedule passed before the request was approved. Confirm whether the borrower still needs the equipment before proceeding.')
            ->assertSee('Suggested pickup: September 21, 2026.')
            ->assertSee('September 22, 2026 9:00 AM to 11:59 PM')
            ->assertSee('I contacted the borrower and they confirmed they still need the equipment.')
            ->assertSee('Continue Release')
            ->assertDontSee('Pickup Missed')
            ->assertDontSee('Set Next Valid Pickup Schedule');
    }

    // ------------------------------------------------------------------
    // 3. Approval after Items Needed From
    // ------------------------------------------------------------------

    public function test_approval_after_items_needed_from_is_refused_without_marking_anything_missed(): void
    {
        $request = $this->submittedRequest('DA-LATE', '2026-09-22'); // needed Tuesday

        try {
            $this->approveAt($request, Carbon::create(2026, 9, 23, 9, 0)); // Wednesday
            $this->fail('A request whose Items Needed From date has passed was approved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup', $exception->errors());
            $this->assertStringContainsString('Please return the request for revision so the borrower can choose new dates.', $exception->errors()['pickup'][0]);
        }

        // Still awaiting a decision: the dates can be revised with the borrower.
        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);
        $this->assertNull($request->fresh()->custody);
        $this->assertSame(0, Allocation::query()->count());
        $this->assertNoMissedPickupOrPenalty($request);
    }

    // ------------------------------------------------------------------
    // 4. Tentative pickup date passes while approval is pending
    // ------------------------------------------------------------------

    public function test_suggested_pickup_date_passing_before_approval_changes_nothing(): void
    {
        $request = $this->submittedRequest('DA-PENDING', '2026-09-22'); // suggested pickup Monday

        foreach ([Carbon::create(2026, 9, 22, 0, 10), Carbon::create(2026, 9, 22, 12, 0), Carbon::create(2026, 9, 25, 9, 0)] as $moment) {
            $this->travelTo($moment);
            $this->assertSame(0, app(CustodyService::class)->expirePickupWindows());
            $this->artisan('spmu:process-deadlines')->assertSuccessful();
        }

        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);
        $this->assertNull($request->fresh()->custody);
        $this->assertNoMissedPickupOrPenalty($request);
    }

    // ------------------------------------------------------------------
    // 5. A genuinely confirmed pickup is missed
    // ------------------------------------------------------------------

    public function test_confirmed_pickup_window_that_expires_is_still_pickup_missed(): void
    {
        $request = $this->submittedRequest('DA-MISSED', '2026-09-23');
        $this->approveAt($request, Carbon::create(2026, 9, 21, 10, 0));
        $custody = $this->custody($request);

        $this->travelTo(Carbon::create(2026, 9, 23, 0, 5)); // Tuesday's confirmed window is over
        $this->assertSame(1, app(CustodyService::class)->expirePickupWindows());

        $custody->refresh();
        $this->assertNotNull($custody->pickup_expired_at);
        $this->assertSame('PICKUP_EXPIRED', $custody->workflowStatus()['key']);
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));
        $this->assertSame(1, NotificationEvent::query()->where('event_code', 'PICKUP_EXPIRED')->count());

        $this->releasePage($custody)
            ->assertSee('Pickup Missed')
            ->assertSee('The scheduled pickup was not completed. Decide whether to continue this approved release or cancel the borrowing.')
            ->assertSee('Continue Release')
            ->assertDontSee('Schedule Review Required')
            ->assertDontSee('I contacted the borrower');

        // The existing missed-pickup Continue Release needs no attestation.
        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody))->assertSessionHasNoErrors();
        $this->assertSame('MISSED_PICKUP_NEXT_VALID_OPERATIONAL_WINDOW', $this->lastAudit($custody, 'PICKUP_RESCHEDULED')->after_json['source']);
    }

    // ------------------------------------------------------------------
    // 6. Same-day pickup when operationally feasible
    // ------------------------------------------------------------------

    public function test_continue_release_needs_the_officer_attestation_then_schedules_a_same_day_window(): void
    {
        $request = $this->submittedRequest('DA-CONTINUE', '2026-09-22');
        $this->approveAt($request, Carbon::create(2026, 9, 22, 9, 0));
        $custody = $this->custody($request);
        $this->travelTo(Carbon::create(2026, 9, 22, 10, 30));

        // Without the attestation nothing is scheduled.
        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody))
            ->assertSessionHasErrors('borrower_need_confirmed');
        $this->assertNull($custody->fresh()->scheduled_release_at);
        $this->assertSame(0, $this->auditCount($custody, 'PICKUP_RESCHEDULED'));
        $this->assertSame(0, NotificationEvent::query()->where('event_code', 'PICKUP_SCHEDULED')->count());

        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody), ['borrower_need_confirmed' => '1', 'borrower_confirmation_note' => '   '])
            ->assertSessionHasErrors('borrower_confirmation_note');
        $this->assertNull($custody->fresh()->scheduled_release_at);

        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody), ['borrower_need_confirmed' => '1', 'borrower_confirmation_note' => 'Phone call, 10:25 AM'])
            ->assertSessionHasNoErrors();

        $custody->refresh();
        // Same day, from now until the calendar's configured closing time.
        $this->assertSame('2026-09-22 10:30', $custody->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-22 23:59', $custody->pickup_expires_at->format('Y-m-d H:i'));
        $this->assertSame($this->officer->id, $custody->pickup_scheduled_by_user_id);
        $this->assertNotNull($custody->pickup_scheduled_at);
        $this->assertNull($custody->released_at, 'Scheduling never releases the items.');
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));

        $audit = $this->lastAudit($custody, 'PICKUP_RESCHEDULED')->after_json;
        $this->assertSame('DELAYED_APPROVAL_SCHEDULE_REVIEW', $audit['source']);
        $this->assertTrue($audit['borrower_need_attested_by_spmu']);
        $this->assertSame($this->officer->id, $audit['attested_by_user_id']);
        $this->assertSame($this->officer->full_name, $audit['attested_by_name']);
        $this->assertSame('2026-09-22T10:30:00+08:00', $audit['attested_at']);
        $this->assertSame('I contacted the borrower and they confirmed they still need the equipment.', $audit['attestation_statement']);
        $this->assertSame('Phone call, 10:25 AM', $audit['borrower_confirmation_note']);
        $this->assertSame('SPMU_OFFICER_ATTESTATION', $audit['confirmation_source']);
        $this->assertFalse($audit['borrower_confirmation_recorded_in_system']);
        $event = $this->lastAudit($custody, 'PICKUP_RESCHEDULED');
        $this->assertSame($this->officer->id, $event->actor_user_id);
        $this->assertSame('2026-09-22 10:30:00', $event->occurred_at->format('Y-m-d H:i:s'));

        $message = $this->borrowerMessage('PICKUP_SCHEDULED', $custody);
        $this->assertStringContainsString('September 22, 2026 10:30 AM until 11:59 PM', $message);
        // The borrower is told it is an officer's record, and how to dispute it.
        $this->assertStringContainsString('An SPMU officer recorded that you were contacted and confirmed you still need the items.', $message);
        $this->assertStringContainsString('If that record is not correct, or you no longer need the items, contact SPMU', $message);

        // Now that a schedule was confirmed and communicated, missing it is a genuine missed pickup.
        $this->travelTo(Carbon::create(2026, 9, 23, 0, 5));
        $this->assertSame(1, app(CustodyService::class)->expirePickupWindows());
        $this->assertSame('PICKUP_EXPIRED', $custody->fresh()->workflowStatus()['key']);
    }

    public function test_same_day_pickup_is_not_offered_once_the_configured_hours_have_ended(): void
    {
        OperationalWeeklySchedule::query()->where('weekday', 2)->update(['close_time' => '15:00']);
        $request = $this->submittedRequest('DA-HOURS', '2026-09-22');

        // Tuesday 4:00 PM: the configured window (6:00 AM to 3:00 PM) is over.
        try {
            $this->approveAt($request, Carbon::create(2026, 9, 22, 16, 0));
            $this->fail('Approval proceeded although no pickup window remained.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup', $exception->errors());
        }
        $this->assertSame(RequestStatus::UnderSpmu, $request->fresh()->status);

        // Approved in time, but SPMU acts only after the hours ended: nothing can be scheduled.
        $other = $this->submittedRequest('DA-HOURS-2', '2026-09-22');
        $this->approveAt($other, Carbon::create(2026, 9, 22, 9, 0));
        $custody = $this->custody($other);
        $this->travelTo(Carbon::create(2026, 9, 22, 16, 0));

        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody), ['borrower_need_confirmed' => '1', 'borrower_confirmation_note' => 'Phone call, 10:25 AM'])
            ->assertSessionHasErrors('pickup');
        $this->assertNull($custody->fresh()->scheduled_release_at);
        $this->assertNull($custody->fresh()->pickup_expired_at);

        $this->releasePage($custody)
            ->assertSee('Schedule Review Required')
            ->assertSee('No valid pickup window remains on or before the Items Needed From date.')
            ->assertDontSee('Continue Release')
            ->assertDontSee('Pickup Missed');
    }

    // ------------------------------------------------------------------
    // 7. Special closure and special opening
    // ------------------------------------------------------------------

    public function test_special_closure_and_opening_are_respected_when_approval_is_delayed(): void
    {
        // Special closure on Items Needed From: no same-day pickup exists, so approval is refused.
        OperationalDateException::query()->create([
            'exception_date' => '2026-09-22', 'status' => 'CLOSED', 'accepts_requests' => false,
            'allows_pickup' => false, 'allows_return' => false, 'reason' => 'Typhoon signal',
        ]);
        $closed = $this->submittedRequest('DA-CLOSED', '2026-09-22');
        try {
            $this->approveAt($closed, Carbon::create(2026, 9, 22, 9, 0));
            $this->fail('Approval proceeded on a specially closed pickup day.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup', $exception->errors());
        }
        $this->assertNull($closed->fresh()->custody);

        // A closed suggested day is skipped: needed Friday, Thursday closed, approved Monday.
        // The suggestion falls back to Wednesday, which is still ahead, so it is confirmed.
        OperationalDateException::query()->create([
            'exception_date' => '2026-09-24', 'status' => 'CLOSED', 'accepts_requests' => false,
            'allows_pickup' => false, 'allows_return' => false, 'reason' => 'Maintenance',
        ]);
        $skipped = $this->submittedRequest('DA-SKIP', '2026-09-25');
        $this->approveAt($skipped, Carbon::create(2026, 9, 21, 10, 0));
        $this->assertSame('2026-09-23 06:00', $this->custody($skipped)->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-23 23:59', $this->custody($skipped)->pickup_expires_at->format('Y-m-d H:i'));

        // Special Saturday opening with its own hours: needed Monday, approved Saturday.
        OperationalDateException::query()->create([
            'exception_date' => '2026-09-26', 'status' => 'OPEN', 'accepts_requests' => true,
            'allows_pickup' => true, 'allows_return' => true, 'open_time' => '09:00', 'close_time' => '12:00',
            'reason' => 'Saturday service',
        ]);
        // Approved Friday: the special Saturday opening is the confirmed pickup, with its own hours.
        $opening = $this->submittedRequest('DA-OPENING', '2026-09-28');
        $this->approveAt($opening, Carbon::create(2026, 9, 25, 10, 0));
        $custody = $this->custody($opening);
        $this->assertSame('2026-09-26 09:00', $custody->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-26 12:00', $custody->pickup_expires_at->format('Y-m-d H:i'));
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));

        // Approved on the closed Sunday instead: Monday is still possible, so it goes to review.
        $sunday = $this->submittedRequest('DA-SUNDAY', '2026-09-28');
        $this->approveAt($sunday, Carbon::create(2026, 9, 27, 10, 0));
        $this->assertNull($this->custody($sunday)->scheduled_release_at);
        $this->assertTrue(app(CustodyService::class)->pickupScheduleReviewPending($this->custody($sunday)));
    }

    // ------------------------------------------------------------------
    // 8. No cancellation or penalty solely because approval was delayed
    // ------------------------------------------------------------------

    public function test_delayed_approval_alone_never_expires_cancels_or_penalises(): void
    {
        $request = $this->submittedRequest('DA-NOPENALTY', '2026-09-22');
        $this->approveAt($request, Carbon::create(2026, 9, 22, 9, 0));
        $custody = $this->custody($request);

        foreach ([Carbon::create(2026, 9, 23, 0, 5), Carbon::create(2026, 9, 26, 9, 0), Carbon::create(2026, 12, 1, 9, 0)] as $moment) {
            $this->travelTo($moment);
            $this->assertSame(0, app(CustodyService::class)->expirePickupWindows());
            $this->artisan('spmu:process-deadlines')->assertSuccessful();

            $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
            $this->assertSame('PREPARING_RELEASE', $custody->fresh()->status);
            $this->assertNull($custody->fresh()->pickup_expired_at);
            $this->assertNull($custody->fresh()->closed_at);
            $this->assertNotSame('PICKUP_EXPIRED', $custody->fresh()->workflowStatus()['key']);
        }

        $this->assertSame('ACTIVE', $this->allocation($request)->status);
        $this->assertNoMissedPickupOrPenalty($request);
    }

    // ------------------------------------------------------------------
    // 9. Continue Release and Cancel Borrowing authorization
    // ------------------------------------------------------------------

    public function test_only_the_existing_authorized_roles_may_continue_or_cancel_during_schedule_review(): void
    {
        $request = $this->submittedRequest('DA-AUTH', '2026-09-22');
        $this->approveAt($request, Carbon::create(2026, 9, 22, 9, 0));
        $custody = $this->custody($request);
        $attested = ['borrower_need_confirmed' => '1', 'borrower_confirmation_note' => 'Phone call, 10:25 AM'];

        // Continue Release stays an Action Officer action.
        $this->withSession(['active_workspace' => 'BORROWER'])->actingAs($this->borrower)
            ->post(route('custody.reschedule-pickup', $custody), $attested)->assertForbidden();
        $this->withSession(['active_workspace' => 'SPMU'])->actingAs($this->head)
            ->post(route('custody.reschedule-pickup', $custody), $attested)->assertForbidden();
        $this->assertNull($custody->fresh()->scheduled_release_at);

        // A plain Action Officer still cannot cancel outside a genuine missed pickup,
        // and is not offered the action.
        $this->assertNull($this->officer->activeDelegationFor('SPMU'));
        $this->asOfficer()->post(route('requests.cancel', $request), ['reason' => 'Borrower no longer needs it.', 'return_to' => 'release'])
            ->assertForbidden();
        $this->assertSame(RequestStatus::ApprovedReadyForRelease, $request->fresh()->status);
        $this->releasePage($custody)->assertSee('Continue Release')->assertDontSee('Cancel Borrowing');

        // The SPMU Head keeps the existing cancellation authority; stock is restored.
        $this->withSession(['active_workspace' => 'SPMU'])->actingAs($this->head)
            ->post(route('requests.cancel', $request), ['reason' => 'Borrower confirmed the equipment is no longer needed.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame('CANCELLED', $custody->fresh()->status);
        $this->assertNotSame('ACTIVE', $this->allocation($request)->status);
        $this->assertDatabaseHas('request_cancellations', ['request_id' => $request->id, 'reviewed_by_user_id' => $this->head->id]);
    }

    // ------------------------------------------------------------------
    // 10. Physical release and document safeguards
    // ------------------------------------------------------------------

    public function test_physical_release_stays_blocked_until_a_schedule_is_confirmed_and_documents_are_generated(): void
    {
        $request = $this->submittedRequest('DA-RELEASE', '2026-09-22');
        $this->approveAt($request, Carbon::create(2026, 9, 22, 9, 0));
        $custody = $this->custody($request)->load('lines');

        // The Borrower Slip is generated at approval as before.
        $this->assertDatabaseHas('generated_documents', [
            'subject_type' => CustodyTransaction::class, 'subject_id' => $custody->id, 'document_type' => 'BORROWER_SLIP',
        ]);

        $release = fn () => $this->asOfficer()->post(route('custody.release', $custody), [
            'physical_signatures_confirmed' => '1', 'remarks' => 'Attempted release.',
        ]);
        $prepare = fn () => $this->asOfficer()->post(route('custody.prepare', $custody), [
            'quantities' => $custody->lines->mapWithKeys(fn ($line) => [$line->id => (int) $line->approved_quantity])->all(),
        ]);

        // No schedule yet: nothing can be released, whatever the calendar says about today.
        $release()->assertSessionHasErrors();
        $this->assertNull($custody->fresh()->released_at);

        $this->asOfficer()->post(route('custody.reschedule-pickup', $custody), ['borrower_need_confirmed' => '1', 'borrower_confirmation_note' => 'Phone call, 10:25 AM'])
            ->assertSessionHasNoErrors();

        // A schedule alone is not enough: preparation is still required.
        $release()->assertSessionHasErrors();
        $this->assertNull($custody->fresh()->released_at);

        $prepare()->assertSessionHasNoErrors();

        // The physical-signature confirmation is still required.
        $this->asOfficer()->post(route('custody.release', $custody), ['remarks' => 'No signatures confirmed.'])
            ->assertSessionHasErrors();
        $this->assertNull($custody->fresh()->released_at);

        // Outside the confirmed window the release is refused.
        // (A new session each time: the clock jump would otherwise trip the idle-session logout.)
        $this->travelTo(Carbon::create(2026, 9, 23, 0, 5));
        $this->flushSession();
        $release()->assertSessionHasErrors();
        $this->assertNull($custody->fresh()->released_at);

        // Inside it, the existing Physical Release works.
        $this->travelTo(Carbon::create(2026, 9, 22, 14, 0));
        $this->flushSession();
        $release()->assertSessionHasNoErrors();
        $this->assertNotNull($custody->fresh()->released_at);
        $this->assertSame($this->officer->id, $custody->fresh()->released_by_user_id);
    }

    // ------------------------------------------------------------------
    // Late approval inside the suggested pickup day
    // ------------------------------------------------------------------

    public function test_approval_minutes_before_closing_automatically_activates_the_remaining_window(): void
    {
        OperationalWeeklySchedule::query()->where('weekday', '<=', 5)->update(['open_time' => '08:00', 'close_time' => '17:00']);
        $request = $this->submittedRequest('DA-LASTMINUTE', '2026-09-22'); // needed Tuesday, suggested pickup Monday

        $this->approveAt($request, Carbon::create(2026, 9, 21, 16, 50)); // ten minutes before closing

        $custody = $this->custody($request);
        $this->assertSame('2026-09-21 16:50', $custody->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-21 17:00', $custody->pickup_expires_at->format('Y-m-d H:i'));
        $this->assertNotNull($custody->pickup_scheduled_at);
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));
        $this->assertSame(0, $this->auditCount($custody, 'PICKUP_SCHEDULE_REVIEW_REQUIRED'));
        $this->assertStringContainsString('Pickup is scheduled for September 21, 2026 4:50 PM to 5:00 PM', $this->borrowerMessage('REQUEST_APPROVED', $request));
        $this->assertSame('2026-09-21', $this->allocation($request)->period_start->toDateString());

        $this->releasePage($custody)
            ->assertDontSee('Schedule Review Required')
            ->assertDontSee('I contacted the borrower');
    }

    public function test_approval_during_a_remaining_suggested_pickup_window_is_automatic(): void
    {
        $request = $this->submittedRequest('DA-MORNING', '2026-09-22');
        $this->approveAt($request, Carbon::create(2026, 9, 21, 9, 30));

        $custody = $this->custody($request);
        $this->assertSame('2026-09-21 09:30', $custody->scheduled_release_at->format('Y-m-d H:i'));
        $this->assertSame('2026-09-21 23:59', $custody->pickup_expires_at->format('Y-m-d H:i'));
        $this->assertNotNull($custody->pickup_scheduled_at);
        $this->assertFalse(app(CustodyService::class)->pickupScheduleReviewPending($custody));
        $this->assertSame(0, $this->auditCount($custody, 'PICKUP_SCHEDULE_REVIEW_REQUIRED'));
        $this->assertStringContainsString('Pickup is scheduled for September 21, 2026 9:30 AM to 11:59 PM', $this->borrowerMessage('REQUEST_APPROVED', $request));

        $this->releasePage($custody)
            ->assertDontSee('Schedule Review Required')
            ->assertDontSee('I contacted the borrower');
    }

    // ------------------------------------------------------------------
    // No dead ends
    // ------------------------------------------------------------------

    public function test_request_past_items_needed_from_can_be_returned_for_revision_and_given_new_dates(): void
    {
        $request = $this->submittedRequest('DA-REVISE', '2026-09-22');
        $this->travelTo(Carbon::create(2026, 9, 23, 9, 0)); // Items Needed From has passed, still undecided
        $this->actingAs($this->head);
        session(['active_workspace' => 'SPMU']);

        app(RequestWorkflowService::class)->decide(
            $request->fresh(), $this->head, 'RETURNED_FOR_REVISION', 'Items Needed From has passed. Please choose new dates.', false
        );

        $this->assertSame(RequestStatus::ReturnedForRevision, $request->fresh()->status);
        $this->assertNull($request->fresh()->custody);
        $this->assertSame(0, Allocation::query()->count());
        $this->assertSame(1, NotificationEvent::query()->where('event_code', 'REQUEST_RETURNED_FOR_REVISION')->count());
        $this->assertNoMissedPickupOrPenalty($request);

        // The borrower can open the returned request to change its dates.
        $this->flushSession();
        $this->withSession(['active_workspace' => 'BORROWER'])->actingAs($this->borrower)
            ->get(route('requests.edit', $request))->assertOk()
            ->assertSee('id="schedule_date"', false);
    }

    public function test_borrower_can_withdraw_and_authorized_spmu_can_close_a_request_under_schedule_review(): void
    {
        // The borrower decides they no longer need the items: the existing cancellation applies.
        $withdrawn = $this->submittedRequest('DA-WITHDRAW', '2026-09-22');
        $this->approveAt($withdrawn, Carbon::create(2026, 9, 22, 9, 0));
        $this->flushSession();
        $this->withSession(['active_workspace' => 'BORROWER'])->actingAs($this->borrower)
            ->post(route('requests.cancel', $withdrawn), ['reason' => 'The activity was moved; the items are no longer needed.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Cancelled, $withdrawn->fresh()->status);
        $this->assertNotSame('ACTIVE', $this->allocation($withdrawn)->status);

        // Nobody acted before Items Needed From passed: it is not stuck, and says who can close it.
        $stale = $this->submittedRequest('DA-STALE', '2026-09-24');
        $this->approveAt($stale, Carbon::create(2026, 9, 24, 9, 0));
        $custody = $this->custody($stale);
        $this->travelTo(Carbon::create(2026, 9, 28, 9, 0));
        $this->flushSession();

        $this->releasePage($custody)
            ->assertSee('Schedule Review Required')
            ->assertSee('This approved release cannot be continued. It must be cancelled; the borrower may then file a new request with new dates.')
            ->assertSee('Refer this to the SPMU Head/Admin, who can cancel the borrowing.')
            ->assertDontSee('Continue Release')
            ->assertDontSee('Pickup Missed');
        $this->assertNull($custody->fresh()->pickup_expired_at);

        $this->flushSession();
        $this->withSession(['active_workspace' => 'SPMU'])->actingAs($this->head)
            ->post(route('requests.cancel', $stale), ['reason' => 'Items Needed From passed before a pickup could be arranged.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(RequestStatus::Cancelled, $stale->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /** An on-campus request already submitted and waiting for the SPMU Head decision. */
    private function submittedRequest(string $requestNo, string $neededFrom): BorrowingRequest
    {
        $needed = Carbon::parse($neededFrom)->startOfDay();
        $return = $needed->copy()->addDays(3);
        $item = InventoryItem::query()->where('unique_description', 'Round Table')->firstOrFail();

        $request = BorrowingRequest::query()->create([
            'request_no' => $requestNo,
            'borrower_user_id' => $this->borrower->id,
            'accountable_unit_id' => $this->borrower->organizational_unit_id,
            'current_version_no' => 1,
            'status' => RequestStatus::UnderSpmu,
        ]);
        $version = $request->versions()->create([
            'version_no' => 1,
            'purpose_event' => 'Institutional activity',
            'location' => 'CSPC Campus',
            'schedule_date' => $needed->toDateString(),
            'return_date' => $return->toDateString(),
            'needed_from' => $needed,
            'return_due_at' => $return->copy()->endOfDay(),
            'represents_student_activity' => false,
            'off_campus' => false,
            'created_by_user_id' => $this->borrower->id,
            'submitted_at' => now(),
            'signed_at' => now(),
        ]);
        RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description,
            'unit_snapshot' => $item->unit->unit_name,
            'requested_quantity' => 1,
            'use_location' => 'ON_CAMPUS',
        ]);
        ApprovalStep::query()->create([
            'request_version_id' => $version->id, 'stage_code' => 'SPMU', 'sequence_no' => 2,
            'received_at' => now(), 'decision' => 'RECEIVED',
        ]);

        $bytes = \Tests\Support\EvidenceFixtures::pdfBytes('Delayed approval supporting letter');
        $file = StoredFile::query()->create([
            'uploaded_by_user_id' => $this->borrower->id, 'disk' => 'local',
            'storage_path' => 'tests/request-supporting-documents/'.$request->id.'/letter.pdf',
            'original_name' => 'letter.pdf', 'mime_type' => 'application/pdf', 'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'classification' => 'REQUEST_SUPPORTING_DOCUMENT',
        ]);
        Storage::disk('local')->put($file->storage_path, $bytes);
        RequestSupportingDocument::query()->create([
            'request_id' => $request->id, 'request_version_id' => $version->id,
            'document_type' => RequestSupportingDocument::TYPE_REQUEST_LETTER, 'version_no' => 1,
            'stored_file_id' => $file->id, 'uploaded_by_user_id' => $this->borrower->id, 'uploaded_at' => now(),
            'verification_status' => RequestSupportingDocument::STATUS_PENDING, 'is_current' => true,
        ]);

        return $request;
    }

    private function approveAt(BorrowingRequest $request, Carbon $moment): void
    {
        $this->travelTo($moment);
        $this->actingAs($this->head);
        session(['active_workspace' => 'SPMU']);

        app(RequestWorkflowService::class)->decide($request->fresh(), $this->head, 'APPROVED', null, true);
    }

    private function registerSignature(User $user): void
    {
        $bytes = \Tests\Support\EvidenceFixtures::signatureBytes('signature-ink-'.$user->id);
        $path = 'tests/signatures/'.$user->id.'/signature.png';
        Storage::disk('local')->put($path, $bytes);

        $file = StoredFile::query()->create([
            'uploaded_by_user_id' => $user->id, 'disk' => 'local', 'storage_path' => $path,
            'original_name' => 'signature.png', 'mime_type' => 'image/png', 'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'classification' => 'SIGNATURE',
        ]);
        UserSignature::query()->create([
            'user_id' => $user->id, 'stored_file_id' => $file->id,
            'effective_from' => now()->subMinute(), 'effective_to' => null, 'status' => 'ACTIVE',
        ]);
    }

    private function asOfficer(): static
    {
        return $this->withSession(['active_workspace' => 'SPMU'])->actingAs($this->officer);
    }

    private function releasePage(CustodyTransaction $custody): \Illuminate\Testing\TestResponse
    {
        return $this->asOfficer()->get(route('custody.release.show', $custody))->assertOk();
    }

    private function custody(BorrowingRequest $request): CustodyTransaction
    {
        return CustodyTransaction::query()->where('request_id', $request->id)->firstOrFail();
    }

    private function allocation(BorrowingRequest $request): Allocation
    {
        return Allocation::query()
            ->whereIn('request_item_id', $request->fresh()->currentVersion->items()->pluck('id'))
            ->firstOrFail();
    }

    private function auditCount(CustodyTransaction $custody, string $action): int
    {
        return AuditEvent::query()->where('record_type', CustodyTransaction::class)
            ->where('record_id', $custody->id)->where('action_code', $action)->count();
    }

    private function lastAudit(CustodyTransaction $custody, string $action): AuditEvent
    {
        return AuditEvent::query()->where('record_type', CustodyTransaction::class)
            ->where('record_id', $custody->id)->where('action_code', $action)->latest('id')->firstOrFail();
    }

    /** The latest message of an event type about this record. */
    private function borrowerMessage(string $eventCode, BorrowingRequest|CustodyTransaction $source): string
    {
        $event = NotificationEvent::query()
            ->where('event_code', $eventCode)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->id)
            ->oldest('id')
            ->firstOrFail();

        return (string) ($event->message ?? $event->body ?? json_encode($event->getAttributes()));
    }

    private function assertNoMissedPickupOrPenalty(BorrowingRequest $request): void
    {
        $this->assertSame(0, NotificationEvent::query()->where('event_code', 'PICKUP_EXPIRED')->count());
        $this->assertSame(0, AuditEvent::query()->whereIn('action_code', ['PICKUP_WINDOW_EXPIRED', 'PICKUP_AUTO_CANCELLED_UNCLAIMED'])->count());
        $this->assertSame(0, DB::table('request_cancellations')->where('request_id', $request->id)->count());
        foreach (['penalties', 'sanctions', 'borrower_violations', 'borrower_restrictions', 'overdue_cases', 'incidents'] as $table) {
            $this->assertSame(0, DB::table($table)->where('borrower_user_id', $this->borrower->id)->count(), $table);
        }
    }
}
