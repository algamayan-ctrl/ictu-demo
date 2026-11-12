<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BorrowingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_no',
        'borrower_user_id',
        'accountable_unit_id',
        'current_version_no',
        'status',
        'final_approved_at',

        /* Historical download workflow field. */
        'download_deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'final_approved_at' => 'datetime',
            'download_deadline_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RequestVersion::class, 'request_id');
    }

    public function accountableUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'accountable_unit_id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(RequestVersion::class, 'request_id')->ofMany('version_no', 'max');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id')->latest('changed_at');
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(RequestSupportingDocument::class, 'request_id');
    }

    public function custody(): HasOne
    {
        return $this->hasOne(CustodyTransaction::class, 'request_id');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(RequestCancellation::class, 'request_id');
    }

    /**
     * Custody workflow keys that belong to the separate Accountability case.
     * My Requests reports the borrowing itself as Completed for these; the
     * case is followed up in My Obligations.
     */
    public const BORROWER_ACCOUNTABILITY_KEYS = [
        'INCIDENT_OPEN', 'OBLIGATION_OPEN', 'FOR_BILLING',
        'BILLING_PENDING', 'BILLING_OPEN', 'BILLING_ISSUED',
        'PAYMENT_VERIFICATION', 'RECEIPT_SUBMITTED',
        'COMPLIANCE_REQUIRED', 'COMPLIANCE_RSLDDP_PENDING',
        'F2F_PROCESSING', 'RSLDDP_AWAITING_UPLOAD',
        'RSLDDP_FOR_ACCOUNTING_PROCESSING', 'RSLDDP_PAYMENT_REQUIRED',
        'RSLDDP_DISPOSITION_PENDING', 'RSLDDP_COMPLIANCE_VERIFICATION',
        'RSLDDP_FOR_RESOLUTION', 'BORROWING_RESTRICTED', 'LATE_RETURN',
    ];

    /**
     * The status shown on the borrower's My Requests row. The My Requests
     * status filter uses this same method, so the filter always agrees with
     * the badge.
     *
     * @return array{key:string,label:string}
     */
    public function borrowerRecordStatus(): array
    {
        $custody = $this->custody;

        if ($custody) {
            // Avoid reloading the request we already have.
            if (! $custody->relationLoaded('request')) {
                $custody->setRelation('request', $this);
            }

            // SPMU still recording the accomplished Gate Pass is internal SPMU
            // processing, not a borrower obligation or a separate request status.
            if ($custody->isGatePassOnlyPending()) {
                return ['key' => 'COMPLETED', 'label' => 'Completed'];
            }

            $workflow = $custody->workflowStatus();

            if (in_array($workflow['key'], self::BORROWER_ACCOUNTABILITY_KEYS, true)) {
                return ['key' => 'COMPLETED', 'label' => 'Completed'];
            }

            [$key, $label] = match ($workflow['key']) {
                'BORROWED' => ['ON_CUSTODY', 'On Custody'],
                'RETURN_PROCESSING', 'LINEN_PENDING', 'GATE_PASS_PENDING' => ['ON_CUSTODY', 'On Custody'],
                'OVERDUE' => ['OVERDUE', 'Overdue'],
                'COMPLETED' => ['COMPLETED', 'Completed'],
                'BORROWER_CLEARED' => ['COMPLETED', 'Completed'],
                'CANCELLED' => ['CANCELLED', 'Cancelled'],
                'READY_FOR_RELEASE' => ['READY_FOR_PICKUP', 'Ready for Pickup'],
                'PICKUP_SCHEDULED' => ['PICKUP_SCHEDULED', 'Pickup Scheduled'],
                'ITEM_PREPARATION', 'PICKUP_SCHEDULING', 'PREPARING_RELEASE' => ['FOR_ISSUANCE', 'For Issuance'],
                'PICKUP_EXPIRED' => ['MISSED_PICKUP', 'Missed Pickup'],
                default => [$workflow['key'], $workflow['label']],
            };

            return ['key' => $key, 'label' => $label];
        }

        [$key, $label] = match ($this->status) {
            RequestStatus::Draft => ['DRAFT', 'Draft'],
            RequestStatus::ReturnedForRevision => ['NEEDS_REVISION', 'Needs Revision'],
            RequestStatus::UnderSpmu, RequestStatus::UnderGsu, RequestStatus::UnderVpaf => ['FOR_REVIEW', 'For Review'],
            RequestStatus::ApprovedReadyForRelease, RequestStatus::FinalApprovedAwaitingDownload => ['FOR_ISSUANCE', 'For Issuance'],
            RequestStatus::Rejected => ['REJECTED', 'Rejected'],
            RequestStatus::Cancelled, RequestStatus::Expired => ['CANCELLED', 'Cancelled'],
            default => ['FOR_REVIEW', 'For Review'],
        };

        return ['key' => $key, 'label' => $label];
    }
}
