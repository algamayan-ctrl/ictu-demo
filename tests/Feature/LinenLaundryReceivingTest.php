<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\AuditEvent;
use App\Models\BorrowerRestriction;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\EvidenceSubmission;
use App\Models\GeneratedDocument;
use App\Models\InventoryItem;
use App\Models\LaundryJob;
use App\Models\LaundryJobLine;
use App\Models\LaundryReceivingDay;
use App\Models\NotificationEvent;
use App\Models\OperationalDateException;
use App\Models\OverdueCase;
use App\Models\RequestItem;
use App\Models\Sanction;
use App\Models\StoredFile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\BorrowerObligationService;
use App\Services\LateReturnService;
use App\Services\OperationalCalendarService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Linen returns against two receiving calendars.
 *
 * Linen is handed to Laundry Personnel, who sign the Laundry Form with the
 * actual date received; the Action Officer later uploads that form and types
 * the signed date. A linen late day is any day after the effective due date,
 * through the signed date, that is an SPMU open return day OR a date the SPMU
 * Head specifically authorized for Laundry receiving. Equipment keeps the
 * SPMU calendar only.
 *
 * Fixed week used throughout (Monday to Friday are SPMU open days):
 *   Fri 6 Nov 2026 (due) - Sat 7 - Sun 8 - Mon 9 - Tue 10 - Wed 11
 */
class LinenLaundryReceivingTest extends TestCase
{
    use RefreshDatabase;

    private const FRI = '2026-11-06';
    private const SAT = '2026-11-07';
    private const SUN = '2026-11-08';
    private const MON = '2026-11-09';
    private const TUE = '2026-11-10';
    private const WED = '2026-11-11';

    private LateReturnService $lateReturns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $tariff = SystemSetting::query()->where('setting_key', 'daily_overdue_tariff')->firstOrFail();
        $tariff->value_json = 50;
        $tariff->save();

