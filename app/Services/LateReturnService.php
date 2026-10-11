<?php

namespace App\Services;

use App\Models\BorrowerRestriction;
use App\Models\CustodyTransaction;
use App\Models\LaundryJob;
use App\Models\OverdueCase;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The late-return lifecycle.
 *
 * OVERDUE and LATE RETURN are different states and are never merged:
 *
 *   OVERDUE       the item is past its effective return date and has NOT been
 *                 returned. The amount shown is an estimate that grows only on open Return-processing days.
 *
 *   LATE RETURN   the item HAS been returned, after its effective return date.
 *                 The late days and the fee are frozen at the physical return
 *                 date and never move again.
 *
 * This service does not perform returns. It reads the authoritative result of
 * the existing return workflows and classifies it:
 *
 *   linen       the Laundry Personnel receipt (laundry_jobs.worker_received_at)
 *   non-linen   the AO Return Inspection (return_transactions.received_at)
 *   mixed       the later of those two authoritative physical-return dates
 *
 * For linen the Action Officer's later attestation of the accomplished Laundry
 * Form is a document step, not a physical return, so it must never add late
 * days. For mixed custody, however, the custody is not physically complete
 * until BOTH return channels are complete, so lateness is measured using the
 * later authoritative date. A borrower is never charged for internal document
 * forwarding itself.
 */
class LateReturnService
{
    /** Awaiting the physical return. The fee shown is an estimate. */
    public const STATUS_OVERDUE = 'OVERDUE';

    /**
     * Legacy stage, no longer reachable from any code path.
     *
     * There is no mechanism anywhere in the system to correct an authoritative
     * physical return record once it is recorded (the Return Inspection form
     * itself has nothing left to submit once every line is fully returned), so
     * a "send back for correction" action could never lead anywhere - it was a
     * dead end. The action that used to reach this status has been removed.
     *
     * The existing status name/column value is kept only so any historical row
     * already at this status keeps rendering read-only, with the presentation
     * label "Late Return - Returned for Correction", instead of erroring.
     */
    public const STATUS_FOR_AO_CONFIRMATION = 'RETURNED_PENDING_SETTLEMENT';

    /** The assessment is finalized and waiting for the SPMU Head's decision. */
    public const STATUS_FOR_HEAD_APPROVAL = 'FOR_HEAD_APPROVAL';

    /** Head-approved: the Late Return Fee Form exists and payment is due. */
    public const STATUS_AWAITING_PAYMENT = 'BILLED';

    /** Settled or formally waived. */
    public const STATUS_RESOLVED = 'RESOLVED';

    /** Presentation labels for each stage of the lifecycle. */
    public const LABELS = [
        self::STATUS_OVERDUE => 'Overdue - Item Not Returned',
        self::STATUS_FOR_AO_CONFIRMATION => 'Late Return - Returned for Correction',
        self::STATUS_FOR_HEAD_APPROVAL => 'For Head Review',
        self::STATUS_AWAITING_PAYMENT => 'Approved - Awaiting Payment',
        self::STATUS_RESOLVED => 'Resolved',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
        private readonly OperationalCalendarService $operationalCalendar,
    ) {}

    public static function label(?string $status): string
    {
        return self::LABELS[(string) $status] ?? (string) $status;
    }

    /* ------------------------------------------------------------------ */
    /* The authoritative physical return date                              */
    /* ------------------------------------------------------------------ */

