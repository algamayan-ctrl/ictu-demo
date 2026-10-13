<?php

namespace App\Services;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BillingStatement;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\LaundryJob;
use App\Models\OverdueCase;
use App\Models\User;

/** Read-only badge totals. These never affect workflow or database state. */
class SidebarActionCountService
{
    /** @return array<string,int> Counts keyed by the existing sidebar route name. */
    public function counts(User $user): array
    {
        $workspace = $user->primaryWorkspace();

        if ($workspace === 'BORROWER') {
            return $this->borrowerCounts((int) $user->id);
        }

        if ($workspace !== 'SPMU') {
            return []; // No ICTU badge implementation; other roles are out of scope.
        }

        $classification = $user->access_classification;
        if (! in_array($classification, [AccessClassification::SpmuHead, AccessClassification::SpmuOfficer], true)) {
            return [];
        }

        $counts = [];
        if ($classification === AccessClassification::SpmuHead) {
            // Exactly matches the SPMU Head approval queue, not total unreviewed requests.
            $counts['approvals.index'] = $this->approvalCount(2);
        } else {
            // Exactly matches the AO verification, Release and Return workspaces.
            $counts['verifications.index'] = $this->approvalCount(1);
            $counts['custody.release.index'] = CustodyTransaction::query()
                ->where('status', 'PREPARING_RELEASE')
                ->whereNull('released_at')
                ->count();
            $counts['custody.return.index'] = CustodyTransaction::query()
                ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
                ->whereNotNull('released_at')
                ->whereHas('lines', fn ($line) => $line
                    ->whereColumn('returned_quantity', '<', 'actual_released_quantity'))
                ->count();

            // Pending laundry workflows, including off-site physical processing.
            // Unlike approval counters, this is a WORKFLOW total, not a claim
            // that Laundry Personnel have returned the signed paper already.
            $counts['laundry.index'] = LaundryJob::query()
                ->where('status', '!=', 'LAUNDRY_COMPLETED')
                ->count();
        }

        // Count only items in the role's "Needs My Decision / Action" list.
        // Do not use total active cases/restrictions, which are monitoring figures.
        $counts['accountability.index'] = $this->staffAccountabilityWorkCount($classification);

        return $counts;
    }

    /** @return array<string,int> */
    private function borrowerCounts(int $userId): array
    {
        $now = now();

        // Draft and returned-for-revision requests can be edited/resubmitted.
        $requests = BorrowingRequest::query()
            ->where('borrower_user_id', $userId)
            ->whereIn('status', [
                RequestStatus::Draft->value,
                RequestStatus::ReturnedForRevision->value,
            ])
            ->count();

        // Count distinct custody transactions only: eligible pickup or
        // outstanding return due by end of tomorrow, including overdue.
        $borrowings = CustodyTransaction::query()
            ->where('borrower_user_id', $userId)
            ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
            ->where(function ($q) use ($now): void {
                $q->where(function ($pickup) use ($now): void {
                    $pickup->whereNull('released_at')
                        ->whereNotNull('scheduled_release_at')
                        ->whereNull('pickup_expired_at')
                        ->where(function ($window) use ($now): void {
                            $window->whereNull('pickup_expires_at')
                                ->orWhere('pickup_expires_at', '>=', $now);
                        });
                })->orWhere(function ($return) use ($now): void {
                    $return->whereNotNull('released_at')
                        ->whereNotNull('due_at')
                        ->where('due_at', '<=', $now->copy()->addDay()->endOfDay())
                        ->whereHas('lines', fn ($line) => $line
                            ->whereColumn('returned_quantity', '<', 'actual_released_quantity'));
                });
            })
            ->count();

        // Same grouped, unresolved obligations shown by My Obligations;
        // linked incident, billing, restriction form one obligation.
        $obligations = count(app(BorrowerObligationService::class)->obligationRows($userId));

        return [
            'requests.index' => $requests,
            'custody.index' => $borrowings,
            'accountability.index' => $obligations,
        ];
    }

    /** @return array<string, 'normal'|'soon'|'urgent'> */
    public function tones(User $user): array
    {
        $workspace = $user->primaryWorkspace();
        if ($workspace === 'BORROWER') {
            return [
                'accountability.index' => 'urgent',
                'custody.index' => $this->returnUrgency((int) $user->id, true),
            ];
        }

        if ($workspace !== 'SPMU' || $user->access_classification !== AccessClassification::SpmuOfficer) {
            return $workspace === 'SPMU' ? ['accountability.index' => 'urgent'] : [];
        }

        return [
            'accountability.index' => 'urgent',
            'custody.return.index' => $this->returnUrgency(null, false),
        ];
    }

    /**
     * Only use red for a real overdue outstanding return and amber for a
     * real due-soon outstanding return/pickup. Monitoring totals remain blue.
     */
    private function returnUrgency(?int $borrowerId, bool $includePickup): string
    {
        $now = now();
        $endTomorrow = $now->copy()->addDay()->endOfDay();

        $outstanding = static function () use ($borrowerId) {
            return CustodyTransaction::query()
                ->when($borrowerId !== null, fn ($query) => $query->where('borrower_user_id', $borrowerId))
                ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
                ->whereNotNull('released_at')
                ->whereHas('lines', fn ($line) => $line
                    ->whereColumn('returned_quantity', '<', 'actual_released_quantity'));
        };

        if ($outstanding()->whereNotNull('due_at')->where('due_at', '<', $now)->exists()) {
            return 'urgent';
        }

        if ($outstanding()->whereBetween('due_at', [$now, $endTomorrow])->exists()) {
            return 'soon';
        }

        if ($includePickup && $borrowerId !== null) {
            $pickupSoon = CustodyTransaction::query()
                ->where('borrower_user_id', $borrowerId)
                ->whereNotIn('status', ['CLOSED', 'CANCELLED'])
                ->whereNull('released_at')
                ->whereNotNull('scheduled_release_at')
                ->where('scheduled_release_at', '<=', $endTomorrow)
                ->whereNull('pickup_expired_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('pickup_expires_at')
                        ->orWhere('pickup_expires_at', '>=', $now);
                })
                ->exists();

            if ($pickupSoon) {
                return 'soon';
            }
        }

        return 'normal';
    }

    private function approvalCount(int $sequence): int
    {
        return BorrowingRequest::query()
            ->where('status', RequestStatus::UnderSpmu)
            ->whereHas('currentVersion.approvalSteps', fn ($step) => $step
                ->where('stage_code', 'SPMU')
                ->where('sequence_no', $sequence)
                ->whereIn('decision', ['PENDING', 'RECEIVED']))
            ->count();
    }

    private function staffAccountabilityWorkCount(AccessClassification $classification): int
    {
        // Match the relations/role-specific classification of the existing
        // AccountabilityController + AccountabilityWorkQueueService::build().
        $incidents = Incident::query()
            ->with(['borrower', 'custody.lines.requestItem', 'lines'])
            ->whereNotIn('status', ['RESOLVED', 'CLOSED', 'VOID_CORRECTION'])
            ->get();

        $overdueCases = OverdueCase::query()
            ->with(['borrower', 'custody'])
            ->where('status', '!=', 'RESOLVED')
            ->get();

        $billings = BillingStatement::query()
            ->with(['borrower', 'lines.penalty', 'payments'])
            ->whereNotIn('status', ['SETTLED', 'WAIVED', 'VOID'])
            ->get();

        return app(AccountabilityWorkQueueService::class)
            ->build($classification, $incidents, $overdueCases, $billings)['work']
            ->count();
    }
}
