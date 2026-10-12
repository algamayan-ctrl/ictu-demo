<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Incident extends Model
{
    protected $fillable = ['incident_no', 'custody_transaction_id', 'borrower_user_id', 'reported_by_user_id', 'head_decision_signature_snapshot_id', 'head_decided_by_user_id', 'head_decided_at', 'supporting_evidence_file_id', 'incident_type', 'reported_at', 'police_blotter_reference', 'appraisal_amount', 'rslddp_reference', 'requires_rslddp', 'compliance_action', 'rslddp_evidence_submission_id', 'rslddp_attachment_file_id', 'resolved_by_user_id', 'resolved_at', 'f2f_processing_completed_at', 'status', 'remarks', 'official_disposition', 'official_disposition_amount', 'official_disposition_details', 'official_disposition_recorded_by_user_id', 'official_disposition_recorded_at', 'compliance_verification_status', 'compliance_verification_remarks', 'compliance_verified_by_user_id', 'compliance_verified_at', 'accountability_flow_version'];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'head_decided_at' => 'datetime',
            'appraisal_amount' => 'decimal:2',
            'requires_rslddp' => 'boolean',
            'official_disposition_amount' => 'decimal:2',
            'official_disposition_recorded_at' => 'datetime',
            'compliance_verified_at' => 'datetime',
            'resolved_at' => 'datetime',
            'f2f_processing_completed_at' => 'datetime',
        ];
    }

    /** Prefix of the line the SPMU Head's decision appends to the remarks. */
    public const HEAD_DECISION_PREFIX = 'SPMU Head decision:';

    public const HEAD_DECISION_CLEARED = 'Finding cleared.';

    public const HEAD_DECISION_CONFIRMED = 'Accountability confirmed.';

    /** Prefix of the line the final accountability resolution appends to the remarks. */
    public const FINAL_RESOLUTION_PREFIX = 'SPMU Head/Admin final accountability resolution:';

    /**
     * The SPMU Head's most recent decision line, exactly as it was recorded
     * with the decision. Null when no such line exists; nothing is inferred.
     */
    public function headDecisionNote(): ?string
    {
        $note = collect(preg_split('/\R/', trim((string) $this->remarks)) ?: [])
            ->map(fn ($line): string => trim((string) $line))
            ->filter(fn (string $line): bool => str_starts_with($line, self::HEAD_DECISION_PREFIX))
            ->last();

        return $note !== null ? trim(substr($note, strlen(self::HEAD_DECISION_PREFIX))) : null;
    }

    /**
     * The Head reviewed the finding and cleared it: the case closed with no
     * accountability. This is a different outcome from a confirmed case that
     * was later settled, although both end in the RESOLVED status.
     */
    public function findingWasCleared(): bool
    {
        return in_array($this->status, ['RESOLVED', 'CLOSED'], true)
            && str_starts_with((string) $this->headDecisionNote(), self::HEAD_DECISION_CLEARED);
    }

    /** The Head confirmed accountability for this finding. */
    public function accountabilityWasConfirmed(): bool
    {
        return str_starts_with((string) $this->headDecisionNote(), self::HEAD_DECISION_CONFIRMED);
    }

    /**
     * The reason the Head recorded with that decision, without the decision
     * label. Null when none is on record.
     */
    public function headDecisionReason(): ?string
    {
        $note = $this->headDecisionNote();

        if ($note === null) {
            return null;
        }

        foreach ([self::HEAD_DECISION_CLEARED, self::HEAD_DECISION_CONFIRMED] as $label) {
            if (str_starts_with($note, $label)) {
                $note = trim(substr($note, strlen($label)));
                break;
            }
        }

        return $note !== '' ? $note : null;
    }

    /**
     * When the case actually reached its final outcome.
     *
     * The recorded resolution time is used when there is one. Older records
     * were closed before that field was written on every path; for those the
     * fallback is, in order, the Head's decision time when that decision was
     * itself the final one (a cleared finding), then the record's last
     * update. Nothing is written back.
     */
    public function resolutionMoment(): ?\DateTimeInterface
    {
        if ($this->resolved_at !== null) {
            return $this->resolved_at;
        }

        if ($this->findingWasCleared() && $this->head_decided_at !== null) {
            return $this->head_decided_at;
        }

        return $this->updated_at;
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function custody(): BelongsTo
    {
        return $this->belongsTo(CustodyTransaction::class, 'custody_transaction_id');
    }

    public function evidenceFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'supporting_evidence_file_id');
    }

    /**
     * SPMU Action Officer who inspected the property and reported this case.
     * Printed on the RSLDDP as the named reporting signatory.
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function headDecisionSignature(): BelongsTo
    {
        return $this->belongsTo(SignatureSnapshot::class, 'head_decision_signature_snapshot_id');
    }

    public function headDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_decided_by_user_id');
    }

    /**
     * SPMU Head/Admin who recorded the requirement approved during F2F
     * processing. The accomplished RSLDDP is attached only at resolution.
     */
    public function officialDispositionRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'official_disposition_recorded_by_user_id');
    }

    /**
     * SPMU Action Officer who verified REPAIR/REPLACEMENT/RETURN_RECOVERY
     * compliance. Not used for MONETARY_SETTLEMENT, which is verified through
     * the Cashier receipt/payment flow instead.
     */
    public function complianceVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compliance_verified_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(IncidentLine::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(GeneratedDocument::class, 'subject');
    }

    /**
     * Accomplished RSLDDP retained once as a final-resolution attachment.
     * Preparation and institutional processing happen outside the system.
     */
    public function rslddpAttachment(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'rslddp_attachment_file_id');
    }

    /**
     * SPMU Head/Admin who explicitly completed the final accountability
     * resolution after the face-to-face RSLDDP process.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function rslddpEvidence(): BelongsTo
    {
        return $this->belongsTo(EvidenceSubmission::class, 'rslddp_evidence_submission_id');
    }
}
