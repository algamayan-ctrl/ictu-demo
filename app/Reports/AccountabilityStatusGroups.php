<?php

namespace App\Reports;

// This is the filter grouping for current and historical accountability cases.
final class AccountabilityStatusGroups
{
    public const OPTIONS = [
        'OPEN_CURRENT' => 'Open / Unresolved', 'GROUP_ADMIN_DECISION' => 'For Admin Decision',
        'GROUP_PROCESSING' => 'Processing / Compliance', 'GROUP_PAYMENT' => 'Awaiting Payment',
        'GROUP_RESOLVED' => 'Resolved / Closed', 'VOID_CORRECTION' => 'Void / Correction',
    ];

    public static function matches(string $filter, string $status, string $caseType): bool
    {
        return match ($filter) {
            'OPEN_CURRENT' => $caseType === 'LATE_RETURN' ? $status !== 'RESOLVED' : ! in_array($status, ['RESOLVED', 'CLOSED', 'VOID_CORRECTION'], true),
            'GROUP_ADMIN_DECISION' => $status === 'FOR_HEAD_APPROVAL',
            'GROUP_PAYMENT' => in_array($status, ['BILLED', 'RSLDDP_PAYMENT_REQUIRED'], true),
            'GROUP_RESOLVED' => in_array($status, ['RESOLVED', 'CLOSED'], true),
            'GROUP_PROCESSING' => in_array($status, ['COMPLIANCE_REQUIRED', 'COMPLIANCE_RSLDDP_PENDING', 'F2F_PROCESSING',
                'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_FOR_ACCOUNTING_PROCESSING', 'RSLDDP_DISPOSITION_PENDING',
                'RSLDDP_COMPLIANCE_VERIFICATION', 'RSLDDP_FOR_RESOLUTION', 'FOR_BILLING', 'BILLING_PENDING', 'RETURNED_PENDING_SETTLEMENT'], true),
            default => $filter === $status,
        };
    }
}
