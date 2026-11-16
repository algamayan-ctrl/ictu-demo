<?php

namespace App\Reports;

use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;

// This is the read-only status presentation for the request overview report.
final class RequestOverviewStatus
{
    public const OPTIONS = [
        'FOR_APPROVAL' => 'For Approval', 'REJECTED' => 'Rejected',
        'ITEM_PREPARATION' => 'For Item Preparation', 'FOR_RELEASE' => 'For Release',
        'ON_CUSTODY' => 'On Custody', 'INSPECTED' => 'Inspected', 'RETURNED' => 'Returned',
        'OVERDUE_RETURN' => 'Overdue Return', 'LATE_RETURNED' => 'Late Returned',
        'ACCOUNTABILITY_CASE' => 'Accountability Case', 'ACCOUNTABILITY_SETTLED' => 'Accountability Settled',
    ];

    public static function forRequest(BorrowingRequest $request): array
    {
        $custody = $request->custody;
        if (! $custody) {
            return match ($request->status) {
                RequestStatus::Rejected => ['REJECTED', 'Rejected'],
                RequestStatus::Cancelled => ['CANCELLED', 'Cancelled'],
                RequestStatus::Expired => ['EXPIRED', 'Expired'],
                RequestStatus::ReturnedForRevision => ['RETURNED_FOR_REVISION', 'Returned for Revision'],
                RequestStatus::ApprovedReadyForRelease, RequestStatus::FinalApprovedAwaitingDownload => ['ITEM_PREPARATION', 'For Item Preparation'],
                default => ['FOR_APPROVAL', 'For Approval'],
            };
        }
        $custody->setRelation('request', $request);
        $workflow = $custody->workflowStatus();
        $key = $workflow['key'];
        if ($key === 'CANCELLED') return ['CANCELLED', 'Cancelled'];
        if ($key === 'OVERDUE') return ['OVERDUE_RETURN', 'Overdue Return'];
        if ($key === 'RETURN_PROCESSING') {
            return $custody->returns->contains(fn ($row) => $row->confirmed_at !== null || $row->inspection_signature_snapshot_id !== null)
                ? ['INSPECTED', 'Inspected'] : ['ON_CUSTODY', 'On Custody'];
        }
        if ($custody->activeAccountabilityIndicator()) return ['ACCOUNTABILITY_CASE', 'Accountability Case'];
        if ($custody->released_at && ($custody->closed_at || $custody->status === 'CLOSED')) {
            if ($custody->incidents->contains(fn ($row) => in_array($row->status, ['CLOSED', 'RESOLVED'], true))
                || $custody->overdueCase?->status === 'RESOLVED') {
                return ['ACCOUNTABILITY_SETTLED', 'Accountability Settled'];
            }
            $physicalReturn = $custody->returns->map(fn ($row) => $row->physical_return_date ?: $row->received_at)->filter()->sort()->last();
            $due = $custody->original_due_at ?: $custody->due_at;
            if ($physicalReturn && $due && $physicalReturn->copy()->startOfDay()->gt($due->copy()->startOfDay())) return ['LATE_RETURNED', 'Late Returned'];

            return ['RETURNED', 'Returned'];
        }
        if ($custody->released_at) return ['ON_CUSTODY', 'On Custody'];

        return match ($key) {
            'READY_FOR_RELEASE', 'PICKUP_SCHEDULED' => ['FOR_RELEASE', 'For Release'],
            'ITEM_PREPARATION', 'PICKUP_SCHEDULING', 'PREPARING_RELEASE' => ['ITEM_PREPARATION', 'For Item Preparation'],
            default => [$key, $workflow['label']],
        };
    }
}