    /**
     * When the property physically came back, and which workflow said so.
     *
     * Linen is answered by Laundry Operations because the borrower hands linen
     * to the Laundry Area, not to the Action Officer. Everything else is
     * answered by the Return Inspection.
     *
     * @return array{0: ?Carbon, 1: ?string}
     */
    public function actualReturn(CustodyTransaction $custody): array
    {
        $custody->loadMissing([
            'lines.requestItem.inventoryItem',
            'laundryJob',
            'returns.lines.custodyLine.requestItem.inventoryItem',
        ]);

        $hasLinen = $custody->lines->contains(
            fn ($line): bool => (bool) $line->requestItem?->inventoryItem?->laundry_required
        );
        $hasNonLinen = $custody->lines->contains(
            fn ($line): bool => ! (bool) $line->requestItem?->inventoryItem?->laundry_required
        );

        $linenReceivedAt = null;
        /* A signed date still awaiting authorization cannot finalize anything. */
        if ($hasLinen && $custody->laundryJob?->hasUsableReceiptDate()) {
            $linenReceivedAt = Carbon::parse($custody->laundryJob->worker_received_at)->startOfDay();
        }

        $nonLinenReceivedAt = null;
        if ($hasNonLinen) {
            $nonLinenDates = $custody->returns
                ->filter(function ($return): bool {
                    if ($return->received_at === null) {
                        return false;
                    }

                    return $return->lines->contains(
                        fn ($line): bool => ! (bool) $line->custodyLine?->requestItem?->inventoryItem?->laundry_required
                    );
                })
                ->map(fn ($return) => Carbon::parse($return->received_at)->startOfDay());

            if ($nonLinenDates->isNotEmpty()) {
                $nonLinenReceivedAt = $nonLinenDates->sortByDesc(fn (Carbon $date) => $date->timestamp)->first();
            }
        }

        /*
         * A mixed custody has two valid physical channels, but it still has one
         * due date. The property is completely returned only when BOTH channels
         * have completed, so the later authoritative physical-return date is
         * the correct date for late-return assessment. Missing either date means
         * the physical return is not authoritative yet.
         */
        if ($hasLinen && $hasNonLinen) {
            if (! $linenReceivedAt || ! $nonLinenReceivedAt) {
                return [null, null];
            }

            $completedAt = $linenReceivedAt->greaterThan($nonLinenReceivedAt)
                ? $linenReceivedAt
                : $nonLinenReceivedAt;

            return [$completedAt, 'MIXED_RETURN_COMPLETION'];
        }

        if ($hasLinen) {
            return $linenReceivedAt
                ? [$linenReceivedAt, 'LAUNDRY_RECEIPT']
                : [null, null];
        }

        return $nonLinenReceivedAt
            ? [$nonLinenReceivedAt, 'RETURN_INSPECTION']
            : [null, null];
    }

    /**
     * Record the Laundry RECEIVED BY date the Action Officer read off the
     * accomplished Laundry Form.
     *
     * This is only for linen whose receipt was never captured digitally by
     * Laundry Personnel. It is the date written and wet-signed on the form,
     * never the date the form reached SPMU and never today.
     *
     * An existing digital receipt always wins and is never overwritten.
     */
    public function recordLaundryReceipt(
        CustodyTransaction $custody,
        Carbon $receivedOn,
        User $officer
    ): void {
        $custody->loadMissing('laundryJob');
        $job = $custody->laundryJob;

        if (! $job) {
            throw ValidationException::withMessages([
                'laundry_received_date' => 'This custody has no Laundry job, so a Laundry receipt date cannot be recorded.',
            ]);
        }

        /* Laundry Personnel are authoritative; their record is never replaced. */
        if ($job->worker_received_at !== null) {
            return;
        }

        if ($receivedOn->isFuture()) {
            throw ValidationException::withMessages([
                'laundry_received_date' => 'The Laundry Received Date cannot be in the future.',
            ]);
        }

        if ($custody->released_at && $receivedOn->lt(Carbon::parse($custody->released_at)->startOfDay())) {
            throw ValidationException::withMessages([
                'laundry_received_date' => 'The Laundry Received Date cannot be earlier than the release date.',
            ]);
        }

        $job->update(['worker_received_at' => $receivedOn]);
        $this->classifyReceiptDate($job, $officer);

        $this->audit->record('LAUNDRY_RECEIVED_DATE_ATTESTED', $job, after: [
            'laundry_received_at' => $receivedOn->toDateString(),
            'source' => 'ACCOMPLISHED_LAUNDRY_FORM',
            'attested_by_user_id' => $officer->id,
            'attested_at' => now()->toDateTimeString(),
            'custody_no' => $custody->custody_no,
        ]);
    }

    /** Does this custody carry any linen? */
    public function isLinen(CustodyTransaction $custody): bool
    {
        $custody->loadMissing('lines.requestItem.inventoryItem');

        return $custody->lines->contains(
            fn ($line): bool => (bool) $line->requestItem?->inventoryItem?->laundry_required
        );
    }

    /**
     * Linen whose Laundry receipt date is still unknown.
     *
     * Until it is known there is no authoritative physical return date, so the
     * case cannot leave the overdue stage.
     */
    public function needsLaundryReceiptDate(CustodyTransaction $custody): bool
    {
        if (! $this->isLinen($custody)) {
            return false;
        }

        $custody->loadMissing('laundryJob');

        return $custody->laundryJob?->worker_received_at === null;
    }

