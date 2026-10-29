<?php

namespace App\Support;

use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Services\ReturnMetricsService;

/** Read-only SAO list labels. Filter categories never replace case details. */
final class ActionOfficerStatusPresentation
{
    public static function filters(): array
    {
        return [
            'UNDER_REVIEW' => 'Under Review',
            'NEEDS_REVISION' => 'Needs Revision',
            'FOR_RELEASE' => 'For Release',
            'ON_CUSTODY' => 'On Custody',
            'RETURN_PROCESSING' => 'Return Processing',
            'ACCOUNTABILITY_PROCESSING' => 'Accountability Processing',
            'COMPLETED' => 'Completed',
            'CANCELLED' => 'Cancelled',
            'REJECTED' => 'Rejected',
        ];
    }

    public static function releaseStatus(array $status): array
    {
        if (in_array($status['key'], ['PICKUP_SCHEDULING', 'ITEM_PREPARATION', 'PREPARING_RELEASE'], true)) {
            return array_replace($status, ['key' => 'FOR_PREPARATION', 'label' => 'For Preparation']);
        }

        return $status;
    }

    public static function forRequest(BorrowingRequest $request): array
    {
        if ($request->custody) {
            return self::forCustody($request->custody);
        }

        $raw = $request->status instanceof \BackedEnum ? $request->status->value : (string) $request->status;
        $verificationPending = (bool) $request->currentVersion?->off_campus
            && ($request->currentVersion?->approvalSteps ?? collect())->contains(
                fn ($step) => (int) $step->sequence_no === 1
                    && (string) ($step->stage_code instanceof \BackedEnum ? $step->stage_code->value : $step->stage_code) === 'SPMU'
                    && in_array((string) $step->decision, ['PENDING', 'RECEIVED'], true)
            );

        [$key, $label, $filter] = match ($raw) {
            'RETURNED_FOR_REVISION' => ['NEEDS_REVISION', 'Needs Revision', 'NEEDS_REVISION'],
            'FINAL_APPROVED_AWAITING_DOWNLOAD', 'APPROVED_READY_FOR_RELEASE' => ['FOR_RELEASE', 'For Release', 'FOR_RELEASE'],
            'CANCELLED', 'EXPIRED' => ['CANCELLED', 'Cancelled', 'CANCELLED'],
            'REJECTED' => ['REJECTED', 'Rejected', 'REJECTED'],
            'DRAFT' => ['DRAFT', 'Draft', 'UNDER_REVIEW'],
            default => $verificationPending
                ? ['FOR_VERIFICATION', 'For Verification', 'UNDER_REVIEW']
                : ['FOR_APPROVAL', 'For Approval', 'UNDER_REVIEW'],
        };

        return compact('key', 'label', 'filter') + ['group' => 'request', 'notes' => [], 'attention' => false];
    }

