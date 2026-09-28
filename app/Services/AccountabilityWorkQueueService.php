<?php

namespace App\Services;

use App\Enums\AccessClassification;
use App\Models\BillingStatement;
use App\Models\Incident;
use App\Models\OverdueCase;
use Illuminate\Support\Collection;

/**
 * Builds the role-specific Accountability work queue shown to SPMU staff.
 *
 * This does not change case state or duplicate business rules. It only turns
 * the already-authoritative Incident / OverdueCase / Billing records into a
 * consistent operational view: what the case is, who/what it is waiting on,
 * and which role handles the next system action.
 */
class AccountabilityWorkQueueService
{
    /**
     * @param Collection<int, Incident> $incidents
     * @param Collection<int, OverdueCase> $overdueCases
     * @param Collection<int, BillingStatement> $billings
     * @return array{work: Collection<int,array<string,mixed>>, waiting: Collection<int,array<string,mixed>>}
     */
    public function build(
        AccessClassification $classification,
        Collection $incidents,
        Collection $overdueCases,
        Collection $billings
    ): array {
        $isHead = $classification === AccessClassification::SpmuHead;
        $isOfficer = $classification === AccessClassification::SpmuOfficer;

        $work = collect();
        $waiting = collect();

        $openIncidents = $incidents->whereNotIn('status', ['RESOLVED', 'CLOSED', 'VOID_CORRECTION']);
        $openOverdueCases = $overdueCases->whereNotIn('status', [LateReturnService::STATUS_RESOLVED]);
        $openBillings = $billings->whereNotIn('status', ['SETTLED', 'WAIVED', 'VOID']);

        $billingForIncident = static function (Incident $incident) use ($openBillings): ?BillingStatement {
            return $openBillings->first(fn (BillingStatement $billing) => $billing->lines->contains(
                fn ($line) => (int) ($line->incident_id ?? 0) === (int) $incident->id
            ));
        };

        $billingForOverdue = static function (OverdueCase $overdue) use ($openBillings): ?BillingStatement {
            return $openBillings->first(fn (BillingStatement $billing) => $billing->lines->contains(
                fn ($line) => (int) ($line->penalty?->overdue_case_id ?? 0) === (int) $overdue->id
            ));
        };

        foreach ($openOverdueCases as $overdue) {
            $billing = $billingForOverdue($overdue);
            $borrower = $overdue->borrower;
            $status = (string) $overdue->status;
            $lateDays = max(0, (int) ($overdue->late_days ?? 0));
            $fee = (float) ($overdue->final_fee ?? $overdue->assessed_fee ?? 0);

            $base = [
                'key' => 'overdue-'.$overdue->id,
                'record_type' => 'overdue',
                'record_id' => (int) $overdue->id,
                'borrower' => $borrower,
                'reference' => $overdue->custody?->custody_no ?: 'Late-return case #'.$overdue->id,
                'case_type' => $status === LateReturnService::STATUS_OVERDUE ? 'Overdue Return' : 'Late Return',
                'issue' => $lateDays > 0 ? $lateDays.' late day'.($lateDays === 1 ? '' : 's') : 'Return deadline exceeded',
                'amount' => $fee > 0 ? 'PHP '.number_format($fee, 2) : null,
                'status' => $status,
                'date' => $overdue->overdue_started_at ?: $overdue->created_at,
                'tone' => $status === LateReturnService::STATUS_OVERDUE ? 'danger' : 'warning',
                'url' => $borrower
                    ? route('accountability.borrower', [$borrower, 'tab' => 'cases', 'focus' => 'overdue-'.$overdue->id]).'#overdue-'.$overdue->id
                    : null,
            ];

            if ($status === LateReturnService::STATUS_OVERDUE) {
                $item = $base + [
                    'stage' => 'Physical Return',
                    'waiting_on' => 'Borrower',
                    'next_step' => 'Borrower must return the outstanding item(s). The Action Officer records the physical return and inspection when presented.',
                    'action_label' => $isOfficer ? 'Open Return Processing' : 'View Case',
                    'action_url' => $isOfficer && $overdue->custody
                        ? route('custody.return.show', $overdue->custody)
                        : $base['url'],
                ];
                ($isOfficer ? $work : $waiting)->push($item);
                continue;
            }

            if (in_array($status, [LateReturnService::STATUS_FOR_AO_CONFIRMATION, LateReturnService::STATUS_FOR_HEAD_APPROVAL], true)) {
                $item = $base + [
                    'stage' => 'Late-Return Assessment Review',
                    'waiting_on' => 'SPMU Head/Admin',
                    'next_step' => 'Review the finalized late-return assessment and determine the applicable billing outcome.',
                    'action_label' => $isHead ? 'Review Assessment' : 'View Case',
                    'action_url' => $base['url'],
                ];
                ($isHead ? $work : $waiting)->push($item);
                continue;
            }

            if ($status === LateReturnService::STATUS_AWAITING_PAYMENT) {
                $remaining = $billing ? $this->remainingBalance($billing) : $fee;
                $item = $base + [
                    'stage' => 'Cashier Payment',
                    'waiting_on' => 'Borrower',
                    'next_step' => 'Borrower pays through the CSPC Cashier and presents the official receipt. The Action Officer records the receipt when presented.',
                    'amount' => $remaining > 0 ? 'PHP '.number_format($remaining, 2) : ($base['amount'] ?? null),
                    'action_label' => $isOfficer ? 'Open Payment Record' : 'View Case',
                    'action_url' => $base['url'],
                ];
                ($isOfficer ? $work : $waiting)->push($item);
                continue;
            }

            $waiting->push($base + [
                'stage' => LateReturnService::label($status),
                'waiting_on' => 'SPMU Processing',
                'next_step' => 'Continue the current late-return process from the borrower case workspace.',
                'action_label' => 'View Case',
                'action_url' => $base['url'],
            ]);
        }

        foreach ($openIncidents as $incident) {
            $billing = $billingForIncident($incident);
            $borrower = $incident->borrower;
            $status = strtoupper((string) $incident->status);
            $disposition = strtoupper((string) $incident->official_disposition);
            $itemSummary = $incident->lines
                ->map(function ($line) use ($incident): string {
                    $custodyLine = $incident->custody?->lines?->firstWhere('id', $line->custody_line_id);
                    $description = $custodyLine?->requestItem?->description_snapshot ?: 'Inventory item';
                    $quantity = (float) $line->quantity;
                    $qty = fmod($quantity, 1.0) === 0.0
                        ? (string) (int) $quantity
                        : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');

                    return $description.' ×'.$qty;
                })
                ->filter()
                ->implode(', ');

            $base = [
                'key' => 'incident-'.$incident->id,
                'record_type' => 'incident',
                'record_id' => (int) $incident->id,
                'borrower' => $borrower,
                'reference' => $incident->incident_no ?: 'Incident #'.$incident->id,
                'case_type' => 'Property Accountability',
                'issue' => str((string) $incident->incident_type)->replace('_', ' ')->title()->toString().($itemSummary ? ' — '.$itemSummary : ''),
                'amount' => $billing ? 'PHP '.number_format($this->remainingBalance($billing), 2) : null,
                'status' => $status,
                'date' => $incident->reported_at ?: $incident->created_at,
                'tone' => 'warning',
                'url' => $borrower
                    ? route('accountability.borrower', [$borrower, 'tab' => 'cases', 'focus' => 'incident-'.$incident->id]).'#incident-'.$incident->id
                    : null,
            ];

            $headOwned = in_array($status, [
                'OPEN',
                'FOR_BILLING',
                'RSLDDP_AWAITING_UPLOAD',
                'COMPLIANCE_RSLDDP_PENDING',
                'RSLDDP_DISPOSITION_PENDING',
                'RSLDDP_FOR_ACCOUNTING_PROCESSING',
                'RSLDDP_FOR_RESOLUTION',
            ], true) || ($status === 'RSLDDP_COMPLIANCE_VERIFICATION' && $disposition === 'OTHER');

            if ($headOwned) {
                [$stage, $nextStep, $actionLabel] = match ($status) {
                    'OPEN' => ['Accountability Review', 'Review the Action Officer finding and record whether accountability is confirmed or the finding is cleared.', 'Review Finding'],
                    'FOR_BILLING' => ['Billing Decision', 'Record the approved billing details for this property accountability case.', 'Generate Billing'],
                    'RSLDDP_AWAITING_UPLOAD', 'COMPLIANCE_RSLDDP_PENDING' => ['RSLDDP Processing', 'Upload the accomplished RSLDDP received after external signing/notarization processing.', 'Upload RSLDDP'],
                    'RSLDDP_DISPOSITION_PENDING' => ['Official Disposition', 'Record the official disposition exactly as stated in the accomplished RSLDDP.', 'Record Disposition'],
                    'RSLDDP_FOR_ACCOUNTING_PROCESSING' => ['Accounting Billing', 'Record the official Billing Statement issued by Accounting.', 'Record Billing Statement'],
                    'RSLDDP_FOR_RESOLUTION' => ['Final Review', 'Review completed compliance/payment and record the final case resolution.', 'Resolve Case'],
                    default => ['Final Review', 'Review the official disposition and complete the required Head/Admin action.', 'Review Case'],
                };

                $item = $base + [
                    'stage' => $stage,
                    'waiting_on' => 'SPMU Head/Admin',
                    'next_step' => $nextStep,
                    'action_label' => $isHead ? $actionLabel : 'View Case',
                    'action_url' => $base['url'],
                ];
                ($isHead ? $work : $waiting)->push($item);
                continue;
            }

            if (in_array($status, ['BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED'], true)
                || ($status === 'RSLDDP_COMPLIANCE_VERIFICATION' && $disposition === 'MONETARY_SETTLEMENT')) {
                $item = $base + [
                    'stage' => 'Cashier Payment',
                    'waiting_on' => 'Borrower',
                    'next_step' => 'Borrower pays through the CSPC Cashier and presents the official receipt. The Action Officer records the receipt when presented.',
                    'action_label' => $isOfficer ? 'Open Payment Record' : 'View Case',
                    'action_url' => $base['url'],
                ];
                ($isOfficer ? $work : $waiting)->push($item);
                continue;
            }

            if ($status === 'COMPLIANCE_REQUIRED'
                || ($status === 'RSLDDP_COMPLIANCE_VERIFICATION' && in_array($disposition, ['REPAIR', 'REPLACEMENT', 'RETURN_RECOVERY'], true))) {
                $dispositionLabel = $disposition !== ''
                    ? \App\Support\AccountabilityDispositionLabels::officialDispositionLabel($disposition)
                    : 'Property Compliance';
                $item = $base + [
                    'stage' => 'Physical Compliance Verification',
                    'waiting_on' => 'Borrower',
                    'next_step' => 'Borrower completes and presents the required '.$dispositionLabel.'. The Action Officer physically verifies it before final Head/Admin review.',
                    'action_label' => $isOfficer ? 'Verify '.$dispositionLabel : 'View Case',
                    'action_url' => $base['url'],
                ];
                ($isOfficer ? $work : $waiting)->push($item);
                continue;
            }

            $waiting->push($base + [
                'stage' => str($status)->replace('_', ' ')->title()->toString(),
                'waiting_on' => 'SPMU Processing',
                'next_step' => 'Continue the current property-accountability process from the borrower case workspace.',
                'action_label' => 'View Case',
                'action_url' => $base['url'],
            ]);
        }

        // A genuinely standalone billing has no open Incident/OverdueCase to
        // represent it, so it must still appear in the AO operational queue.
        $claimedBillingIds = $openIncidents
            ->map(fn (Incident $incident) => $billingForIncident($incident)?->id)
            ->merge($openOverdueCases->map(fn (OverdueCase $overdue) => $billingForOverdue($overdue)?->id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($openBillings->reject(fn (BillingStatement $billing) => $claimedBillingIds->contains((int) $billing->id)) as $billing) {
            $borrower = $billing->borrower;
            $item = [
                'key' => 'billing-'.$billing->id,
                'record_type' => 'billing',
                'record_id' => (int) $billing->id,
                'borrower' => $borrower,
                'reference' => $billing->billing_no,
                'case_type' => 'Financial Obligation',
                'issue' => $billing->lines->map(fn ($line) => str((string) $line->line_type)->replace('_', ' ')->title())->unique()->implode(', ') ?: 'Billing Statement',
                'amount' => 'PHP '.number_format($this->remainingBalance($billing), 2),
                'status' => (string) $billing->status,
                'stage' => 'Cashier Payment',
                'waiting_on' => 'Borrower',
                'next_step' => 'Borrower pays through the CSPC Cashier and presents the official receipt. The Action Officer records the receipt when presented.',
                'action_label' => $isOfficer ? 'Open Payment Record' : 'View Borrower',
                'action_url' => $borrower ? route('accountability.borrower', [$borrower, 'tab' => 'cases']) : null,
                'url' => $borrower ? route('accountability.borrower', [$borrower, 'tab' => 'cases']) : null,
                'date' => $billing->issued_at ?: $billing->created_at,
                'tone' => 'warning',
            ];
            ($isOfficer ? $work : $waiting)->push($item);
        }

        $sort = static fn (Collection $items): Collection => $items
            ->sortByDesc(fn (array $item) => optional($item['date'] ?? null)->timestamp ?? 0)
            ->values();

        return [
            'work' => $sort($work),
            'waiting' => $sort($waiting),
        ];
    }

    private function remainingBalance(BillingStatement $billing): float
    {
        $verified = (float) $billing->payments
            ->where('status', 'VERIFIED')
            ->sum(fn ($payment): float => (float) $payment->amount);

        return max(0, (float) $billing->total_amount - $verified);
    }
}
