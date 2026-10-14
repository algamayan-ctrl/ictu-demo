<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\AcademicPeriod;
use App\Models\Allocation;
use App\Models\AuditEvent;
use App\Models\BillingStatement;
use App\Models\BorrowerRestriction;
use App\Models\BorrowerViolation;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\IncidentLine;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\OrganizationalUnit;
use App\Models\Payment;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\Sanction;
use App\Models\SanctionRule;
use App\Models\StoredFile;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserSignature;
use App\Services\BorrowerObligationService;
use App\Services\PolicyService;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A property finding the SPMU Head cleared must stay cleared everywhere:
 * no offense can be confirmed from it afterwards, its history shows what
 * was actually decided and when, and My Obligations tells the borrower the
 * same thing the borrowing-eligibility rule will enforce.
 */
class AccountabilityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $unit;

    private Carbon $now;

    private AcademicPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        Carbon::setTestNow(Carbon::create(2026, 9, 21, 9));
        $this->now = Carbon::create(2026, 9, 21, 9);

        $this->unit = OrganizationalUnit::query()->create([
            'unit_code' => 'CONSIST',
            'unit_name' => 'Consistency Test Unit',
            'unit_type' => 'OFFICE',
            'active' => true,
        ]);

        AcademicPeriod::query()->where('status', 'ACTIVE')->update(['status' => 'CLOSED']);
        $this->period = AcademicPeriod::query()->create([
            'academic_year' => '2026-2027',
            'term_code' => 'CONSIST-TERM',
            'term_name' => 'Consistency Term',
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-18',
            'status' => 'ACTIVE',
        ]);

        $this->sanctionRules();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */
    /* A. Cleared finding and its violation                                */
    /* ------------------------------------------------------------------ */

    public function test_clearing_a_finding_dismisses_its_pending_violation_with_an_audit_record(): void
    {
        [$custody, $line] = $this->custody('Cleared Finding Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $violation = $this->propertyViolation($custody);

        $this->clear($head, $incident, 'Damage was pre-existing and already on record.')->assertSessionHasNoErrors();

        $violation->refresh();
        $this->assertSame('DISMISSED', $violation->status);
        $this->assertSame($head->id, $violation->reviewed_by_user_id);
        $this->assertNotNull($violation->reviewed_at);
        $this->assertStringContainsString($incident->incident_no, (string) $violation->review_remarks);
        $this->assertSame(0, Sanction::query()->count());

        /* The violation is kept, not deleted, and its dismissal explains itself. */
        $dismissal = AuditEvent::query()->where('action_code', 'BORROWING_VIOLATION_DISMISSED')->where('record_id', $violation->id)->sole();
        $this->assertSame('PROPERTY_FINDING_CLEARED', data_get($dismissal->after_json, 'cause'));
        $this->assertSame($incident->id, data_get($dismissal->after_json, 'incident_id'));
        $this->assertSame('PENDING_REVIEW', data_get($dismissal->before_json, 'status'));
        $this->assertSame('Damage was pre-existing and already on record.', $dismissal->reason);

        $resolved = AuditEvent::query()->where('action_code', 'PROPERTY_ACCOUNTABILITY_CASE_RESOLVED')->where('record_id', $incident->id)->sole();
        $this->assertSame('FINDING_CLEARED', data_get($resolved->after_json, 'resolution_outcome'));
        $this->assertSame(1, data_get($resolved->after_json, 'pending_violations_dismissed'));

        /* The borrower is no longer restricted by this case and may borrow. */
        $this->assertSame(0, BorrowerRestriction::query()->where('incident_id', $incident->id)->where('status', 'ACTIVE')->count());
        $this->assertTrue(app(BorrowerObligationService::class)->isEligibleToBorrow($custody->borrower_user_id));
    }

    public function test_a_confirmed_finding_still_records_its_legitimate_sanction(): void
    {
        [$custody, $line] = $this->custody('Confirmed Finding Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $violation = $this->propertyViolation($custody);

        $this->confirm($head, $incident, 'Damage verified during return inspection.')->assertSessionHasNoErrors();

        $this->assertSame('F2F_PROCESSING', $incident->fresh()->status);
        $this->assertSame('CONFIRMED', $violation->fresh()->status);

        $sanction = Sanction::query()->where('borrower_violation_id', $violation->id)->sole();
        $this->assertSame(1, $sanction->offense_no);
        $this->assertSame('WRITTEN_REPRIMAND', $sanction->sanction_code);
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'BORROWING_VIOLATION_DISMISSED')->count());
    }

    public function test_a_cleared_finding_cannot_be_confirmed_through_the_generic_review_endpoint(): void
    {
        [$custody, $line] = $this->custody('Legacy Cleared Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');

        /* The state older records were left in: finding cleared, violation still pending. */
        $this->markClearedAsBefore($incident, $head);
        $violation = $this->propertyViolation($custody);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('accountability.violations.review', $violation), [
                'decision' => 'CONFIRMED',
                'sanction_code' => 'NOTICE',
                'effective_to' => $this->now->toDateString(),
                '_action_token' => 'consistency-old-endpoint-confirm',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('PENDING_REVIEW', $violation->fresh()->status);
        $this->assertSame(0, Sanction::query()->count());

        /* The service entry point used by the incident decision refuses it too. */
        try {
            app(PolicyService::class)->confirmIncidentOffense($incident->fresh(), $head, 'Attempt after clearance.');
            $this->fail('A cleared finding was confirmed as an offense.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('count_as_offense', $exception->errors());
        }
        $this->assertSame(0, Sanction::query()->count());

        /* Dismissing the left-over violation is still allowed, so it can be closed properly. */
        $this->travel(30)->seconds();
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('accountability.violations.review', $violation), [
                'decision' => 'DISMISSED',
                'remarks' => 'Finding was cleared by the SPMU Head.',
                '_action_token' => 'consistency-old-endpoint-dismiss',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('DISMISSED', $violation->fresh()->status);
    }

    public function test_other_violation_reviews_keep_working(): void
    {
        [$head] = $this->headAndOfficer();

        /* A late-return violation has no property finding behind it. */
        $borrower = User::factory()->create(['access_classification' => AccessClassification::BorrowerOnly]);
        $lateReturn = BorrowerViolation::query()->create([
            'borrower_user_id' => $borrower->id,
            'academic_period_id' => $this->period->id,
            'violation_code' => 'LATE_RETURN',
            'violation_source' => 'LATE_RETURN',
            'details_json' => ['reasons' => ['LATE_RETURN']],
            'status' => 'PENDING_REVIEW',
            'detected_at' => $this->now,
        ]);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('accountability.violations.review', $lateReturn), [
                'decision' => 'CONFIRMED',
                '_action_token' => 'consistency-late-return-confirm',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('CONFIRMED', $lateReturn->fresh()->status);

        /* A property violation whose finding is still open can be confirmed as before. */
        [$custody, $line] = $this->custody('Open Finding Fixture');
        [, $officer] = $this->headAndOfficer();
        $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $open = $this->propertyViolation($custody);

        $sanction = app(PolicyService::class)->reviewViolation($open, $head, 'CONFIRMED', 'Open finding confirmed.');
        $this->assertNotNull($sanction);
        $this->assertSame('CONFIRMED', $open->fresh()->status);
    }

    public function test_repeated_and_opposite_decisions_never_produce_conflicting_records(): void
    {
        [$head, $officer] = $this->headAndOfficer();

        /* Clear, then try everything else. */
        [$custody, $line] = $this->custody('Repeat Clear Fixture');
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $violation = $this->propertyViolation($custody);

        $this->clear($head, $incident, 'Cleared on review.')->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->clear($head, $incident, 'Cleared a second time.', 'second')->assertStatus(422);
        $this->travel(30)->seconds();
        $this->confirm($head, $incident, 'Confirm after clearing.', 'after-clear')->assertStatus(422);
        $this->travel(30)->seconds();
        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('accountability.violations.review', $violation), [
                'decision' => 'CONFIRMED',
                '_action_token' => 'consistency-repeat-review',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame('DISMISSED', $violation->fresh()->status);
        $this->assertSame(0, Sanction::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'BORROWING_VIOLATION_DISMISSED')->where('record_id', $violation->id)->count());
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'PROPERTY_ACCOUNTABILITY_CASE_RESOLVED')->where('record_id', $incident->id)->count());

        /* Confirm, then try to clear: the sanction stands, once. */
        [$custodyB, $lineB] = $this->custody('Repeat Confirm Fixture');
        $incidentB = $this->openIncident($custodyB, $lineB, $officer, 'DAMAGED');
        $violationB = $this->propertyViolation($custodyB);

        $this->travel(30)->seconds();
        $this->confirm($head, $incidentB, 'Confirmed on review.', 'b-first')->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->confirm($head, $incidentB, 'Confirmed again.', 'b-second')->assertStatus(422);
        $this->travel(30)->seconds();
        $this->clear($head, $incidentB, 'Clear after confirming.', 'b-clear')->assertStatus(422);

        $this->assertSame('CONFIRMED', $violationB->fresh()->status);
        $this->assertSame(1, Sanction::query()->where('borrower_violation_id', $violationB->id)->count());
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'BORROWING_VIOLATION_DISMISSED')->where('record_id', $violationB->id)->count());
    }

    public function test_a_violation_with_another_finding_behind_it_is_not_dismissed(): void
    {
        [$custody, $line] = $this->custody('Two Findings Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $first = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $second = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $violation = $this->propertyViolation($custody);

        /* One finding cleared, the other still open: the violation keeps its basis. */
        $this->clear($head, $first, 'First finding cleared.', 'first')->assertSessionHasNoErrors();
        $this->assertSame('PENDING_REVIEW', $violation->fresh()->status);
        $this->assertSame(0, data_get(
            AuditEvent::query()->where('action_code', 'PROPERTY_ACCOUNTABILITY_CASE_RESOLVED')->where('record_id', $first->id)->sole()->after_json,
            'pending_violations_dismissed'
        ));

        /* The last remaining finding is cleared: nothing supports the violation any more. */
        $this->travel(30)->seconds();
        $this->clear($head, $second, 'Second finding cleared.', 'second')->assertSessionHasNoErrors();
        $this->assertSame('DISMISSED', $violation->fresh()->status);
    }

    public function test_an_already_confirmed_violation_and_its_sanction_are_never_reversed(): void
    {
        [$custody, $line] = $this->custody('Historical Sanction Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');

        /* A decision recorded earlier: the violation is confirmed and carries a sanction. */
        $violation = $this->propertyViolation($custody, 'CONFIRMED');
        $sanction = $this->sanction($violation, 'NOTICE', 'Notice', $this->now->copy()->endOfDay());
        $before = [$violation->fresh()->getAttributes(), $sanction->fresh()->getAttributes()];

        $this->clear($head, $incident, 'Cleared on review.')->assertSessionHasNoErrors();

        $this->assertSame($before, [$violation->fresh()->getAttributes(), $sanction->fresh()->getAttributes()]);
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'BORROWING_VIOLATION_DISMISSED')->count());
        $this->assertSame(1, Sanction::query()->count());
    }

    /* ------------------------------------------------------------------ */
    /* B. Resolution timestamps                                            */
    /* ------------------------------------------------------------------ */

    public function test_clearing_records_the_resolution_time_and_actor(): void
    {
        [$custody, $line] = $this->custody('Timestamp Fixture');
        [$head, $officer] = $this->headAndOfficer();
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');

        $this->clear($head, $incident, 'Cleared on review.')->assertSessionHasNoErrors();

        $incident->refresh();
        $this->assertSame($head->id, $incident->resolved_by_user_id);
        $this->assertSame($this->now->toDateTimeString(), $incident->resolved_at->toDateTimeString());

        /* A later, unrelated edit to the row does not move the resolution date shown in history. */
        Carbon::setTestNow($this->now->copy()->addDays(3));
        $incident->update(['police_blotter_reference' => 'N/A']);

        $row = $this->historyRow($custody->borrower_user_id, $incident);
        $this->assertSame($this->now->toDateTimeString(), Carbon::parse($row['resolved_at'])->toDateTimeString());
    }

    public function test_older_records_without_a_resolution_time_use_a_defined_fallback_and_are_not_rewritten(): void
    {
        [$head, $officer] = $this->headAndOfficer();

        /* Cleared before the resolution time was recorded: the Head's decision time is the resolution. */
        [$custody, $line] = $this->custody('Legacy Timestamp Fixture');
        $cleared = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $this->markClearedAsBefore($cleared, $head);
        $decidedAt = $cleared->fresh()->head_decided_at;

        Carbon::setTestNow($this->now->copy()->addDays(4));
        $cleared->fresh()->update(['police_blotter_reference' => 'N/A']);

        $row = $this->historyRow($custody->borrower_user_id, $cleared);
        $this->assertSame($decidedAt->toDateTimeString(), Carbon::parse($row['resolved_at'])->toDateTimeString());
        $this->assertSame('Resolved - Finding Cleared', $row['outcome']);
        $this->assertNull($cleared->fresh()->resolved_at, 'The historical record must not be rewritten.');
        $this->assertNull($cleared->fresh()->resolved_by_user_id);

        /* No decision line and no resolution time at all: last update, labelled plainly. */
        [$custodyB, $lineB] = $this->custody('Legacy Plain Fixture');
        $plain = $this->openIncident($custodyB, $lineB, $officer, 'DAMAGED');
        $plain->update(['status' => 'RESOLVED']);

        $rowB = $this->historyRow($custodyB->borrower_user_id, $plain);
        $this->assertSame($plain->fresh()->updated_at->toDateTimeString(), Carbon::parse($rowB['resolved_at'])->toDateTimeString());
        $this->assertSame('Resolved', $rowB['outcome']);
        $this->assertNull($plain->fresh()->resolved_at);
    }

    /* ------------------------------------------------------------------ */
    /* C. Borrower KPIs                                                    */
    /* ------------------------------------------------------------------ */

    public function test_borrowing_status_follows_the_same_rule_as_request_eligibility(): void
    {
        $obligations = app(BorrowerObligationService::class);
        [$custody] = $this->custody('Status Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);

        /* Nothing open: clear, and eligible. */
        $status = $obligations->borrowingStatus($borrower->id);
        $this->assertSame('Clear', $status['label']);
        $this->assertTrue($status['eligible']);
        $this->assertTrue($obligations->isEligibleToBorrow($borrower->id));
        $this->borrowerPage($borrower)->assertOk()->assertSee('No active restriction')->assertDontSee('On Hold');

        /* An unpaid billing with no restriction row still blocks a request - the card must not say Clear. */
        $billing = $this->billing($borrower, 500);
        $status = $obligations->borrowingStatus($borrower->id);
        $this->assertFalse($obligations->isEligibleToBorrow($borrower->id));
        $this->assertFalse($status['eligible']);
        $this->assertFalse($status['restricted']);
        $this->assertSame('On Hold', $status['label']);
        $this->assertNotEmpty($status['reasons']);
        $this->borrowerPage($borrower)
            ->assertOk()
            ->assertSee('On Hold')
            ->assertSee('Open obligation blocks borrowing')
            ->assertDontSee('No active restriction');

        /* An active restriction reads Restricted, as before. */
        BorrowerRestriction::query()->create([
            'borrower_user_id' => $borrower->id,
            'billing_statement_id' => $billing->id,
            'restriction_type' => 'UNRESOLVED_PROPERTY_OBLIGATION',
            'reason' => 'Open billing statement '.$billing->billing_no,
            'effective_from' => $this->now->copy()->subMinute(),
            'status' => 'ACTIVE',
        ]);
        $status = $obligations->borrowingStatus($borrower->id);
        $this->assertSame('Restricted', $status['label']);
        $this->assertFalse($obligations->isEligibleToBorrow($borrower->id));
        $this->borrowerPage($borrower)->assertOk()->assertSee('Restriction in effect');

        /* Settled and lifted: clear again. The eligibility rule itself was not changed. */
        $billing->update(['status' => 'SETTLED']);
        BorrowerRestriction::query()->where('borrower_user_id', $borrower->id)->update(['status' => 'LIFTED']);
        $this->assertSame('Clear', $obligations->borrowingStatus($borrower->id)['label']);
        $this->assertTrue($obligations->isEligibleToBorrow($borrower->id));
    }

    public function test_amount_due_is_the_remaining_unpaid_balance_on_both_screens(): void
    {
        $obligations = app(BorrowerObligationService::class);
        [$custody] = $this->custody('Balance Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);

        $billing = $this->billing($borrower, 500);
        $this->payment($billing, $borrower, 200, 'VERIFIED');
        $this->payment($billing, $borrower, 100, 'SUBMITTED');

        /* Only the verified PHP 200 reduces the balance; the billed amount itself is untouched. */
        $this->assertSame(300.0, $obligations->outstandingBalance(collect([$billing->fresh('payments')])));
        $this->assertSame(300.0, $obligations->overview($borrower->id)['billing_total']);
        $this->assertSame(500.0, (float) $billing->fresh()->total_amount);

        $this->borrowerPage($borrower)->assertOk()->assertSee('PHP 300.00')->assertSee('Unpaid billing balance');

        /* Overpayment never shows a negative amount. */
        $this->payment($billing, $borrower, 400, 'VERIFIED');
        $this->assertSame(0.0, $obligations->overview($borrower->id)['billing_total']);
        $this->borrowerPage($borrower)->assertOk()->assertSee('My Unsettled Charges')
            ->assertSee('PHP 0.00')->assertSee('No unsettled charges');
    }

    public function test_head_unsettled_charges_count_only_the_unpaid_part_of_issued_billings(): void
    {
        [$custody] = $this->custody('Head Balance Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);
        [$head] = $this->headAndOfficer();

        /* Partly paid: only the verified PHP 200 counts; the submitted PHP 100 is not yet verified. */
        $partial = $this->billing($borrower, 500);
        $this->payment($partial, $borrower, 200, 'VERIFIED');
        $this->payment($partial, $borrower, 100, 'SUBMITTED');

        /* Settled, waived and voided (corrected) statements owe nothing. */
        $this->billing($borrower, 700)->update(['status' => 'SETTLED']);
        $this->billing($borrower, 800)->update(['status' => 'WAIVED']);
        $this->billing($borrower, 900)->update(['status' => 'VOID']);

        $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->get(route('accountability.index'))
            ->assertOk()
            ->assertSee('data-accountability-card-label="Unsettled Accountability Charges"', false)
            ->assertSee('Total unpaid charges from confirmed accountability cases only.')
            ->assertSee('PHP 300.00')
            ->assertDontSee('Outstanding Balance');
    }

    /* ------------------------------------------------------------------ */
    /* D. Presentation                                                     */
    /* ------------------------------------------------------------------ */

    public function test_a_notice_reads_as_recorded_and_a_suspension_keeps_its_period(): void
    {
        [$custody] = $this->custody('Sanction Display Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);

        /* A Notice stored with a same-day end date, as the older review form allowed. */
        $notice = $this->sanction($this->propertyViolation($custody, 'CONFIRMED'), 'NOTICE', 'Notice', $this->now->copy()->endOfDay());

        $page = $this->borrowerPage($borrower)->assertOk();
        $page->assertSee('Recorded on 21 Sep 2026')
            ->assertSee('No document issued')
            ->assertDontSee('to 21 Sep 2026');

        /* The stored dates were only presented differently, never changed. */
        $this->assertSame($this->now->copy()->endOfDay()->toDateTimeString(), $notice->fresh()->effective_to->toDateTimeString());

        /* A suspension is a real period and still shows both ends. */
        [$custodyB] = $this->custody('Suspension Display Fixture');
        $suspended = User::query()->findOrFail($custodyB->borrower_user_id);
        $this->sanction($this->propertyViolation($custodyB, 'CONFIRMED'), 'BORROWING_SUSPENSION', '1-Month Borrowing Suspension', $this->now->copy()->addMonth()->endOfDay());

        $this->borrowerPage($suspended)
            ->assertOk()
            ->assertSee('21 Sep 2026')
            ->assertSee('to 21 Oct 2026')
            ->assertDontSee('Recorded on')
            ->assertDontSee('No document issued');
    }

    public function test_obligation_history_details_show_what_was_decided_and_why(): void
    {
        [$head, $officer] = $this->headAndOfficer();

        /* Cleared through the real workflow. */
        [$custody, $line] = $this->custody('History Cleared Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $this->clear($head, $incident, 'Damage was pre-existing and already on record.')->assertSessionHasNoErrors();

        $page = $this->borrowerPage($borrower)->assertOk();
        $page->assertSee('Resolved - Finding Cleared')
            ->assertSee('Date Reported')
            ->assertSee('SPMU Head Decision')
            ->assertSee('Finding Cleared')
            ->assertSee('Reason Recorded')
            ->assertSee('Damage was pre-existing and already on record.')
            ->assertSee($incident->incident_no)
            ->assertDontSee('data-related-sanction', false)
            ->assertDontSee('Accountability Settled');

        /* The staff member's name is not shown to the borrower. */
        $page->assertDontSee($head->full_name);

        /* Confirmed, then settled: a different outcome, with its related sanction. */
        [$custodyB, $lineB] = $this->custody('History Settled Fixture');
        $borrowerB = User::query()->findOrFail($custodyB->borrower_user_id);
        $incidentB = $this->openIncident($custodyB, $lineB, $officer, 'DAMAGED');
        $this->propertyViolation($custodyB);
        $this->travel(30)->seconds();
        $this->confirm($head, $incidentB, 'Damage verified during return inspection.', 'history-b')->assertSessionHasNoErrors();
        $incidentB->fresh()->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'resolved_by_user_id' => $head->id,
        ]);
        BorrowerRestriction::query()->where('incident_id', $incidentB->id)->update(['status' => 'LIFTED']);

        $this->borrowerPage($borrowerB)
            ->assertOk()
            ->assertSee('Resolved - Accountability Settled')
            ->assertSee('Accountability Confirmed')
            ->assertSee('Damage verified during return inspection.')
            ->assertSee('data-related-sanction', false)
            ->assertSee('Offense 1')
            ->assertSee('Written Reprimand')
            ->assertDontSee('Resolved - Finding Cleared');

        /* An older record with no decision on file: nothing is invented for it. */
        [$custodyC, $lineC] = $this->custody('History Plain Fixture');
        $borrowerC = User::query()->findOrFail($custodyC->borrower_user_id);
        $incidentC = $this->openIncident($custodyC, $lineC, $officer, 'DAMAGED');
        $incidentC->update(['status' => 'RESOLVED']);
        BorrowerRestriction::query()->where('incident_id', $incidentC->id)->update(['status' => 'LIFTED']);

        $this->borrowerPage($borrowerC)
            ->assertOk()
            ->assertSee('Final Status')
            ->assertDontSee('SPMU Head Decision')
            ->assertDontSee('Reason Recorded')
            ->assertDontSee('Finding Cleared')
            ->assertDontSee('Accountability Settled');
    }

    public function test_a_cleared_case_with_a_historical_sanction_shows_both_facts_without_altering_either(): void
    {
        [$head, $officer] = $this->headAndOfficer();
        [$custody, $line] = $this->custody('Conflicting History Fixture');
        $borrower = User::query()->findOrFail($custody->borrower_user_id);
        $incident = $this->openIncident($custody, $line, $officer, 'DAMAGED');
        $this->markClearedAsBefore($incident, $head, 'Okay na');
        $sanction = $this->sanction($this->propertyViolation($custody, 'CONFIRMED'), 'NOTICE', 'Notice', $this->now->copy()->endOfDay());
        $before = $sanction->fresh()->getAttributes();

        $this->borrowerPage($borrower)
            ->assertOk()
            ->assertSee('Resolved - Finding Cleared')
            ->assertSee('Okay na')
            ->assertSee('data-related-sanction', false)
            ->assertSee('Offense 1')
            ->assertSee('Recorded on 21 Sep 2026');

        $this->assertSame($before, $sanction->fresh()->getAttributes());
    }

    /* ------------------------------------------------------------------ */
    /* Access                                                              */
    /* ------------------------------------------------------------------ */

    public function test_borrowers_see_only_their_own_records_and_cannot_decide_cases(): void
    {
        [$head, $officer] = $this->headAndOfficer();

        [$custodyA, $lineA] = $this->custody('Isolation Fixture A');
        $borrowerA = User::query()->findOrFail($custodyA->borrower_user_id);
        $incidentA = $this->openIncident($custodyA, $lineA, $officer, 'DAMAGED');
        $this->clear($head, $incidentA, 'Confidential reason for borrower A only.')->assertSessionHasNoErrors();
        $this->sanction($this->propertyViolation($custodyA, 'CONFIRMED'), 'NOTICE', 'Notice', null);

        [$custodyB, $lineB] = $this->custody('Isolation Fixture B');
        $borrowerB = User::query()->findOrFail($custodyB->borrower_user_id);
        $incidentB = $this->openIncident($custodyB, $lineB, $officer, 'DAMAGED');
        $violationB = $this->propertyViolation($custodyB);

        /* Borrower B sees nothing of borrower A's case, reason or sanction. */
        $this->borrowerPage($borrowerB)
            ->assertOk()
            ->assertDontSee($incidentA->incident_no)
            ->assertDontSee('Confidential reason for borrower A only.')
            ->assertDontSee('Recorded on')
            ->assertDontSee($custodyA->custody_no);

        /* Borrower A sees their own. */
        $this->borrowerPage($borrowerA)->assertOk()->assertSee($incidentA->incident_no);

        /* A borrower cannot open the staff per-borrower page or record any decision. */
        $asBorrower = fn () => $this->actingAs($borrowerB)->withSession(['active_workspace' => 'BORROWER']);
        $asBorrower()->get(route('accountability.borrower', $borrowerA))->assertForbidden();
        $asBorrower()->post(route('incidents.resolve', $incidentB), [
            'decision' => 'CLEAR',
            'resolution_remarks' => 'Borrower clearing their own case.',
            '_action_token' => 'consistency-borrower-clear',
        ])->assertForbidden();
        $asBorrower()->post(route('accountability.violations.review', $violationB), [
            'decision' => 'DISMISSED',
            '_action_token' => 'consistency-borrower-dismiss',
        ])->assertForbidden();

        /* The Action Officer cannot make the Head's decisions either. */
        $this->travel(30)->seconds();
        $this->actingAs($officer)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('incidents.resolve', $incidentB), [
                'decision' => 'CLEAR',
                'resolution_remarks' => 'Officer clearing a finding.',
                '_action_token' => 'consistency-officer-clear',
            ])->assertForbidden();
        $this->actingAs($officer)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('accountability.violations.review', $violationB), [
                'decision' => 'DISMISSED',
                '_action_token' => 'consistency-officer-dismiss',
            ])->assertForbidden();

        /* ICTU has no access to borrower accountability records. */
        $ictu = User::where('access_classification', AccessClassification::IctuMaintainer->value)->firstOrFail();
        $this->actingAs($ictu)->withSession(['active_workspace' => 'ICTU'])
            ->get(route('accountability.index'))->assertForbidden();
        $this->actingAs($ictu)->withSession(['active_workspace' => 'ICTU'])
            ->get(route('accountability.borrower', $borrowerA))->assertForbidden();

        $this->assertSame('OPEN', $incidentB->fresh()->status);
        $this->assertSame('PENDING_REVIEW', $violationB->fresh()->status);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function clear(User $head, Incident $incident, string $remarks, string $token = 'first'): TestResponse
    {
        return $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('incidents.resolve', $incident), [
                'decision' => 'CLEAR',
                'resolution_remarks' => $remarks,
                '_action_token' => 'consistency-clear-'.$incident->id.'-'.$token,
            ]);
    }

    private function confirm(User $head, Incident $incident, string $remarks, string $token = 'first'): TestResponse
    {
        return $this->actingAs($head)->withSession(['active_workspace' => 'SPMU'])
            ->post(route('incidents.resolve', $incident), [
                'decision' => 'CONFIRM',
                'resolution_remarks' => $remarks,
                '_action_token' => 'consistency-confirm-'.$incident->id.'-'.$token,
            ]);
    }

    private function borrowerPage(User $borrower): TestResponse
    {
        return $this->actingAs($borrower)
            ->withSession(['active_workspace' => 'BORROWER'])
            ->get(route('accountability.index'));
    }

    /** The violation the return workflow detects from a property finding. */
    private function propertyViolation(CustodyTransaction $custody, string $status = 'PENDING_REVIEW'): BorrowerViolation
    {
        return BorrowerViolation::query()->create([
            'borrower_user_id' => $custody->borrower_user_id,
            'custody_transaction_id' => $custody->id,
            'academic_period_id' => $this->period->id,
            'violation_code' => 'BORROWING_VIOLATION',
            'violation_source' => 'PROPERTY_ACCOUNTABILITY',
            'details_json' => ['reasons' => ['DAMAGED'], 'late_days' => 0],
            'status' => $status,
            'detected_at' => $this->now,
        ]);
    }

    private function sanction(BorrowerViolation $violation, string $code, string $label, ?Carbon $effectiveTo): Sanction
    {
        return Sanction::query()->create([
            'borrower_violation_id' => $violation->id,
            'borrower_user_id' => $violation->borrower_user_id,
            'academic_period_id' => $this->period->id,
            'offense_no' => 1,
            'sanction_code' => $code,
            'sanction_label' => $label,
            'effective_from' => $this->now,
            'effective_to' => $effectiveTo,
            'status' => 'ACTIVE',
            'confirmed_by_user_id' => User::where('access_classification', AccessClassification::SpmuHead->value)->value('id'),
            'confirmed_at' => $this->now,
            'remarks' => 'Recorded earlier.',
        ]);
    }

    /**
     * An incident exactly as the Clear decision left it before resolution
     * metadata and violation dismissal were added: RESOLVED, with the Head's
     * decision line, and no resolved_at / resolved_by.
     */
    private function markClearedAsBefore(Incident $incident, User $head, string $reason = 'Cleared on review.'): void
    {
        $incident->update([
            'status' => 'RESOLVED',
            'head_decided_by_user_id' => $head->id,
            'head_decided_at' => $this->now->copy()->subHour(),
            'accountability_flow_version' => 'V3',
            'remarks' => 'SPMU Head decision: Finding cleared. '.$reason,
        ]);

        BorrowerRestriction::query()->where('incident_id', $incident->id)->update(['status' => 'LIFTED']);
    }

    /** @return array<string, mixed> */
    private function historyRow(int $borrowerUserId, Incident $incident): array
    {
        $obligations = app(BorrowerObligationService::class);
        $records = $obligations->recordsForBorrower($borrowerUserId);

        return $obligations
            ->resolvedHistory($records['billings'], $records['overdueCases'], $records['incidents'])
            ->firstWhere('key', 'incident-'.$incident->id);
    }

    private function billing(User $borrower, float $amount): BillingStatement
    {
        return BillingStatement::query()->create([
            'billing_no' => 'BILL-CONSIST-'.fake()->unique()->numberBetween(1000, 999999),
            'borrower_user_id' => $borrower->id,
            'responsible_spmu_user_id' => $borrower->id,
            'issued_at' => now(),
            'total_amount' => $amount,
            'status' => 'ISSUED',
        ]);
    }

    private function payment(BillingStatement $billing, User $borrower, float $amount, string $status): Payment
    {
        return Payment::query()->create([
            'billing_statement_id' => $billing->id,
            'recorded_by_user_id' => $borrower->id,
            'verified_by_user_id' => $status === 'VERIFIED' ? $borrower->id : null,
            'official_receipt_no' => 'OR-CONSIST-'.fake()->unique()->numberBetween(1000, 999999),
            'receipt_date' => now()->toDateString(),
            'amount' => $amount,
            'status' => $status,
            'verified_at' => $status === 'VERIFIED' ? now() : null,
        ]);
    }

    /**
     * @return array{0:CustodyTransaction,1:CustodyLine}
     */
    private function custody(string $description): array
    {
        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'RSLDDPFX'],
            ['category_name' => 'RSLDDP Fixture', 'active' => true]
        );
        $measure = UnitOfMeasure::query()->firstOrCreate(
            ['unit_code' => 'PC'],
            ['unit_name' => 'Piece', 'active' => true]
        );
        $item = InventoryItem::query()->firstOrCreate(
            ['unique_description' => $description],
            [
                'category_id' => $category->id,
                'unit_id' => $measure->id,
                'total_quantity' => 100,
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
            'request_no' => 'BR-RSLDDP-'.fake()->unique()->numberBetween(1000, 9999),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $this->unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);

        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => 'RSLDDP workflow fixture',
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => 'Test Office',
            'schedule_date' => $this->now->copy()->addDay()->toDateString(),
            'return_date' => $this->now->copy()->addDays(5)->toDateString(),
            'needed_from' => $this->now->copy()->addDay(),
            'return_due_at' => $this->now->copy()->addDays(5)->endOfDay(),
        ]);

        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $description,
            'unit_snapshot' => 'Piece',
            'requested_quantity' => 1,
            'approved_quantity' => 1,
        ]);
        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => $this->now->copy()->addDay(),
            'period_end' => $this->now->copy()->addDays(5),
            'allocated_quantity' => 1,
            'released_quantity' => 1,
            'restored_quantity' => 0,
            'status' => 'RELEASED',
            'allocated_at' => $this->now->copy()->addDay(),
        ]);
        $custody = CustodyTransaction::query()->create([
            'custody_no' => 'CUS-RSLDDP-'.fake()->unique()->numberBetween(1000, 9999),
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id,
            'status' => 'ACTIVE',
            'released_at' => $this->now->copy()->addDay(),
            'due_at' => $this->now->copy()->addDays(5)->endOfDay(),
        ]);

        $line = CustodyLine::query()->create([
            'custody_transaction_id' => $custody->id,
            'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id,
            'approved_quantity' => 1,
            'quantity_to_receive' => 1,
            'actual_released_quantity' => 1,
            'returned_quantity' => 1,
        ]);

        return [$custody->fresh(), $line];
    }

    /** @return array{0:User,1:User} */
    private function headAndOfficer(): array
    {
        $head = User::where('access_classification', AccessClassification::SpmuHead->value)->firstOrFail();
        $officer = User::where('access_classification', AccessClassification::SpmuOfficer->value)->firstOrFail();

        $this->registerSignature($head);
        $this->registerSignature($officer);

        return [$head, $officer];
    }

    private function registerSignature(User $user): void
    {
        if (UserSignature::where('user_id', $user->id)->where('status', 'ACTIVE')->exists()) {
            return;
        }

        $bytes = \Tests\Support\EvidenceFixtures::signatureBytes('signature-ink-'.$user->id);
        $path = 'tests/signatures/'.$user->id.'/signature.png';

        Storage::disk('local')->put($path, $bytes);

        $file = StoredFile::create([
            'uploaded_by_user_id' => $user->id,
            'disk' => 'local',
            'storage_path' => $path,
            'original_name' => 'signature.png',
            'mime_type' => 'image/png',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'classification' => 'SIGNATURE',
        ]);

        UserSignature::query()->create([
            'user_id' => $user->id,
            'stored_file_id' => $file->id,
            'effective_from' => now()->subMinute(),
            'effective_to' => null,
            'status' => 'ACTIVE',
        ]);
    }

    private function openIncident(CustodyTransaction $custody, CustodyLine $line, User $officer, string $type): Incident
    {
        $incident = Incident::query()->create([
            'incident_no' => 'INC-RSLDDP-'.fake()->unique()->numberBetween(1000, 9999),
            'custody_transaction_id' => $custody->id,
            'borrower_user_id' => $custody->borrower_user_id,
            'reported_by_user_id' => $officer->id,
            'incident_type' => $type,
            'reported_at' => $this->now,
            'status' => 'OPEN',
        ]);

        IncidentLine::query()->create([
            'incident_id' => $incident->id,
            'custody_line_id' => $line->id,
            'quantity' => 1,
            'observed_condition' => $type,
            'disposition_state' => $type === 'DAMAGED' ? 'DAMAGED_MAINTENANCE' : 'REPLACEMENT_REQUIRED',
        ]);

        BorrowerRestriction::query()->create([
            'borrower_user_id' => $custody->borrower_user_id,
            'custody_transaction_id' => $custody->id,
            'incident_id' => $incident->id,
            'restriction_type' => 'UNRESOLVED_INCIDENT',
            'reason' => 'Unresolved '.$type.' incident.',
            'status' => 'ACTIVE',
            'effective_from' => $this->now,
            'imposed_by_user_id' => $officer->id,
        ]);

        return $incident->fresh();
    }

    /** @return array{0:SanctionRule,1:SanctionRule} */
    private function sanctionRules(): array
    {
        return [
            SanctionRule::query()->firstOrCreate(
                ['sanction_code' => 'WRITTEN_REPRIMAND', 'offense_no' => 1],
                ['sanction_label' => 'Written Reprimand', 'duration_mode' => 'NONE', 'status' => 'ACTIVE']
            ),
            SanctionRule::query()->firstOrCreate(
                ['sanction_code' => 'BORROWING_SUSPENSION', 'offense_no' => 2],
                ['sanction_label' => '1-Month Borrowing Suspension', 'duration_mode' => 'MONTHS', 'duration_value' => 1, 'status' => 'ACTIVE']
            ),
        ];
    }
}
