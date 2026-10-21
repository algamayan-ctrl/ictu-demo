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
 *   non-linen   documented physical date, falling back to legacy received_at
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
        if ($hasLinen && $custody->laundryJob?->worker_received_at) {
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
                ->map(fn ($return) => Carbon::parse($return->physical_return_date ?: $return->received_at)->startOfDay());

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
     *   linen      the actual signed Laundry Received date determines when
     *              the property physically returned. Chargeable days remain
     *              the normal SPMU open return days after the effective due
     *              date and through that actual date.
     *
     * A mixed custody is late on a date when either kind was still out on
     * an SPMU open return day; every date counts once.
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
                ->map(fn ($return) => Carbon::parse($linen
                    ? $return->received_at
                    : ($return->physical_return_date ?: $return->received_at))->startOfDay())
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

            if ($job?->worker_received_at) {
                $end = Carbon::parse($job->worker_received_at)->startOfDay();
            } elseif ($job?->worker_received_at === null && ! $linenLines->contains($outstanding)) {
                /* A record from before Laundry receipt dates were kept. */
                $end = $receivedOn(true) ?? $through;
            }

            $dates = array_merge($dates, $this->operationalCalendar->openTransactionDates(
                OperationalCalendarService::RETURN,
                $due,
                $end
            ));
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

    /**
     * The late days recorded on this custody's finalized assessment, or null
     * while there is none (still out, or returned without a case).
     *
     * Once the physical return is recorded the case figures are frozen. Any
     * screen that shows late days for a completed return reads them here, so
     * a later change to a receiving calendar cannot make it disagree with
     * the penalty case beside it. Nothing is recalculated or written.
     */
    public function finalizedLateDays(CustodyTransaction $custody): ?int
    {
        $case = $custody->relationLoaded('overdueCase')
            ? $custody->overdueCase
            : $custody->overdueCase()->first();

        return $case && $case->actual_return_date !== null
            ? max(0, (int) $case->late_days)
            : null;
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

    /** Usable signed receipt on or before the due date; return encoding still to be done. */
    public const LINEN_RECEIVED_ON_TIME = 'RECEIVED_ON_TIME_PENDING_ENCODING';

    /** Usable signed receipt after the due date; return encoding still to be done. */
    public const LINEN_RECEIVED_LATE = 'RECEIVED_LATE_PENDING_ENCODING';

    /** The linen return has been encoded in full. */
    public const LINEN_RETURN_ENCODED = 'RETURN_ENCODED';

    public const LINEN_STATE_LABELS = [
        self::LINEN_PROVISIONALLY_OVERDUE => 'Pending Laundry Form - Return Date Unverified',
        self::LINEN_CONFIRMED_UNRETURNED => 'Confirmed Not Returned',
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
            return $due && Carbon::parse($job->worker_received_at)->startOfDay()->gt($due)
                ? self::LINEN_RECEIVED_LATE
                : self::LINEN_RECEIVED_ON_TIME;
        }

        if ($job?->unreturned_confirmed_at !== null) {
            return self::LINEN_CONFIRMED_UNRETURNED;
        }

        /* Outstanding released quantity already means the linen was issued. */
        return $due
            && $this->operationalCalendar->countOpenTransactionDays(
                OperationalCalendarService::RETURN,
                $due,
                now()->startOfDay()
            ) > 0
            ? self::LINEN_PROVISIONALLY_OVERDUE
            : self::LINEN_AWAITING_RETURN;
    }

    public static function linenStateLabel(?string $state): ?string
    {
        return self::LINEN_STATE_LABELS[(string) $state] ?? null;
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