    public static function forCustody(CustodyTransaction $custody): array
    {
        $workflow = $custody->workflowStatus();
        $status = self::releaseStatus($workflow);
        $summary = in_array($status['key'], ['OBLIGATION_OPEN', 'INCIDENT_OPEN'], true)
            ? $custody->openObligationSummary()
            : null;
        if ($summary) {
            $status = array_replace($status, ['key' => $summary['key'], 'label' => $summary['label']]);
        }

        $key = $status['key'];
        $label = match ($key) {
            'BORROWED' => 'On Custody',
            'RETURN_PROCESSING' => 'Return Processing',
            'LINEN_PENDING' => 'For Linen Return Recording',
            'GATE_PASS_PENDING' => 'For Gate Pass Recording',
            'BORROWER_CLEARED' => 'For Return Record Review',
            default => self::caseLabel($key, $status['label']),
        };

        $filter = match (true) {
            $key === 'CANCELLED' => 'CANCELLED',
            $key === 'COMPLETED' => 'COMPLETED',
            in_array($key, ['FOR_PREPARATION', 'PICKUP_SCHEDULED', 'READY_FOR_RELEASE', 'PICKUP_EXPIRED'], true) => 'FOR_RELEASE',
            in_array($key, ['BORROWED', 'OVERDUE'], true) => 'ON_CUSTODY',
            in_array($key, ['RETURN_PROCESSING', 'LINEN_PENDING', 'GATE_PASS_PENDING', 'BORROWER_CLEARED'], true) => 'RETURN_PROCESSING',
            default => 'ACCOUNTABILITY_PROCESSING',
        };

        $notes = [];
        $attention = $filter === 'ACCOUNTABILITY_PROCESSING' || in_array($key, ['OVERDUE', 'PICKUP_EXPIRED'], true);
        $cancelled = $key === 'CANCELLED';
        if (! $cancelled) {
            $returnState = app(ReturnMetricsService::class)->state($custody);
            if ($returnState === ReturnMetricsService::RETURNED_LATE) {
                $notes[] = 'Returned Late';
            } elseif ($returnState === ReturnMetricsService::CURRENTLY_OVERDUE && $key !== 'OVERDUE') {
                $notes[] = 'Overdue';
                $attention = true;
            }

            $incidents = $custody->incidents
                ->whereNotIn('status', ['RESOLVED', 'CLOSED', 'VOID_CORRECTION']);
            foreach ($incidents as $incident) {
                $notes[] = match ((string) $incident->incident_type) {
                    'DAMAGED', 'DAMAGE' => 'Damage Finding',
                    'LOST', 'LOSS' => 'Loss Finding',
                    'NON_RETURN', 'UNRETURNED' => 'Unreturned Property',
                    default => 'Property Finding',
                };
                $action = strtoupper((string) ($incident->official_disposition ?: $incident->compliance_action));
                $actionLabel = match ($action) {
                    'REPAIR' => 'Repair Required',
                    'REPLACEMENT' => 'Replacement Required',
                    'RETURN_RECOVERY', 'RECOVERY' => 'Return / Recovery Required',
                    default => null,
                };
                if ($actionLabel) {
                    $notes[] = $actionLabel;
                }
                $attention = true;
            }

            $lateCase = $custody->overdueCase;
            if ($lateCase && (string) $lateCase->status !== 'RESOLVED') {
                $lateStage = $custody->hasOutstandingProperty() ? 'Overdue Case Review' : match ((string) $lateCase->status) {
                    'RETURNED_PENDING_SETTLEMENT' => 'For Late Return Verification',
                    'FOR_HEAD_APPROVAL' => 'For Late Return Approval',
                    'BILLED' => 'For Payment',
                    default => 'Late Return Review',
                };
                $notes[] = $custody->hasOutstandingProperty() ? 'Overdue Case' : 'Late Return Case';
                if ($key === 'LATE_RETURN') {
                    $label = $lateStage;
                } else {
                    $notes[] = $lateStage;
                }
                $attention = true;
            }

            $indicator = $custody->activeAccountabilityIndicator();
            if ($indicator && $filter !== 'ACCOUNTABILITY_PROCESSING') {
                $caseStatus = $custody->openObligationSummary() ?? $indicator;
                $notes[] = $caseStatus['key'] === 'LATE_RETURN' && $custody->hasOutstandingProperty()
                    ? 'Overdue Case Review'
                    : self::caseLabel($caseStatus['key'], $caseStatus['label']);
                $attention = true;
            }

        }

        $notes = array_values(array_unique(array_filter($notes, fn ($note) => $note !== $label)));

        return compact('key', 'label', 'filter', 'notes', 'attention') + ['group' => $workflow['group']];
    }

    private static function caseLabel(string $key, string $fallback): string
    {
        return match ($key) {
            'INCIDENT_OPEN', 'ACCOUNTABILITY_REVIEW', 'REPORTED', 'UNDER_REVIEW' => 'Property Case Review',
            'OBLIGATION_OPEN', 'BORROWING_RESTRICTED' => 'For Accountability Review',
            'LATE_RETURN' => 'Late Return Review',
            'FOR_BILLING' => 'For Billing',
            'BILLING_PENDING', 'BILLING_OPEN' => 'Billing Pending',
            'BILLING_ISSUED', 'RSLDDP_PAYMENT_REQUIRED' => 'For Payment',
            'PAYMENT_VERIFICATION', 'RECEIPT_SUBMITTED' => 'Payment Verification',
            'COMPLIANCE_REQUIRED' => 'For Compliance',
            'COMPLIANCE_RSLDDP_PENDING' => 'For Compliance Verification',
            'F2F_PROCESSING', 'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING' => 'For In-Person Processing',
            'RSLDDP_FOR_ACCOUNTING_PROCESSING' => 'For Accounting Review',
            'RSLDDP_COMPLIANCE_VERIFICATION' => $fallback,
            'RSLDDP_FOR_RESOLUTION' => 'For Final Review',
            default => $fallback,
        };
    }
}