    /** The effective expected return date this custody is measured against. */
    public function expectedReturn(CustodyTransaction $custody): ?Carbon
    {
        return $custody->due_at ? Carbon::parse($custody->due_at)->startOfDay() : null;
    }

    /* ------------------------------------------------------------------ */
    /* Chargeable late days                                                */
    /* ------------------------------------------------------------------ */

    /**
     * The dates on which this custody is chargeably late.
     *
     * Each kind of property is measured against the calendar it can
     * actually be returned on, and only while it was still out:
     *
     *   equipment  SPMU open return days after the effective due date,
     *              through its Return Inspection date (or $through while
     *              it is still out);
     *   linen      SPMU open return days and authorized Laundry receiving
     *              days after the effective due date, through the usable
     *              signed Laundry Received date (or $through while there is
     *              none, or while that date awaits authorization).
     *
     * A mixed custody is late on a date when either kind was still out on
     * one of its own eligible days; every date counts once. A Laundry-only
     * receiving day therefore never adds a day on account of equipment.
     *
     * $through is "today" for property still out and the completion date
     * for a finished return. It is never consulted for a component whose
     * own physical return date is known, so late encoding adds nothing.
     *
     * @return list<string> Y-m-d, ascending
     */
    public function chargeableLateDates(CustodyTransaction $custody, \DateTimeInterface|string|null $through = null): array
    {
        $due = $this->expectedReturn($custody);
        if (! $due) {
            return [];
        }

        $through = $through !== null ? Carbon::parse($through)->startOfDay() : now()->startOfDay();

        $custody->loadMissing([
            'lines.requestItem.inventoryItem',
            'laundryJob',
            'returns.lines.custodyLine.requestItem.inventoryItem',
        ]);

        $isLinen = static fn ($line): bool => (bool) $line->requestItem?->inventoryItem?->laundry_required;
        $outstanding = static fn ($line): bool => (float) $line->returned_quantity < (float) $line->actual_released_quantity;
        $linenLines = $custody->lines->filter($isLinen);
        $otherLines = $custody->lines->reject($isLinen);

        $receivedOn = function (bool $linen) use ($custody): ?Carbon {
            return $custody->returns
                ->filter(fn ($return): bool => $return->received_at !== null && $return->lines->contains(
                    fn ($line): bool => (bool) $line->custodyLine?->requestItem?->inventoryItem?->laundry_required === $linen
                ))
                ->map(fn ($return) => Carbon::parse($return->received_at)->startOfDay())
                ->sortByDesc(fn (Carbon $date) => $date->timestamp)
                ->first();
        };

        $dates = [];

        /* Equipment, and any custody whose lines cannot be classified. */
        if ($otherLines->isNotEmpty() || $linenLines->isEmpty()) {
            $end = $through;

            if ($otherLines->isNotEmpty() && ! $otherLines->contains($outstanding)) {
                $end = $receivedOn(false) ?? $through;
            }

            $dates = $this->operationalCalendar->openTransactionDates(
                OperationalCalendarService::RETURN,
                $due,
                $end
            );
        }

        if ($linenLines->isNotEmpty()) {
            $job = $custody->laundryJob;
            $end = $through;

            if ($job?->hasUsableReceiptDate()) {
                $end = Carbon::parse($job->worker_received_at)->startOfDay();
            } elseif ($job?->worker_received_at === null && ! $linenLines->contains($outstanding)) {
                /* A record from before Laundry receipt dates were kept. */
                $end = $receivedOn(true) ?? $through;
            }

            $dates = array_merge($dates, $this->operationalCalendar->linenReceivingDates($due, $end));
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /** How many chargeable late days this custody has; see chargeableLateDates(). */
    public function chargeableLateDays(CustodyTransaction $custody, \DateTimeInterface|string|null $through = null): int
    {
        return count($this->chargeableLateDates($custody, $through));
    }

    /* ------------------------------------------------------------------ */
    /* Linen return state                                                  */
    /* ------------------------------------------------------------------ */

    /** No linen on this custody. */
    public const LINEN_NOT_APPLICABLE = 'NOT_APPLICABLE';

    /** Linen is out, nothing has reached SPMU, and no eligible receiving day has passed since the due date. */
    public const LINEN_AWAITING_RETURN = 'AWAITING_RETURN';

    /** Linen is past due with no return evidence known to the system. Not a finding that it was never returned. */
    public const LINEN_PROVISIONALLY_OVERDUE = 'PROVISIONALLY_OVERDUE';

    /** The Action Officer verified and recorded that the linen was not returned. */
    public const LINEN_CONFIRMED_UNRETURNED = 'CONFIRMED_UNRETURNED';

    /** A signed form is on file, but its date is not an authorized receiving day yet. */
    public const LINEN_PENDING_DATE_AUTHORIZATION = 'PENDING_DATE_AUTHORIZATION';

    /** Usable signed receipt on or before the due date; return encoding still to be done. */
    public const LINEN_RECEIVED_ON_TIME = 'RECEIVED_ON_TIME_PENDING_ENCODING';

    /** Usable signed receipt after the due date; return encoding still to be done. */
    public const LINEN_RECEIVED_LATE = 'RECEIVED_LATE_PENDING_ENCODING';

    /** The linen return has been encoded in full. */
    public const LINEN_RETURN_ENCODED = 'RETURN_ENCODED';

    public const LINEN_STATE_LABELS = [
        self::LINEN_PROVISIONALLY_OVERDUE => 'Provisionally Overdue - Pending Return Verification',
        self::LINEN_CONFIRMED_UNRETURNED => 'Confirmed Not Returned',
        self::LINEN_PENDING_DATE_AUTHORIZATION => 'Pending Date Authorization',
        self::LINEN_RECEIVED_ON_TIME => 'Received On Time - Awaiting Return Encoding',
        self::LINEN_RECEIVED_LATE => 'Received Late - Awaiting Return Encoding',
    ];

    /**
     * Where the linen on this custody stands, from what the system has been
     * told. The system cannot see an offline hand-over: "no evidence" means
     * exactly that and is never reported as "not returned".
     */
    public function linenReturnState(CustodyTransaction $custody): string
    {
        $custody->loadMissing(['lines.requestItem.inventoryItem', 'laundryJob']);

        $linenLines = $custody->lines->filter(
            fn ($line): bool => (bool) $line->requestItem?->inventoryItem?->laundry_required
        );

        if ($linenLines->isEmpty()) {
            return self::LINEN_NOT_APPLICABLE;
        }

        if (! $linenLines->contains(
            fn ($line): bool => (float) $line->returned_quantity < (float) $line->actual_released_quantity
        )) {
            return self::LINEN_RETURN_ENCODED;
        }

        $job = $custody->laundryJob;
        $due = $this->expectedReturn($custody);

        if ($job?->worker_received_at !== null) {
            if ($job->receiptDatePendingAuthorization()) {
                return self::LINEN_PENDING_DATE_AUTHORIZATION;
            }

            return $due && Carbon::parse($job->worker_received_at)->startOfDay()->gt($due)
                ? self::LINEN_RECEIVED_LATE
                : self::LINEN_RECEIVED_ON_TIME;
        }

        if ($job?->unreturned_confirmed_at !== null) {
            return self::LINEN_CONFIRMED_UNRETURNED;
        }

        /* Outstanding released quantity already means the linen was issued. */
        return $due
            && $this->operationalCalendar->countLinenReceivingDays($due, now()->startOfDay()) > 0
            ? self::LINEN_PROVISIONALLY_OVERDUE
            : self::LINEN_AWAITING_RETURN;
    }

    public static function linenStateLabel(?string $state): ?string
    {
        return self::LINEN_STATE_LABELS[(string) $state] ?? null;
    }

    /* ------------------------------------------------------------------ */
    /* Signed Laundry Received date: authorization and verification        */
    /* ------------------------------------------------------------------ */

    /**
     * Check the signed Laundry Received date on file against the receiving
     * calendars and store the result. The date itself is never changed.
     *
     * A date that is neither an SPMU open return day nor an authorized
     * Laundry receiving day is kept exactly as entered and marked Pending
     * Date Authorization; it cannot finalize the return until the SPMU
     * Head/Admin authorizes the day or accepts the signed date.
     */
    public function classifyReceiptDate(LaundryJob $job, ?User $actor = null): ?string
    {
        if ($job->worker_received_at === null) {
            return null;
        }

        $before = $job->receipt_date_status;
        $status = $this->operationalCalendar->isLinenReceivingDate($job->worker_received_at)
            ? LaundryJob::RECEIPT_DATE_AUTHORIZED
            : LaundryJob::RECEIPT_DATE_PENDING;

        $job->update([
            'receipt_date_status' => $status,
            'receipt_date_reviewed_by_user_id' => null,
            'receipt_date_reviewed_at' => null,
            'receipt_date_review_note' => null,
        ]);

        if ($status === LaundryJob::RECEIPT_DATE_PENDING && $before !== $status) {
            $this->audit->record('LAUNDRY_RECEIPT_DATE_PENDING_AUTHORIZATION', $job, after: [
                'laundry_received_on' => Carbon::parse($job->worker_received_at)->toDateString(),
                'reason' => 'The signed date is neither an SPMU open return day nor an authorized Laundry receiving day.',
                'recorded_by_user_id' => $actor?->id,
            ]);
        }

        return $status;
    }

    /**
     * SPMU Head/Admin: accept the signed Laundry Received date as the actual
     * return date without authorizing that day for Laundry receiving. The
     * day stays unauthorized, so it is not itself counted as a late day.
     * This verifies the receipt only; it approves no penalty.
     */
    public function acceptReceiptDate(LaundryJob $job, User $head, string $note): void
    {
        if (! $job->receiptDatePendingAuthorization()) {
            throw ValidationException::withMessages([
                'receipt_date' => 'This Laundry Received date is not awaiting authorization.',
            ]);
        }

        $job->update([
            'receipt_date_status' => LaundryJob::RECEIPT_DATE_ACCEPTED,
            'receipt_date_reviewed_by_user_id' => $head->id,
            'receipt_date_reviewed_at' => now(),
            'receipt_date_review_note' => $note,
        ]);

        $this->audit->record('LAUNDRY_RECEIPT_DATE_ACCEPTED', $job, reason: $note, before: [
            'receipt_date_status' => LaundryJob::RECEIPT_DATE_PENDING,
        ], after: [
            'receipt_date_status' => LaundryJob::RECEIPT_DATE_ACCEPTED,
            'laundry_received_on' => Carbon::parse($job->worker_received_at)->toDateString(),
            'day_authorized_for_laundry_receiving' => false,
            'accepted_by_user_id' => $head->id,
        ]);
    }

    /**
     * Re-check signed dates that fall on a date whose Laundry authorization
     * just changed. Only returns that are not encoded yet are touched; a
     * finalized return, its late days and its penalty are never revisited.
     */
    public function refreshReceiptDateStatusesFor(\DateTimeInterface|string $date, ?User $actor = null): int
    {
        $day = Carbon::parse($date)->toDateString();
        $changed = 0;

        LaundryJob::query()
            ->where('status', 'FOR_LAUNDRY')
            ->whereDate('worker_received_at', $day)
            ->where(function ($query): void {
                $query->whereNull('receipt_date_status')
                    ->orWhereIn('receipt_date_status', [
                        LaundryJob::RECEIPT_DATE_AUTHORIZED,
                        LaundryJob::RECEIPT_DATE_PENDING,
                    ]);
            })
            ->get()
            ->each(function (LaundryJob $job) use ($actor, &$changed): void {
                $before = $job->receipt_date_status;
                $after = $this->operationalCalendar->isLinenReceivingDate($job->worker_received_at)
                    ? LaundryJob::RECEIPT_DATE_AUTHORIZED
                    : LaundryJob::RECEIPT_DATE_PENDING;

                if ($before === $after) {
                    return;
                }

                $job->update(['receipt_date_status' => $after]);
                $this->audit->record('LAUNDRY_RECEIPT_DATE_STATUS_CHANGED', $job, before: [
                    'receipt_date_status' => $before,
                ], after: [
                    'receipt_date_status' => $after,
                    'laundry_received_on' => Carbon::parse($job->worker_received_at)->toDateString(),
                    'cause' => 'Laundry receiving day authorization changed.',
                    'changed_by_user_id' => $actor?->id,
                ]);
                $changed++;
            });

        return $changed;
    }

    /**
     * Action Officer: record that verification found the linen was not
     * returned. This creates no penalty, violation or restriction by
     * itself; it ends the paperwork hold so the custody continues through
     * the ordinary overdue and accountability process.
     */
    public function confirmLinenNotReturned(CustodyTransaction $custody, User $officer, string $reason): void
    {
        $custody->loadMissing(['lines.requestItem.inventoryItem', 'laundryJob']);
        $job = $custody->laundryJob;

        if (! $job || $this->linenReturnState($custody) !== self::LINEN_PROVISIONALLY_OVERDUE) {
            throw ValidationException::withMessages([
                'unreturned_reason' => 'Only linen that is provisionally overdue with no return evidence on file can be confirmed as not returned.',
            ]);
        }

        $job->update([
            'unreturned_confirmed_by_user_id' => $officer->id,
            'unreturned_confirmed_at' => now(),
            'unreturned_confirmation_reason' => $reason,
        ]);

        $this->audit->record('LINEN_CONFIRMED_NOT_RETURNED', $custody, reason: $reason, after: [
            'custody_no' => $custody->custody_no,
            'laundry_job_id' => $job->id,
            'effective_due_date' => $this->expectedReturn($custody)?->toDateString(),
            'confirmed_by_user_id' => $officer->id,
            'penalty_or_restriction_created' => false,
        ]);

        $this->notifications->send(
            'LINEN_CONFIRMED_NOT_RETURNED',
            $this->spmuHeads(),
            "Linen under {$custody->custody_no} was verified by the Action Officer as not returned. "
                .'The custody now follows the regular overdue and accountability process.',
            $custody,
            ['SYSTEM']
        );
    }

    /* ------------------------------------------------------------------ */
    /* Classification                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Classify a custody once its physical return has been recorded.
     *
     * Called by the return workflows. Returns the case when the return was
     * late, and null when it was on time or is not yet fully returned - an
     * on-time return never opens a late-return case.
     */
    public function assess(CustodyTransaction $custody, ?User $actor = null): ?OverdueCase
    {
        $custody->loadMissing('lines');

        $fullyReturned = $custody->lines->isNotEmpty() && $custody->lines->every(
            fn ($line): bool => (float) $line->returned_quantity >= (float) $line->actual_released_quantity
        );

        if (! $fullyReturned) {
            return null;
        }

        [$actualReturn, $source] = $this->actualReturn($custody);
        $expected = $this->expectedReturn($custody);

        if (! $actualReturn || ! $expected) {
            return null;
        }

        $case = OverdueCase::query()
            ->where('custody_transaction_id', $custody->id)
            ->first();

        /* On time: no late-return accountability, and any case opened while it
           was merely overdue is closed rather than escalated. */
        if ($actualReturn->lessThanOrEqualTo($expected)) {
            if ($case && in_array($case->status, [self::STATUS_OVERDUE, self::STATUS_FOR_AO_CONFIRMATION], true)) {
                $case->update([
                    'actual_return_date' => $actualReturn->toDateString(),
                    'return_date_source' => $source,
                    'late_days' => 0,
                    'accrued_amount' => 0,
                    'status' => self::STATUS_RESOLVED,
                ]);

                $this->audit->record('LATE_RETURN_NOT_APPLICABLE', $case, after: [
                    'expected_return_date' => $expected->toDateString(),
                    'actual_return_date' => $actualReturn->toDateString(),
                    'return_date_source' => $source,
                ]);
            }

            return null;
        }

        /* Each kind of property against its own receiving calendar; see chargeableLateDates(). */
        $lateDays = $this->chargeableLateDays($custody, $actualReturn);
        $rate = SystemSetting::value('daily_overdue_tariff');
        $amount = is_numeric($rate) ? round($lateDays * (float) $rate, 2) : 0;

        $case = $case ?: OverdueCase::query()->make([
            'custody_transaction_id' => $custody->id,
            'grace_expires_at' => $custody->due_at,
            'offense_level' => 1,
        ]);

        /* Already past confirmation: the assessment is settled, not re-opened. */
        if ($case->exists && ! in_array($case->status, [
            self::STATUS_OVERDUE,
            self::STATUS_FOR_AO_CONFIRMATION,
        ], true)) {
            return $case;
        }

        /*
         * The figures above are entirely system-derived from the
         * authoritative physical return record, the configured daily rate,
         * and the expected return date - there is nothing left for the
         * Action Officer to confirm. The assessment is finalized directly
         * to the SPMU Head, whether this is a fresh detection or a
         * reassessment of a case the Head previously returned for
         * correction.
         */
        $case->fill([
            'borrower_user_id' => $custody->borrower_user_id,
            'overdue_started_at' => $case->overdue_started_at
                ?: $expected->copy()->addDay()->startOfDay(),
            'actual_return_date' => $actualReturn->toDateString(),
            'return_date_source' => $source,
            'late_days' => $lateDays,
            'rate_snapshot' => is_numeric($rate) ? (float) $rate : null,
            'accrued_amount' => $amount,
            'status' => self::STATUS_FOR_HEAD_APPROVAL,
        ])->save();

        $this->audit->record('LATE_RETURN_DETECTED', $case, after: [
            'expected_return_date' => $expected->toDateString(),
            'actual_return_date' => $actualReturn->toDateString(),
            'return_date_source' => $source,
            'late_days' => $lateDays,
            'rate_snapshot' => is_numeric($rate) ? (float) $rate : null,
            'amount' => $amount,
        ]);

        $this->notifications->send(
            'LATE_RETURN_FOR_HEAD_APPROVAL',
            $this->spmuHeads(),
            "Custody {$custody->custody_no} was returned {$lateDays} day(s) after its expected return date. "
                .'The late-return assessment was automatically finalized from the recorded physical return and is ready for approval.',
            $case
        );

        /*
         * The physical property is back, so the outstanding-property
         * restriction is finished; the fee remains a separate obligation.
         */
        BorrowerRestriction::query()->firstOrCreate(
            [
                'borrower_user_id' => $custody->borrower_user_id,
                'custody_transaction_id' => $custody->id,
                'restriction_type' => 'OVERDUE_RETURN',
                'status' => 'ACTIVE',
            ],
            [
                'reason' => 'Late return under '.$custody->custody_no.' is awaiting accountability settlement.',
                'effective_from' => now(),
                'imposed_by_user_id' => $actor?->id,
            ]
        );

        return $case;
    }

    /* ------------------------------------------------------------------ */
    /* Presentation helpers                                                */
    /* ------------------------------------------------------------------ */

    /**
     * What the case is worth right now.
     *
     * While overdue the amount is an estimate that grows each day. Once the
     * item is back the frozen figures are returned unchanged - today's date is
     * deliberately not consulted.
     *
     * @return array<string, mixed>
     */
    public function assessment(OverdueCase $case): array
    {
        $expected = $case->grace_expires_at
            ? Carbon::parse($case->grace_expires_at)->startOfDay()
            : null;

        $rate = $case->rate_snapshot !== null
            ? (float) $case->rate_snapshot
            : (is_numeric($raw = SystemSetting::value('daily_overdue_tariff')) ? (float) $raw : null);

        if ($case->actual_return_date !== null) {
            return [
                'is_estimate' => false,
                'expected_return_date' => $expected,
                'actual_return_date' => Carbon::parse($case->actual_return_date),
                'return_date_source' => $case->return_date_source,
                'late_days' => (int) $case->late_days,
                'rate' => $rate,
                'amount' => (float) $case->accrued_amount,
            ];
        }

        /*
         * The running estimate uses the same counter as the final figure.
         * It follows the custody's current effective due date, exactly as
         * the daily scheduler does when it accrues this case.
         */
        $custody = $case->custody;
        $lateDays = match (true) {
            $custody !== null && $custody->due_at !== null => $this->chargeableLateDays($custody, now()->startOfDay()),
            $expected !== null => $this->operationalCalendar->countOpenTransactionDays(
                OperationalCalendarService::RETURN,
                $expected,
                now()->startOfDay()
            ),
            default => 0,
        };

        return [
            'is_estimate' => true,
            'expected_return_date' => $expected,
            'actual_return_date' => null,
            'return_date_source' => null,
            'late_days' => $lateDays,
            'rate' => $rate,
            'amount' => $rate !== null ? round($lateDays * $rate, 2) : 0.0,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Recipients                                                          */
    /* ------------------------------------------------------------------ */

    /** @return \Illuminate\Support\Collection<int, User> */
    private function actionOfficers()
    {
        return User::query()
            ->where('access_classification', \App\Enums\AccessClassification::SpmuOfficer)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function spmuHeads()
    {
        return User::query()
            ->where('access_classification', \App\Enums\AccessClassification::SpmuHead)
            ->get();
    }
}