        $this->lateReturns = app(LateReturnService::class);
        $this->at(self::MON);
    }

    /* ------------------------------------------------------------------ */
    /* Policy 1 - chargeable late days for linen                           */
    /* ------------------------------------------------------------------ */

    public function test_friday_due_and_friday_receipt_is_not_late(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::FRI)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $this->assertSame(0, $this->lateReturns->chargeableLateDays($custody->fresh()));
        $this->assertNull($this->headCase($custody));
    }

    public function test_a_closed_transaction_keeps_its_archived_laundry_form(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::FRI)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $custody->fresh()->status);

        $snapshot = $this->laundryEvidenceSnapshot($custody);

        /* A crafted re-upload cannot replace the archived form of a Closed transaction. */
        $this->travel(30)->seconds();
        $this->upload($job, self::MON)->assertSessionHasErrors('evidence');

        $this->assertSame($snapshot, $this->laundryEvidenceSnapshot($custody));
    }

    public function test_an_archived_laundry_form_is_not_replaced_while_the_custody_is_obligation_open(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* Monday is one late day: the case waits for the Head, so the custody stays open. */
        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->assertSame('OBLIGATION_OPEN', $custody->fresh()->status);
        $this->assertSame('LAUNDRY_COMPLETED', $job->fresh()->status);

        $snapshot = $this->laundryEvidenceSnapshot($custody);

        $this->travel(30)->seconds();
        $this->upload($job, self::FRI)->assertSessionHasErrors('evidence');

        /* The archived form, its verifier and the late-day case all stay exactly as recorded. */
        $this->assertSame($snapshot, $this->laundryEvidenceSnapshot($custody));
        $this->assertSame(1, (int) $this->headCase($custody)->late_days);
    }

    public function test_a_closed_legacy_transaction_may_archive_its_first_laundry_form_once(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::FRI)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $custody->fresh()->status);

        /*
         * An older record: the linen return was encoded and the transaction
         * closed before the accomplished form reached SPMU.
         */
        $job->refresh()->update([
            'status' => 'TURNED_OVER_TO_LAUNDRY',
            'latest_evidence_submission_id' => null,
            'form_verified_by_user_id' => null,
            'form_verified_at' => null,
        ]);
        $closedAt = $custody->fresh()->closed_at->toIso8601String();
        $quantities = $this->custodyQuantities($custody);
        $submissions = EvidenceSubmission::query()->count();

        $this->travel(30)->seconds();
        $this->upload($job, self::FRI)->assertSessionHasNoErrors();

        $job->refresh();
        $submission = EvidenceSubmission::query()->findOrFail($job->latest_evidence_submission_id);
        $this->assertSame($submissions + 1, EvidenceSubmission::query()->count());
        $this->assertSame($this->user(AccessClassification::SpmuOfficer)->id, (int) $submission->uploaded_by_user_id);
        $this->assertNotNull($submission->submitted_at);
        $this->assertNotNull($job->form_verified_at);
        $this->assertTrue($this->audited('LAUNDRY_SIGNED_FORM_ARCHIVED', $job));
        $this->assertSame('CLOSED', $custody->fresh()->status);
        $this->assertSame($closedAt, $custody->fresh()->closed_at->toIso8601String());
        $this->assertSame($quantities, $this->custodyQuantities($custody));

        /* A duplicate or racing second submission finds the form on file and is refused. */
        $snapshot = $this->laundryEvidenceSnapshot($custody);
        $this->travel(30)->seconds();
        $this->upload($job, self::FRI)->assertSessionHasErrors('evidence');
        $this->assertSame($snapshot, $this->laundryEvidenceSnapshot($custody));

        /* The scheduler's legacy reconciliation completes the job without reopening the transaction. */
        $closedNotices = NotificationEvent::query()->where('event_code', 'TRANSACTION_CLOSED')->count();
        $this->runScheduler(2);

        $this->assertSame('LAUNDRY_COMPLETED', $job->fresh()->status);
        $this->assertSame('CLOSED', $custody->fresh()->status);
        $this->assertSame($closedAt, $custody->fresh()->closed_at->toIso8601String());
        $this->assertSame($closedNotices, NotificationEvent::query()->where('event_code', 'TRANSACTION_CLOSED')->count());
        $this->assertSame($quantities, $this->custodyQuantities($custody));
    }

    public function test_a_closed_transaction_stays_closed_and_unchanged_through_scheduler_runs(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::FRI)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->assertSame('CLOSED', $custody->fresh()->status);

        $closedAt = $custody->fresh()->closed_at->toIso8601String();

        /* The first run only applies the existing one-time issuance lock. */
        $this->runScheduler();
        $this->assertNotNull($custody->fresh()->issuance_locked_at);
        $this->assertSame('CLOSED', $custody->fresh()->status);
        $this->assertSame($closedAt, $custody->fresh()->closed_at->toIso8601String());

        $snapshot = $this->laundryEvidenceSnapshot($custody);

        $this->runScheduler(2);
        $this->at(self::WED);
        $this->runScheduler();

        $this->assertSame($snapshot, $this->laundryEvidenceSnapshot($custody));
    }

    /** Custody, laundry evidence, quantities, inventory and accountability rows for one transaction. */
    private function laundryEvidenceSnapshot(CustodyTransaction $custody): array
    {
        $rows = fn ($query): array => $query->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $jobIds = DB::table('laundry_jobs')->where('custody_transaction_id', $custody->id)->pluck('id');

        return [
            'custody' => (array) DB::table('custody_transactions')->where('id', $custody->id)->first(),
            'laundry_jobs' => $rows(DB::table('laundry_jobs')->whereIn('id', $jobIds)),
            'laundry_job_lines' => $rows(DB::table('laundry_job_lines')->whereIn('laundry_job_id', $jobIds)),
            'evidence' => $rows(DB::table('evidence_submissions')->where('borrower_user_id', $custody->borrower_user_id)),
            'returns' => $rows(DB::table('return_transactions')->where('custody_transaction_id', $custody->id)),
            'overdue_cases' => $rows(DB::table('overdue_cases')->where('custody_transaction_id', $custody->id)),
            'incidents' => $rows(DB::table('incidents')->where('custody_transaction_id', $custody->id)),
            'penalties' => $rows(DB::table('penalties')->where('custody_transaction_id', $custody->id)),
            'billing_statements' => DB::table('billing_statements')->count(),
            'restrictions' => $rows(DB::table('borrower_restrictions')->where('borrower_user_id', $custody->borrower_user_id)),
            'quantities' => $this->custodyQuantities($custody),
        ];
    }

    private function custodyQuantities(CustodyTransaction $custody): array
    {
        $rows = fn ($query): array => $query->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();

        return [
            'custody_lines' => $rows(DB::table('custody_lines')->where('custody_transaction_id', $custody->id)),
            'inventory' => $rows(DB::table('inventory_items')),
        ];
    }

    public function test_friday_due_and_an_authorized_saturday_receipt_is_one_late_day(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->authorizeLaundry(self::SAT)->assertSessionHasNoErrors();

        $this->upload($job, self::SAT)->assertSessionHasNoErrors();
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $job->fresh()->receipt_date_status);

        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertNotNull($case);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame(50.0, (float) $case->rate_snapshot);
        $this->assertSame(self::SAT, $case->actual_return_date->toDateString());
        $this->assertSame('LAUNDRY_RECEIPT', $case->return_date_source);
        $this->assertSame([self::SAT], $this->lateReturns->chargeableLateDates($custody->fresh()));
    }

    public function test_saturday_authorized_and_monday_receipt_is_two_late_days(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->authorizeLaundry(self::SAT);

        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertSame(2, (int) $case->late_days);
        $this->assertSame(100.0, (float) $case->accrued_amount);
        $this->assertSame([self::SAT, self::MON], $this->lateReturns->chargeableLateDates($custody->fresh()));
    }

    public function test_closed_weekend_and_monday_receipt_is_one_late_day(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
    }

    public function test_saturday_authorized_sunday_closed_and_tuesday_receipt_is_three_late_days(): void
    {
        $this->at(self::TUE);
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->authorizeLaundry(self::SAT);

        $this->upload($job, self::TUE)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertSame(3, (int) $case->late_days);
        $this->assertSame(150.0, (float) $case->accrued_amount);
        $this->assertSame([self::SAT, self::MON, self::TUE], $this->lateReturns->chargeableLateDates($custody->fresh()));
    }

    public function test_a_saturday_open_for_both_spmu_and_laundry_is_counted_once(): void
    {
        OperationalDateException::query()->create([
            'exception_date' => self::SAT,
            'status' => 'OPEN',
            'allows_return' => true,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
            'reason' => 'Special Saturday operations.',
        ]);
        $this->authorizeLaundry(self::SAT);

        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $this->assertSame([self::SAT, self::MON], $this->lateReturns->chargeableLateDates($custody->fresh()));
        $this->assertSame(2, (int) $this->headCase($custody)->late_days);
    }

    public function test_an_spmu_closure_with_authorized_laundry_receiving_counts_for_linen_only(): void
    {
        /* Monday is a declared SPMU holiday, but the Laundry Area was authorized to receive. */
        OperationalDateException::query()->create([
            'exception_date' => self::MON,
            'status' => 'CLOSED',
            'reason' => 'Declared holiday.',
        ]);
        $this->authorizeLaundry(self::MON);
        $this->at(self::TUE);

        ['custody' => $linen, 'lines' => $linenLines, 'job' => $job] = $this->custody(linen: true);
        ['custody' => $equipment, 'lines' => $equipmentLines] = $this->custody(linen: false);

        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($linen, $linenLines)->assertSessionHasNoErrors();
        $this->encodeEquipment($equipment, $equipmentLines)->assertSessionHasNoErrors();

        /* Linen: Monday is an authorized Laundry day. Equipment returned Tuesday: Monday was closed for it. */
        $this->assertSame([self::MON], $this->lateReturns->chargeableLateDates($linen->fresh()));
        $this->assertSame([self::TUE], $this->lateReturns->chargeableLateDates($equipment->fresh()));
        $this->assertSame(1, (int) $this->headCase($linen)->late_days);
        $this->assertSame(1, (int) $this->headCase($equipment)->late_days);
    }

    public function test_equipment_never_gains_a_late_day_from_a_laundry_receiving_date(): void
    {
        ['custody' => $withLaundryDay, 'lines' => $linesA] = $this->custody(linen: false);
        $baseline = $this->lateReturns->chargeableLateDates($withLaundryDay, self::MON);

        $this->authorizeLaundry(self::SAT);
        $this->authorizeLaundry(self::SUN);

        /* Still out on Monday: only Monday counts, exactly as before the authorization. */
        $this->assertSame([self::MON], $baseline);
        $this->assertSame($baseline, $this->lateReturns->chargeableLateDates($withLaundryDay->fresh(), self::MON));
        $this->assertSame(1, app(AnalyticsService::class)->daysOverdue($withLaundryDay->fresh()));

        $this->encodeEquipment($withLaundryDay, $linesA)->assertSessionHasNoErrors();

        $case = $this->headCase($withLaundryDay);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame('RETURN_INSPECTION', $case->return_date_source);
    }

    public function test_mixed_custody_charges_each_kind_against_its_own_calendar(): void
    {
        $this->authorizeLaundry(self::SAT);
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true, nonLinen: true);

        /* Linen received on the authorized Saturday; equipment brought back on Monday. */
        $this->upload($job, self::SAT)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeEquipment($custody, $lines)->assertSessionHasNoErrors();

        /* Saturday is late because linen was still out; Monday because equipment was. Each date once. */
        $this->assertSame([self::SAT, self::MON], $this->lateReturns->chargeableLateDates($custody->fresh()));

        $case = $this->headCase($custody);
        $this->assertSame(2, (int) $case->late_days);
        $this->assertSame('MIXED_RETURN_COMPLETION', $case->return_date_source);
        $this->assertSame(self::MON, $case->actual_return_date->toDateString());
    }

    public function test_mixed_custody_with_equipment_back_on_time_is_late_only_for_the_linen_days(): void
    {
        $this->authorizeLaundry(self::SAT);
        $this->at(self::FRI);
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true, nonLinen: true);

        $this->encodeEquipment($custody, $lines)->assertSessionHasNoErrors();

        $this->at(self::TUE);
        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        /* Equipment contributes nothing; linen was out on the authorized Saturday and on Monday. */
        $this->assertSame([self::SAT, self::MON], $this->lateReturns->chargeableLateDates($custody->fresh()));
        $this->assertSame(2, (int) $this->headCase($custody)->late_days);
    }

    public function test_delayed_encoding_never_adds_late_days_or_changes_the_signed_date(): void
    {
        $this->authorizeLaundry(self::SAT);
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* The form reaches SPMU and is encoded more than a week after the signed Saturday. */
        $this->at('2026-11-18');
        $this->upload($job, self::SAT)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $this->assertSame(self::SAT, $job->fresh()->worker_received_at->toDateString());

        $case = $this->headCase($custody);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(self::SAT, $case->actual_return_date->toDateString());
        $this->assertSame(self::SAT, $custody->returns()->latest('id')->first()->received_at->toDateString());
    }

    public function test_effective_due_date_rollover_is_unchanged_by_laundry_authorization(): void
    {
        ['custody' => $custody] = $this->custody(linen: true, due: self::SAT);
        $calendar = app(OperationalCalendarService::class);

        $before = $calendar->synchronizeCustodyDueDate($custody->fresh())->due_at->toDateString();
        $this->authorizeLaundry(self::SAT);
        $after = $calendar->synchronizeCustodyDueDate($custody->fresh())->due_at->toDateString();

        /* A Saturday deadline still moves to Monday; a Laundry authorization does not open SPMU. */
        $this->assertSame(self::MON, $before);
        $this->assertSame($before, $after);
        $this->assertFalse($calendar->isOpenFor(OperationalCalendarService::RETURN, self::SAT.' 10:00:00'));
        $this->assertFalse($calendar->isOpenFor(OperationalCalendarService::PICKUP, self::SAT.' 10:00:00'));
        $this->assertTrue($calendar->isLinenReceivingDate(self::SAT));

        /* Measured from the effective Monday deadline: a Tuesday receipt is one late day. */
        $this->assertSame([self::TUE], $this->lateReturns->chargeableLateDates($custody->fresh(), self::TUE));
    }

    /* ------------------------------------------------------------------ */
    /* Policy 4 - signed receipt on an unauthorized closed day             */
    /* ------------------------------------------------------------------ */

    public function test_an_unauthorized_saturday_receipt_is_kept_and_blocks_finalization(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::SAT)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Pending Date Authorization'));

        $job->refresh();

        /* The evidence and the exact signed date are preserved; nothing was moved to Monday. */
        $this->assertSame(self::SAT, $job->worker_received_at->toDateString());
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $job->receipt_date_status);
        $this->assertTrue($job->receiptDatePendingAuthorization());
        $this->assertFalse($job->hasUsableReceiptDate());
        $this->assertNotNull($job->latest_evidence_submission_id);
        $this->assertSame(1, EvidenceSubmission::query()->where('generated_document_id', $job->generated_document_id)->count());
        $this->assertTrue($this->audited('LAUNDRY_RECEIPT_DATE_PENDING_AUTHORIZATION', $job));
        $this->assertSame(LateReturnService::LINEN_PENDING_DATE_AUTHORIZATION, $this->lateReturns->linenReturnState($custody->fresh()));

        /* The return cannot be encoded, so no late days, penalty or closure can come from this date. */
        $this->encodeLinen($custody, $lines)->assertSessionHasErrors('laundry_form');

        $this->assertSame(0.0, (float) $lines['linen']->fresh()->returned_quantity);
        $this->assertSame('FOR_LAUNDRY', $job->fresh()->status);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame([null, null], $this->lateReturns->actualReturn($custody->fresh()));
        $this->assertNull($this->lateReturns->assess($custody->fresh()));

        /* The scheduler finalizes nothing either, however often it runs. */
        $this->runScheduler(3);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(0, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame('ACTIVE', $custody->fresh()->status);

        /* The Action Officer sees why, with the form and the date together. */
        $this->officerGet(route('custody.return.show', $custody))
            ->assertOk()
            ->assertSee('Pending Date Authorization')
            ->assertSee('data-laundry-date-pending', false)
            ->assertSee('07 Nov 2026');
    }

    public function test_the_head_authorizing_the_date_releases_the_receipt_with_the_correct_late_days(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->upload($job, self::SAT);

        /* The Head sees the pending receipt with its form and date before deciding. */
        $this->headGet(route('policies.index', ['section' => 'special-dates']))
            ->assertOk()
            ->assertSee('Signed Receipts Pending Date Authorization')
            ->assertSee($custody->custody_no)
            ->assertSee('07 Nov 2026');

        $this->authorizeLaundry(self::SAT, 'Laundry Area was on duty for the weekend linen turnover.')
            ->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $job->receipt_date_status);
        $this->assertSame(self::SAT, $job->worker_received_at->toDateString());
        $this->assertTrue($this->audited('LAUNDRY_RECEIPT_DATE_STATUS_CHANGED', $job));

        /* Authorizing the day approved no penalty and closed nothing. */
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->where('status', LateReturnService::STATUS_FOR_HEAD_APPROVAL)->count());
        $this->assertSame(0.0, (float) $lines['linen']->fresh()->returned_quantity);

        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame(LateReturnService::STATUS_FOR_HEAD_APPROVAL, $case->status);
    }

    public function test_the_head_accepting_one_signed_receipt_counts_that_day_for_this_custody_only(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        ['custody' => $other, 'job' => $otherJob] = $this->custody(linen: true);
        ['custody' => $equipment] = $this->custody(linen: false);
        $this->upload($job, self::SAT);

        $note = 'Confirmed with Laundry Personnel that the linen was received on the signed date.';
        $this->headPost(route('laundry.spmu.accept-receipt-date', $job), ['review_note' => $note])
            ->assertSessionHasNoErrors();

        /* The decision, its reason, the actor and the time are kept; the signed date is untouched. */
        $job->refresh();
        $this->assertSame(LaundryJob::RECEIPT_DATE_ACCEPTED, $job->receipt_date_status);
        $this->assertSame($this->user(AccessClassification::SpmuHead)->id, $job->receipt_date_reviewed_by_user_id);
        $this->assertNotNull($job->receipt_date_reviewed_at);
        $this->assertSame($note, $job->receipt_date_review_note);
        $this->assertSame(self::SAT, $job->worker_received_at->toDateString());

        $accepted = AuditEvent::query()->where('action_code', 'LAUNDRY_RECEIPT_DATE_ACCEPTED')->where('record_id', $job->id)->sole();
        $this->assertSame(self::SAT, data_get($accepted->after_json, 'laundry_received_on'));
        $this->assertFalse((bool) data_get($accepted->after_json, 'day_authorized_for_laundry_receiving'));
        $this->assertTrue((bool) data_get($accepted->after_json, 'counted_as_late_day_for_this_custody_only'));

        /* Saturday is the one chargeable day for this custody. */
        $this->assertSame([self::SAT], $this->lateReturns->chargeableLateDates($custody->fresh()));

        /* It was not opened for anyone else: no calendar entry, nothing counted, and another Saturday receipt still waits. */
        $this->assertFalse(app(OperationalCalendarService::class)->isLinenReceivingDate(self::SAT));
        $this->assertSame(0, LaundryReceivingDay::query()->count());
        $this->assertSame([], $this->lateReturns->chargeableLateDates($other->fresh(), self::SAT));
        $this->assertSame([], $this->lateReturns->chargeableLateDates($equipment->fresh(), self::SUN));
        $this->assertSame([self::MON], $this->lateReturns->chargeableLateDates($equipment->fresh(), self::MON));
        $this->travel(30)->seconds();
        $this->upload($otherJob, self::SAT);
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $otherJob->fresh()->receipt_date_status);

        $this->travel(30)->seconds();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        /* Friday due, accepted Saturday receipt: one late day with the applicable fee. */
        $case = $this->headCase($custody);
        $this->assertNotNull($case);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame(self::SAT, $case->actual_return_date->toDateString());
        $this->assertTrue($this->audited('LATE_RETURN_DETECTED', $case));
    }

    public function test_both_head_decisions_charge_an_identical_saturday_receipt_the_same(): void
    {
        /* Receipt-specific acceptance first, while Saturday is not on the Laundry calendar. */
        ['custody' => $acceptedCustody, 'lines' => $acceptedLines, 'job' => $acceptedJob] = $this->custody(linen: true);
        $this->upload($acceptedJob, self::SAT);
        $this->headPost(route('laundry.spmu.accept-receipt-date', $acceptedJob), [
            'review_note' => 'Laundry Personnel confirmed receipt on the signed Saturday.',
        ])->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeLinen($acceptedCustody, $acceptedLines)->assertSessionHasNoErrors();

        /* The same receipt for another custody, released by authorizing the whole date. */
        ['custody' => $authorizedCustody, 'lines' => $authorizedLines, 'job' => $authorizedJob] = $this->custody(linen: true);
        $this->travel(30)->seconds();
        $this->upload($authorizedJob, self::SAT);
        $this->authorizeLaundry(self::SAT)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeLinen($authorizedCustody, $authorizedLines)->assertSessionHasNoErrors();

        $accepted = $this->headCase($acceptedCustody);
        $authorized = $this->headCase($authorizedCustody);

        $this->assertSame(1, (int) $accepted->late_days);
        $this->assertSame(1, (int) $authorized->late_days);
        $this->assertSame((float) $authorized->accrued_amount, (float) $accepted->accrued_amount);
        $this->assertSame(50.0, (float) $accepted->accrued_amount);

        /* Accepted and then authorized as well: still one day, never two. */
        $this->assertSame(LaundryJob::RECEIPT_DATE_ACCEPTED, $acceptedJob->fresh()->receipt_date_status);
        $this->assertSame([self::SAT], $this->lateReturns->chargeableLateDates($acceptedCustody->fresh()));
    }

    public function test_an_accepted_sunday_after_an_authorized_saturday_counts_each_eligible_day_once(): void
    {
        $this->authorizeLaundry(self::SAT)->assertSessionHasNoErrors();
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::SUN);
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $job->fresh()->receipt_date_status);
        $this->headPost(route('laundry.spmu.accept-receipt-date', $job), [
            'review_note' => 'Laundry Personnel confirmed receipt on the signed Sunday.',
        ])->assertSessionHasNoErrors();

        /* Saturday from the Laundry calendar, Sunday from this receipt alone. */
        $this->assertSame([self::SAT, self::SUN], $this->lateReturns->chargeableLateDates($custody->fresh()));
        $this->assertFalse(app(OperationalCalendarService::class)->isLinenReceivingDate(self::SUN));

        $this->travel(30)->seconds();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);
        $this->assertSame(2, (int) $case->late_days);
        $this->assertSame(100.0, (float) $case->accrued_amount);
        $this->assertSame(self::SUN, $case->actual_return_date->toDateString());
    }

    public function test_mixed_custody_with_an_accepted_receipt_counts_distinct_late_dates(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true, nonLinen: true);

        $this->upload($job, self::SAT);
        $this->headPost(route('laundry.spmu.accept-receipt-date', $job), [
            'review_note' => 'Laundry Personnel confirmed receipt on the signed Saturday.',
        ])->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeEquipment($custody, $lines)->assertSessionHasNoErrors();

        /* Saturday for the linen receipt, Monday for the equipment; each date once. */
        $this->assertSame([self::SAT, self::MON], $this->lateReturns->chargeableLateDates($custody->fresh()));
        $this->assertSame(2, (int) $this->headCase($custody)->late_days);
        $this->assertSame(100.0, (float) $this->headCase($custody)->accrued_amount);
    }

    public function test_finalized_late_days_stay_frozen_everywhere_after_calendar_changes(): void
    {
        $this->authorizeLaundry(self::SAT, 'Laundry Area on duty.');
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* Authorized Saturday, received Monday: two late days. */
        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $frozen = $this->headCase($custody)->only(['late_days', 'accrued_amount', 'rate_snapshot', 'status', 'actual_return_date']);
        $this->assertSame(2, (int) $frozen['late_days']);
        $this->assertSame(100.0, (float) $frozen['accrued_amount']);

        $agrees = function () use ($custody, $frozen): void {
            $case = OverdueCase::query()->where('custody_transaction_id', $custody->id)->sole();

            /* The stored assessment is untouched. */
            $this->assertEquals($frozen, $case->only(['late_days', 'accrued_amount', 'rate_snapshot', 'status', 'actual_return_date']));
            $this->assertSame(2, (int) $this->lateReturns->assessment($case)['late_days']);
            $this->assertSame(100.0, (float) $this->lateReturns->assessment($case)['amount']);

            /* Analytics and Borrower Obligations show the same finalized figure. */
            $this->assertSame(2, $this->lateReturns->finalizedLateDays($custody->fresh()));
            $this->assertSame(2, app(AnalyticsService::class)->lateDaysForReturn($custody->fresh(), self::MON));

            $row = collect(app(BorrowerObligationService::class)->obligationRows($custody->borrower_user_id))
                ->first(fn (array $row): bool => in_array($row['type'], ['Late Return', 'Overdue Return'], true));
            $this->assertNotNull($row);
            $this->assertSame('2 days late', $row['status_meta']);
        };

        $agrees();

        /* Saturday's authorization is revoked: a live recount would now say one day. */
        $day = LaundryReceivingDay::query()->sole();
        $this->headPost(route('policies.laundry-receiving-days.revoke', $day), [
            'revocation_reason' => 'Authorization was entered for the wrong weekend.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->lateReturns->chargeableLateDays($custody->fresh()));
        $agrees();

        /* Sunday is authorized afterwards: a live recount would say two days for another reason. */
        $this->travel(30)->seconds();
        $this->authorizeLaundry(self::SUN)->assertSessionHasNoErrors();
        $agrees();

        /* Both weekend days authorized: a live recount would say three. */
        $this->travel(30)->seconds();
        $this->authorizeLaundry(self::SAT, 'Confirmed correct after review.')->assertSessionHasNoErrors();
        $this->assertSame(3, $this->lateReturns->chargeableLateDays($custody->fresh()));
        $agrees();
    }

    public function test_an_unfinalized_return_keeps_using_the_live_counter(): void
    {
        ['custody' => $custody] = $this->custody(linen: false);

        $this->at(self::TUE);

        /* Still out, no finalized assessment: nothing to read, so the live counter applies. */
        $this->assertNull($this->lateReturns->finalizedLateDays($custody->fresh()));
        $this->assertSame(2, app(AnalyticsService::class)->daysOverdue($custody->fresh()));

        $this->runScheduler();
        $case = OverdueCase::query()->where('custody_transaction_id', $custody->id)->sole();
        $this->assertNull($case->actual_return_date);
        $this->assertNull($this->lateReturns->finalizedLateDays($custody->fresh()));
        $this->assertTrue((bool) $this->lateReturns->assessment($case)['is_estimate']);
        $this->assertSame(2, (int) $this->lateReturns->assessment($case)['late_days']);

        $row = collect(app(BorrowerObligationService::class)->obligationRows($custody->borrower_user_id))
            ->first(fn (array $row): bool => $row['type'] === 'Overdue Return');
        $this->assertNotNull($row);
        $this->assertSame('2 days late', $row['status_meta']);

        /* A day later the running figure moves on, as before. */
        $this->at(self::WED);
        $this->assertSame(3, app(AnalyticsService::class)->daysOverdue($custody->fresh()));
    }

    public function test_repeated_decisions_and_scheduler_runs_never_duplicate_cases_sanctions_or_history(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::SAT);
        $this->headPost(route('laundry.spmu.accept-receipt-date', $job), [
            'review_note' => 'Laundry Personnel confirmed receipt on the signed Saturday.',
        ])->assertSessionHasNoErrors();

        /* A second acceptance of the same receipt is refused. */
        $this->travel(30)->seconds();
        $this->headPost(route('laundry.spmu.accept-receipt-date', $job), [
            'review_note' => 'Second attempt to accept the same signed date.',
        ])->assertSessionHasErrors('receipt_date');

        /* The date is then authorized, twice. */
        $this->authorizeLaundry(self::SAT)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->authorizeLaundry(self::SAT, 'Authorized again by mistake.');
        $this->assertSame(1, LaundryReceivingDay::query()->count());

        $this->at(self::MON);
        $this->runScheduler(3);
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->runScheduler(3);

        $this->assertSame(1, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $case = $this->headCase($custody);
        $this->assertSame(1, (int) $case->late_days);
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame(0, Sanction::query()->count());

        /* One restriction per kind (outstanding return, then late-return settlement), never a repeat. */
        $restrictions = BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->pluck('restriction_type');
        $this->assertSame($restrictions->unique()->count(), $restrictions->count());
        $this->assertSame(1, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->where('restriction_type', 'OVERDUE_RETURN')->count());

        /* The Head's decision and the signed document history are all still on record. */
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'LAUNDRY_RECEIPT_DATE_ACCEPTED')->where('record_id', $job->id)->count());
        $this->assertTrue($this->audited('LAUNDRY_RECEIPT_DATE_PENDING_AUTHORIZATION', $job));
        $this->assertTrue($this->audited('LAUNDRY_SIGNED_FORM_ARCHIVED', $job));
        $this->assertSame(1, EvidenceSubmission::query()->where('generated_document_id', $job->generated_document_id)->count());
        $this->assertSame(LaundryJob::RECEIPT_DATE_ACCEPTED, $job->fresh()->receipt_date_status);
        $this->assertSame(self::SAT, $job->fresh()->worker_received_at->toDateString());
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'LATE_RETURN_DETECTED')->where('record_id', $case->id)->count());
    }


    public function test_a_date_typed_wrongly_can_be_recorded_again_with_the_earlier_evidence_kept(): void
    {
        ['custody' => $custody, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::SAT)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->upload($job, self::MON)->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame(self::MON, $job->worker_received_at->toDateString());
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $job->receipt_date_status);

        /* Both uploads are still on file, and the audit trail shows the earlier date. */
        $this->assertSame(2, EvidenceSubmission::query()->where('generated_document_id', $job->generated_document_id)->count());
        $archived = AuditEvent::query()
            ->where('action_code', 'LAUNDRY_SIGNED_FORM_ARCHIVED')
            ->where('record_id', $job->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $archived);
        $this->assertSame(self::SAT, data_get($archived[1]->after_json, 'previous_laundry_received_on'));
        $this->assertSame(self::MON, data_get($archived[1]->after_json, 'physical_laundry_received_on'));
        $this->assertSame(1, LaundryJob::query()->where('custody_transaction_id', $custody->id)->count());
    }

    public function test_the_same_form_submitted_twice_keeps_one_consistent_receipt(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* An immediate identical resubmission is stopped by the duplicate-submission guard. */
        $this->upload($job, self::MON)->assertSessionHasNoErrors();
        $this->upload($job, self::MON);
        $this->travel(30)->seconds();
        $this->upload($job, self::MON)->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame(self::MON, $job->worker_received_at->toDateString());
        $this->assertSame(1, LaundryJob::query()->where('custody_transaction_id', $custody->id)->count());

        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->encodeLinen($custody, $lines);

        /* One return, one case, the late day counted once. */
        $this->assertSame(1, $custody->returns()->count());
        $this->assertSame(1, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(1, (int) $this->headCase($custody)->late_days);
    }

    public function test_the_fallback_date_entry_point_applies_the_same_rule_and_keeps_the_date(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* A form on file with no Laundry Received date yet: the return form's own date field is used. */
        $this->upload($job, self::MON);
        $job->update(['worker_received_at' => null, 'receipt_date_status' => null]);

        $this->encodeLinen($custody, $lines, ['laundry_received_date' => self::SAT])
            ->assertSessionHasErrors('laundry_form');

        $job->refresh();
        $this->assertSame(self::SAT, $job->worker_received_at->toDateString());
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $job->receipt_date_status);
        $this->assertSame(0.0, (float) $lines['linen']->fresh()->returned_quantity);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());

        /* A borrower cannot use this entry point at all. */
        $this->withSession(['active_workspace' => 'BORROWER'])->actingAs($custody->borrower)
            ->post(route('custody.return', $custody), [
                'accounting' => [$lines['linen']->id => ['FINE' => 3]],
                'laundry_received_date' => self::SAT,
            ])->assertForbidden();

        /* Once the date is authorized the same entry point finalizes with the right count. */
        $this->authorizeLaundry(self::SAT);
        $this->travel(30)->seconds();
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $this->headCase($custody)->late_days);
    }

    public function test_the_fallback_entry_point_with_an_open_day_finalizes_normally(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::MON);
        $job->update(['worker_received_at' => null, 'receipt_date_status' => null]);

        $this->encodeLinen($custody, $lines, ['laundry_received_date' => self::MON])->assertSessionHasNoErrors();

        $this->assertSame(self::MON, $job->fresh()->worker_received_at->toDateString());
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $job->fresh()->receipt_date_status);
        $this->assertSame(1, (int) $this->headCase($custody)->late_days);
    }

    /* ------------------------------------------------------------------ */
    /* Policy 5 - who may authorize a Laundry receiving day                */
    /* ------------------------------------------------------------------ */

    public function test_only_the_spmu_head_can_authorize_or_revoke_a_laundry_receiving_date(): void
    {
        foreach ([AccessClassification::SpmuOfficer, AccessClassification::BorrowerOnly, AccessClassification::IctuMaintainer] as $classification) {
            $response = $this->actingAsRole($classification)->post(route('policies.laundry-receiving-days.store'), [
                'receiving_date' => self::SAT,
                'reason' => 'Attempted by a role that may not authorize.',
            ]);

            $this->assertContains($response->getStatusCode(), [302, 403], $classification->value);
            $this->assertSame(0, LaundryReceivingDay::query()->count(), $classification->value);
        }

        $this->authorizeLaundry(self::SAT, 'Laundry Area on duty for the linen turnover.')->assertSessionHasNoErrors();

        $day = LaundryReceivingDay::query()->sole();
        $head = $this->user(AccessClassification::SpmuHead);
        $this->assertTrue($day->is_authorized);
        $this->assertSame(self::SAT, $day->receiving_date->toDateString());
        $this->assertSame($head->id, $day->authorized_by_user_id);
        $this->assertNotNull($day->authorized_at);
        $this->assertSame('Laundry Area on duty for the linen turnover.', $day->reason);
        $this->assertTrue($this->audited('LAUNDRY_RECEIVING_DAY_AUTHORIZED', $day));

        /* The Action Officer cannot revoke it either. */
        $this->actingAsRole(AccessClassification::SpmuOfficer)
            ->post(route('policies.laundry-receiving-days.revoke', $day), ['revocation_reason' => 'Not permitted.'])
            ->assertForbidden();
        $this->assertTrue($day->fresh()->is_authorized);

        /* A reason is required, and the same date cannot be authorized twice. */
        $this->headPost(route('policies.laundry-receiving-days.store'), ['receiving_date' => self::SUN])
            ->assertSessionHasErrors('reason');
        $this->headPost(route('policies.laundry-receiving-days.store'), ['receiving_date' => self::SAT, 'reason' => 'Duplicate attempt.'])
            ->assertSessionHasErrors('receiving_date');
        $this->assertSame(1, LaundryReceivingDay::query()->count());
    }

    public function test_the_action_officer_cannot_bypass_authorization_with_crafted_requests(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->upload($job, self::SAT);

        /* Extra fields on the upload cannot set the status or the review. */
        $this->travel(30)->seconds();
        $this->officerPost(route('laundry.spmu.upload-form', $job), [
            'evidence' => UploadedFile::fake()->create('form.pdf', 20, 'application/pdf'),
            'laundry_received_on' => self::SAT,
            'receipt_date_status' => LaundryJob::RECEIPT_DATE_AUTHORIZED,
            'receipt_date_reviewed_by_user_id' => 1,
            'is_authorized' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $job->fresh()->receipt_date_status);

        /* The Head-only acceptance endpoint refuses the Action Officer. */
        $this->officerPost(route('laundry.spmu.accept-receipt-date', $job), ['review_note' => 'Trying to accept my own entry.'])
            ->assertForbidden();
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $job->fresh()->receipt_date_status);

        /* Extra fields on the return form cannot finalize it either. */
        $this->encodeLinen($custody, $lines, ['receipt_date_status' => LaundryJob::RECEIPT_DATE_AUTHORIZED])
            ->assertSessionHasErrors('laundry_form');
        $this->assertSame(0.0, (float) $lines['linen']->fresh()->returned_quantity);
        $this->assertSame(0, LaundryReceivingDay::query()->count());
    }

    public function test_revoking_keeps_the_history_and_never_alters_a_finalized_case(): void
    {
        $this->authorizeLaundry(self::SAT, 'Laundry Area on duty.');
        ['custody' => $finalized, 'lines' => $finalizedLines, 'job' => $finalizedJob] = $this->custody(linen: true);
        ['custody' => $waiting, 'job' => $waitingJob] = $this->custody(linen: true);

        $this->upload($finalizedJob, self::SAT);
        $this->encodeLinen($finalized, $finalizedLines)->assertSessionHasNoErrors();
        $this->travel(30)->seconds();
        $this->upload($waitingJob, self::SAT);

        $before = $this->headCase($finalized)->only(['late_days', 'accrued_amount', 'rate_snapshot', 'status', 'actual_return_date']);
        $day = LaundryReceivingDay::query()->sole();

        $this->headPost(route('policies.laundry-receiving-days.revoke', $day), [
            'revocation_reason' => 'Authorization was entered for the wrong weekend.',
        ])->assertSessionHasNoErrors();

        /* The decision is not hidden: the row and both audit events remain. */
        $day->refresh();
        $this->assertFalse($day->is_authorized);
        $this->assertSame('Laundry Area on duty.', $day->reason);
        $this->assertSame('Authorization was entered for the wrong weekend.', $day->revocation_reason);
        $this->assertNotNull($day->revoked_at);
        $this->assertNotNull($day->authorized_at);
        $this->assertTrue($this->audited('LAUNDRY_RECEIVING_DAY_AUTHORIZED', $day));
        $this->assertTrue($this->audited('LAUNDRY_RECEIVING_DAY_REVOKED', $day));
        $this->headGet(route('policies.index', ['section' => 'special-dates']))
            ->assertOk()->assertSee('Revoked')->assertSee('Authorization was entered for the wrong weekend.');

        /* The finalized case is frozen. */
        $this->assertEquals($before, $this->headCase($finalized)->only(['late_days', 'accrued_amount', 'rate_snapshot', 'status', 'actual_return_date']));
        $this->assertSame(1, (int) $this->lateReturns->assessment($this->headCase($finalized))['late_days']);
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $finalizedJob->fresh()->receipt_date_status);

        /* The receipt that was not encoded yet goes back to pending. */
        $this->assertSame(LaundryJob::RECEIPT_DATE_PENDING, $waitingJob->fresh()->receipt_date_status);
        $this->assertSame(self::SAT, $waitingJob->fresh()->worker_received_at->toDateString());

        /* Re-authorizing reuses the same record rather than creating a second one. */
        $this->headPost(route('policies.laundry-receiving-days.store'), ['receiving_date' => self::SAT, 'reason' => 'Confirmed correct after review.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, LaundryReceivingDay::query()->count());
        $this->assertTrue($day->fresh()->is_authorized);
        $this->assertSame(LaundryJob::RECEIPT_DATE_AUTHORIZED, $waitingJob->fresh()->receipt_date_status);
        $this->assertSame(2, AuditEvent::query()->where('action_code', 'LAUNDRY_RECEIVING_DAY_AUTHORIZED')->count());
    }

    /* ------------------------------------------------------------------ */
    /* Policy 3 - linen with no return evidence                            */
    /* ------------------------------------------------------------------ */

    public function test_linen_with_no_evidence_is_flagged_provisionally_on_the_first_eligible_day_only(): void
    {
        ['custody' => $custody] = $this->custody(linen: true);

        /* Saturday: SPMU closed and no Laundry authorization, so no eligible receiving day has passed. */
        $this->at(self::SAT);
        $this->runScheduler(2);
        $this->assertSame(LateReturnService::LINEN_AWAITING_RETURN, $this->lateReturns->linenReturnState($custody->fresh()));
        $this->assertFalse($this->audited('LINEN_RETURN_PROVISIONALLY_OVERDUE', $custody));

        /* Monday is the first eligible receiving day after the deadline. */
        $this->at(self::MON);
        $this->runScheduler(3);

        $this->assertSame(LateReturnService::LINEN_PROVISIONALLY_OVERDUE, $this->lateReturns->linenReturnState($custody->fresh()));

        /* Identified once, however many times the scheduler ran. */
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'LINEN_RETURN_PROVISIONALLY_OVERDUE')->where('record_id', $custody->id)->count());
        $this->assertSame(1, NotificationEvent::query()->where('event_code', 'LINEN_RETURN_PROVISIONALLY_OVERDUE')->where('source_id', $custody->id)->count());

        /* Missing paperwork creates no penalty, violation, sanction or restriction. */
        $this->assertSame('ACTIVE', $custody->fresh()->status);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(0, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(0, Sanction::query()->count());

        /* Visible in the Action Officer's work queue and on the return workspace, as provisional. */
        $this->officerGet(route('custody.return.index'))
            ->assertOk()->assertSee('Pending Laundry Form - Return Date Unverified');
        $this->officerGet(route('custody.return.show', $custody))
            ->assertOk()
            ->assertSee('Laundry Form Pending')
            ->assertSee('name="laundry_received_on"', false)
            ->assertDontSee('Confirmed Not Returned');

        /* In overdue monitoring, identified as provisional rather than confirmed. */
        $aging = app(AnalyticsService::class)->overdueAging(null, null);
        $this->assertSame(1, $aging['total']);
        $this->assertSame(1, $aging['provisional']);
        $current = app(AnalyticsService::class)->currentOverdue(null, null, 5);
        $this->assertSame('Pending Laundry Form - Return Date Unverified', $current['cases'][0]['linen_state_label']);
        $this->assertSame(1, $current['cases'][0]['days_overdue']);
    }

    public function test_an_authorized_laundry_day_is_the_first_eligible_day_for_provisional_status(): void
    {
        ['custody' => $custody] = $this->custody(linen: true);
        $this->authorizeLaundry(self::SAT);

        $this->at(self::SAT);
        $this->runScheduler();

        $this->assertSame(LateReturnService::LINEN_PROVISIONALLY_OVERDUE, $this->lateReturns->linenReturnState($custody->fresh()));
        $this->assertTrue($this->audited('LINEN_RETURN_PROVISIONALLY_OVERDUE', $custody));
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
    }

    public function test_confirmed_not_returned_linen_enters_the_regular_overdue_process_without_duplicates(): void
    {
        ['custody' => $custody, 'job' => $job] = $this->custody(linen: true);
        $this->authorizeLaundry(self::SAT);
        $this->at(self::TUE);
        $this->runScheduler();

        /* Only the Action Officer verifies; a reason is required; the Head cannot do it on their behalf. */
        $this->headPost(route('laundry.spmu.confirm-not-returned', $job), ['unreturned_reason' => 'Head attempting the AO verification step.'])
            ->assertForbidden();
        $this->officerPost(route('laundry.spmu.confirm-not-returned', $job), ['unreturned_reason' => 'short'])
            ->assertSessionHasErrors('unreturned_reason');
        $this->assertNull($job->fresh()->unreturned_confirmed_at);

        $this->officerPost(route('laundry.spmu.confirm-not-returned', $job), [
            'unreturned_reason' => 'Laundry Personnel confirmed on 10 Nov that no linen was received for this transaction.',
        ])->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertNotNull($job->unreturned_confirmed_at);
        $this->assertSame($this->user(AccessClassification::SpmuOfficer)->id, $job->unreturned_confirmed_by_user_id);
        $this->assertTrue($this->audited('LINEN_CONFIRMED_NOT_RETURNED', $custody));
        $this->assertSame(LateReturnService::LINEN_CONFIRMED_UNRETURNED, $this->lateReturns->linenReturnState($custody->fresh()));

        /* The confirmation itself created no penalty or restriction. */
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(0, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->count());

        /* The regular overdue process then applies, once, however often the scheduler runs. */
        $this->runScheduler(3);

        $this->assertSame('OVERDUE', $custody->fresh()->status);
        $this->assertSame(1, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(1, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->where('status', 'ACTIVE')->count());
        $this->assertSame(0, Sanction::query()->count());

        /* Sat (authorized Laundry), Mon, Tue: every measure reports the same three days. */
        $case = OverdueCase::query()->where('custody_transaction_id', $custody->id)->sole();
        $this->assertSame(LateReturnService::STATUS_OVERDUE, $case->status);
        $this->assertSame(150.0, (float) $case->accrued_amount);
        $this->assertSame(3, $this->lateReturns->chargeableLateDays($custody->fresh()));
        $this->assertSame(3, (int) $this->lateReturns->assessment($case->fresh())['late_days']);
        $this->assertSame(150.0, (float) $this->lateReturns->assessment($case->fresh())['amount']);
        $this->assertSame(3, app(AnalyticsService::class)->daysOverdue($custody->fresh()));
        $this->assertSame(0, app(AnalyticsService::class)->overdueAging(null, null)['provisional']);

        /* Cannot be confirmed twice. */
        $this->travel(30)->seconds();
        $this->officerPost(route('laundry.spmu.confirm-not-returned', $job), [
            'unreturned_reason' => 'Second attempt to confirm the same finding again.',
        ])->assertSessionHasErrors('unreturned_reason');
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'LINEN_CONFIRMED_NOT_RETURNED')->count());
    }

    public function test_linen_confirmed_not_returned_that_later_comes_back_is_measured_to_the_signed_date(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);
        $this->at(self::TUE);
        $this->runScheduler();
        $this->officerPost(route('laundry.spmu.confirm-not-returned', $job), [
            'unreturned_reason' => 'Laundry Personnel confirmed that no linen was received for this transaction.',
        ])->assertSessionHasNoErrors();
        $this->runScheduler();
        $this->assertSame('OVERDUE', $custody->fresh()->status);

        /* The linen is brought back on Wednesday and the signed form is encoded a day later. */
        $this->at('2026-11-12');
        $this->upload($job, self::WED)->assertSessionHasNoErrors();
        $this->runScheduler();

        /* Awaiting encoding: the running count already stops at the signed date (Mon, Tue, Wed). */
        $this->assertSame(LateReturnService::LINEN_RECEIVED_LATE, $this->lateReturns->linenReturnState($custody->fresh()));
        $this->assertSame(3, app(AnalyticsService::class)->daysOverdue($custody->fresh()));
        $this->assertSame(3, $this->lateReturns->chargeableLateDays($custody->fresh()));

        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = OverdueCase::query()->where('custody_transaction_id', $custody->id)->sole();
        $this->assertSame(LateReturnService::STATUS_FOR_HEAD_APPROVAL, $case->status);
        $this->assertSame(3, (int) $case->late_days);
        $this->assertSame(self::WED, $case->actual_return_date->toDateString());
    }

    /* ------------------------------------------------------------------ */
    /* Scheduler and Analytics with evidence already on file               */
    /* ------------------------------------------------------------------ */

    public function test_an_on_time_receipt_awaiting_encoding_is_never_shown_or_processed_as_overdue(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* Signed on the Friday deadline; the form reaches SPMU on Monday and is encoded on Wednesday. */
        $this->upload($job, self::FRI)->assertSessionHasNoErrors();
        $this->runScheduler(3);

        $this->assertSame(LateReturnService::LINEN_RECEIVED_ON_TIME, $this->lateReturns->linenReturnState($custody->fresh()));
        $this->assertSame('ACTIVE', $custody->fresh()->status);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->where('status', '!=', 'RESOLVED')->count());
        $this->assertSame(0, BorrowerRestriction::query()->where('custody_transaction_id', $custody->id)->where('status', 'ACTIVE')->count());
        $this->assertFalse($this->audited('LINEN_RETURN_PROVISIONALLY_OVERDUE', $custody));

        $analytics = app(AnalyticsService::class);
        $this->assertSame(0, $analytics->overdueAging(null, null)['total']);
        $this->assertSame(0, $analytics->currentOverdue(null, null, 5)['total']);
        $this->assertSame(0, $analytics->daysOverdue($custody->fresh()));
        $this->assertSame(0, $analytics->returns(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')->endOfDay(), null, null)['overdue']);

        $this->at(self::WED);
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $this->assertNull($this->headCase($custody));
        $this->assertSame(0, $this->lateReturns->chargeableLateDays($custody->fresh()));
    }

    public function test_a_late_receipt_awaiting_encoding_stops_aging_at_the_signed_date(): void
    {
        $this->authorizeLaundry(self::SAT);
        ['custody' => $custody, 'job' => $job] = $this->custody(linen: true);

        /* Signed on the authorized Saturday; uploaded on Monday; still not encoded the following week. */
        $this->upload($job, self::SAT)->assertSessionHasNoErrors();
        $this->at('2026-11-17');
        $this->runScheduler(2);

        $this->assertSame(LateReturnService::LINEN_RECEIVED_LATE, $this->lateReturns->linenReturnState($custody->fresh()));

        /* One late day (Saturday), not the seven eligible days that have passed since. */
        $analytics = app(AnalyticsService::class);
        $this->assertSame(1, $analytics->daysOverdue($custody->fresh()));
        $this->assertSame(1, $analytics->overdueAging(null, null)['groups'][0]['count']);
        $this->assertSame(0, $analytics->overdueAging(null, null)['provisional']);

        $case = OverdueCase::query()->where('custody_transaction_id', $custody->id)->sole();
        $this->assertSame(50.0, (float) $case->accrued_amount);
        $this->assertSame(1, (int) $this->lateReturns->assessment($case)['late_days']);
    }

    /* ------------------------------------------------------------------ */
    /* Historical records and legacy calendar rows                         */
    /* ------------------------------------------------------------------ */

    public function test_historical_receipts_and_legacy_special_dates_keep_their_meaning(): void
    {
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        /* A completed return from before this check existed: closed-day receipt, no status recorded. */
        $job->update([
            'worker_received_at' => Carbon::parse(self::SAT),
            'receipt_date_status' => null,
            'status' => 'LAUNDRY_COMPLETED',
        ]);
        $lines['linen']->update(['returned_quantity' => 3]);
        $custody->update(['status' => 'CLOSED', 'closed_at' => Carbon::parse(self::MON)]);

        $job->refresh();
        $this->assertFalse($job->receiptDatePendingAuthorization());
        $this->assertTrue($job->hasUsableReceiptDate());
        $this->assertNull($job->receiptDateStatusLabel());
        $this->assertSame(LateReturnService::LINEN_RETURN_ENCODED, $this->lateReturns->linenReturnState($custody->fresh()));

        /* Authorizing or revoking that date later never rewrites the completed record. */
        $this->authorizeLaundry(self::SAT);
        $this->headPost(route('policies.laundry-receiving-days.revoke', LaundryReceivingDay::query()->sole()), ['revocation_reason' => 'Entered in error.']);
        $this->runScheduler();

        $job->refresh();
        $this->assertNull($job->receipt_date_status);
        $this->assertSame(self::SAT, $job->worker_received_at->toDateString());
        $this->assertSame('LAUNDRY_COMPLETED', $job->status);
        $this->assertSame('CLOSED', $custody->fresh()->status);
        $this->assertSame(0, OverdueCase::query()->where('custody_transaction_id', $custody->id)->count());
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'LAUNDRY_RECEIPT_DATE_STATUS_CHANGED')->count());

        /* A legacy Special Date still works exactly as before for equipment and for linen. */
        OperationalDateException::query()->create([
            'exception_date' => self::SUN,
            'status' => 'OPEN',
            'allows_return' => true,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
            'reason' => 'Special Sunday operations.',
        ]);
        $calendar = app(OperationalCalendarService::class);
        $this->assertSame(2, $calendar->countOpenTransactionDays(OperationalCalendarService::RETURN, self::FRI, self::MON));
        $this->assertSame([self::SUN, self::MON], $calendar->linenReceivingDates(self::FRI, self::MON));
    }

    /* ------------------------------------------------------------------ */
    /* Head approval and audit trail are preserved                         */
    /* ------------------------------------------------------------------ */

    public function test_a_late_linen_return_still_goes_through_head_approval_with_its_evidence_shown(): void
    {
        $this->authorizeLaundry(self::SAT, 'Laundry Area on duty.');
        ['custody' => $custody, 'lines' => $lines, 'job' => $job] = $this->custody(linen: true);

        $this->upload($job, self::SAT);
        $this->encodeLinen($custody, $lines)->assertSessionHasNoErrors();

        $case = $this->headCase($custody);

        /* Authorizing the day did not approve the fee: the case waits for the Head as before. */
        $this->assertSame(LateReturnService::STATUS_FOR_HEAD_APPROVAL, $case->status);
        $this->assertSame(0, $case->penalties()->count());
        $this->assertTrue($this->audited('LATE_RETURN_DETECTED', $case));
        $this->assertSame(1, NotificationEvent::query()->where('event_code', 'LATE_RETURN_FOR_HEAD_APPROVAL')->where('source_id', $case->id)->count());

        $detected = AuditEvent::query()->where('action_code', 'LATE_RETURN_DETECTED')->where('record_id', $case->id)->sole();
        $this->assertSame(1, (int) data_get($detected->after_json, 'late_days'));
        $this->assertSame(self::SAT, data_get($detected->after_json, 'actual_return_date'));

        /* The Head sees the signed date, its authorization and the form on the review screen. */
        $this->headGet(route('accountability.index', ['view' => 'cases']))
            ->assertOk()
            ->assertSee('data-late-return-laundry-evidence', false)
            ->assertSee('authorized Laundry receiving day');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function at(string $date, int $hour = 10): void
    {
        $this->travelTo(Carbon::parse($date)->setTime($hour, 0));
    }

    private function user(AccessClassification $classification): User
    {
        return User::query()->where('access_classification', $classification->value)->firstOrFail();
    }

    private function actingAsRole(AccessClassification $classification): static
    {
        $workspace = match ($classification) {
            AccessClassification::BorrowerOnly => 'BORROWER',
            AccessClassification::IctuMaintainer => 'ICTU',
            default => 'SPMU',
        };

        return $this->withSession(['active_workspace' => $workspace])->actingAs($this->user($classification));
    }

    private function headPost(string $url, array $data = []): TestResponse
    {
        return $this->actingAsRole(AccessClassification::SpmuHead)->post($url, $data);
    }

    private function headGet(string $url): TestResponse
    {
        return $this->actingAsRole(AccessClassification::SpmuHead)->get($url);
    }

    private function officerPost(string $url, array $data = []): TestResponse
    {
        return $this->actingAsRole(AccessClassification::SpmuOfficer)->post($url, $data);
    }

    private function officerGet(string $url): TestResponse
    {
        return $this->actingAsRole(AccessClassification::SpmuOfficer)->get($url);
    }

    private function authorizeLaundry(string $date, string $reason = 'Laundry Area authorized to receive linen on this date.'): TestResponse
    {
        return $this->headPost(route('policies.laundry-receiving-days.store'), [
            'receiving_date' => $date,
            'reason' => $reason,
        ]);
    }

    private function upload(LaundryJob $job, string $receivedOn): TestResponse
    {
        return $this->officerPost(route('laundry.spmu.upload-form', $job), [
            'evidence' => UploadedFile::fake()->create('accomplished-laundry-form.pdf', 20, 'application/pdf'),
            'laundry_received_on' => $receivedOn,
        ]);
    }

    private function encodeLinen(CustodyTransaction $custody, array $lines, array $extra = []): TestResponse
    {
        return $this->officerPost(route('custody.return', $custody), array_merge([
            'accounting' => [$lines['linen']->id => ['FINE' => 3]],
        ], $extra));
    }

    private function encodeEquipment(CustodyTransaction $custody, array $lines): TestResponse
    {
        return $this->officerPost(route('custody.return', $custody), [
            'accounting' => [$lines['non_linen']->id => ['FINE' => 2]],
        ]);
    }

    private function runScheduler(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->artisan('spmu:process-deadlines')->assertSuccessful();
        }
    }

    /** The late-return case waiting for the Head, if the return was classified as late. */
    private function headCase(CustodyTransaction $custody): ?OverdueCase
    {
        return OverdueCase::query()
            ->where('custody_transaction_id', $custody->id)
            ->where('status', LateReturnService::STATUS_FOR_HEAD_APPROVAL)
            ->first();
    }

    private function audited(string $actionCode, $record): bool
    {
        return AuditEvent::query()
            ->where('action_code', $actionCode)
            ->where('record_id', $record->getKey())
            ->exists();
    }

    /**
     * A released custody, still fully outstanding, in the shape
     * CustodyService::release() leaves behind. Released Tue 3 Nov, due Fri 6 Nov.
     *
     * @return array{custody: CustodyTransaction, lines: array<string, CustodyLine>, job: ?LaundryJob}
     */
    private function custody(bool $linen, bool $nonLinen = false, string $due = self::FRI): array
    {
        $borrower = $this->user(AccessClassification::BorrowerOnly);
        $released = Carbon::parse('2026-11-03 09:00:00');
        $dueAt = Carbon::parse($due)->endOfDay();

        $request = BorrowingRequest::create([
            'request_no' => 'BR-LAUNDRY-'.uniqid(),
            'borrower_user_id' => $borrower->id,
            'accountable_unit_id' => $borrower->organizational_unit_id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);

        $version = $request->versions()->create([
            'version_no' => 1,
            'purpose_event' => 'Laundry receiving calendar test',
            'location' => 'CSPC Campus',
            'needed_from' => $released,
            'return_due_at' => $dueAt,
            'event_details' => 'Borrower returns linen through the Laundry Area first.',
            'off_campus' => false,
            'created_by_user_id' => $borrower->id,
        ]);

        $custody = CustodyTransaction::create([
            'custody_no' => 'CUS-LAUNDRY-'.uniqid(),
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $borrower->id,
            'status' => 'ACTIVE',
            'released_at' => $released,
            'due_at' => $dueAt,
        ]);

        $lines = [];
        $job = null;

        if ($linen) {
            $lines['linen'] = $this->custodyLine($custody, $version, $this->inventoryItem(true), 3);

            $job = LaundryJob::create([
                'custody_transaction_id' => $custody->id,
                'generated_document_id' => $this->laundryFormDocument($custody, $version)->id,
                'status' => 'FOR_LAUNDRY',
            ]);

            LaundryJobLine::create([
                'laundry_job_id' => $job->id,
                'custody_line_id' => $lines['linen']->id,
                'issued_quantity' => 3,
                'affected_quantity' => 0,
            ]);
        }

        if ($nonLinen || ! $linen) {
            $lines['non_linen'] = $this->custodyLine($custody, $version, $this->inventoryItem(false), 2);
        }

        return ['custody' => $custody->fresh(['lines', 'borrower']), 'lines' => $lines, 'job' => $job];
    }

    private function custodyLine(CustodyTransaction $custody, $version, InventoryItem $item, int $quantity): CustodyLine
    {
        $requestItem = RequestItem::create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description,
            'unit_snapshot' => $item->unit->unit_name,
            'requested_quantity' => $quantity,
            'approved_quantity' => $quantity,
            'use_location' => 'ON_CAMPUS',
        ]);

        $allocation = Allocation::create([
            'request_item_id' => $requestItem->id,
            'period_start' => $version->needed_from,
            'period_end' => $version->return_due_at,
            'allocated_quantity' => $quantity,
            'released_quantity' => $quantity,
            'restored_quantity' => 0,
            'status' => 'RELEASED',
            'allocated_at' => $version->needed_from,
        ]);

        return CustodyLine::create([
            'custody_transaction_id' => $custody->id,
            'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id,
            'approved_quantity' => $quantity,
            'quantity_to_receive' => $quantity,
            'actual_released_quantity' => $quantity,
            'returned_quantity' => 0,
            'release_condition' => 'SERVICEABLE',
            'item_status' => 'RELEASED_PENDING_RETURN',
            'compliance_status' => $item->laundry_required ? 'LAUNDRY_FORM_READY' : 'NOT_REQUIRED',
        ]);
    }

    private function laundryFormDocument(CustodyTransaction $custody, $version): GeneratedDocument
    {
        $bytes = '%PDF-1.4 travelling laundry form';
        $path = 'tests/laundry/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, $bytes);

        $storedFile = StoredFile::create([
            'uploaded_by_user_id' => null,
            'disk' => 'local',
            'storage_path' => $path,
            'original_name' => 'laundry-form.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'classification' => 'CONTROLLED_DOCUMENT',
        ]);

        return GeneratedDocument::create([
            'stored_file_id' => $storedFile->id,
            'request_version_id' => $version->id,
            'subject_type' => CustodyTransaction::class,
            'subject_id' => $custody->id,
            'document_no' => 'DOC-LAUNDRY-'.uniqid(),
            'document_type' => 'LAUNDRY_FORM',
            'version_no' => 1,
            'sha256' => $storedFile->sha256,
            'status' => 'FINAL',
            'generated_at' => now(),
        ]);
    }

    private function inventoryItem(bool $laundryRequired): InventoryItem
    {
        return InventoryItem::query()
            ->where('active', true)
            ->where('borrowable', true)
            ->where('laundry_required', $laundryRequired)
            ->firstOrFail();
    }
}
