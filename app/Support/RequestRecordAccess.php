<?php

namespace App\Support;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Whether a user may open a request's detail page, for screens that link to it.
 *
 * The request page itself decides access (BorrowingRequestController); this
 * class only mirrors that rule so a list never offers a "View Record" link
 * that would answer 403. It grants nothing: the request page still enforces
 * its own check on every visit.
 */
final class RequestRecordAccess
{
    /**
     * Drop the record link from rows whose request the viewer cannot open.
     *
     * Rows without a `_request_id` (custody records, summaries) are left as
     * they are; rows that lose their link are flagged `_link_restricted`.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function withoutUnviewableLinks(Collection $rows, ?User $user, ?string $workspace): Collection
    {
        $ids = $rows
            ->filter(fn (array $row): bool => filled($row['_link'] ?? null) && isset($row['_request_id']))
            ->pluck('_request_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $rows;
        }

        $viewable = self::viewableIds($user, $workspace, $ids);

        return $rows->map(function (array $row) use ($viewable): array {
            if (
                filled($row['_link'] ?? null)
                && isset($row['_request_id'])
                && ! isset($viewable[(int) $row['_request_id']])
            ) {
                $row['_link'] = null;
                $row['_link_restricted'] = true;
            }

            return $row;
        });
    }

    /**
     * @param  list<int>  $requestIds
     * @return array<int, true> the ids the user may open, as keys
     */
    public static function viewableIds(?User $user, ?string $workspace, array $requestIds): array
    {
        if ($user === null || $requestIds === []) {
            return [];
        }

        $workspace = strtoupper((string) $workspace);
        $classification = $user->access_classification;
        $inSpmu = $workspace === 'SPMU';
        $primaryIsSpmu = $user->primaryWorkspace() === 'SPMU';

        $isHead = $classification === AccessClassification::SpmuHead;
        $isOfficer = $classification === AccessClassification::SpmuOfficer;
        $isDelegate = $isOfficer && $user->activeDelegationFor('SPMU') !== null;

        $viewable = [];

        BorrowingRequest::query()
            ->whereIn('id', $requestIds)
            ->with('currentVersion.approvalSteps')
            ->get()
            ->each(function (BorrowingRequest $request) use (
                $user, $workspace, $inSpmu, $primaryIsSpmu, $isHead, $isOfficer, $isDelegate, &$viewable
            ): void {
                $underReview = $request->status === RequestStatus::UnderSpmu;
                $steps = $request->currentVersion?->approvalSteps ?? collect();

                $spmuStepIsOpen = static function (int $sequence) use ($steps): bool {
                    $step = $steps
                        ->where('sequence_no', $sequence)
                        ->first(fn ($item) => (string) $item->stage_code->value === 'SPMU');

                    return $step !== null && in_array($step->decision, ['PENDING', 'RECEIVED'], true);
                };

                /* During review, access follows the current step: 1 = Action Officer, 2 = Head / Admin. */
                $canVerify = $underReview && $primaryIsSpmu && $isOfficer && $spmuStepIsOpen(1);
                $canDecide = $underReview && $primaryIsSpmu && ($isHead || $isDelegate) && $spmuStepIsOpen(2);

                $isBorrowerOwner = $workspace === 'BORROWER' && $request->borrower_user_id === $user->id;
                $isActiveReviewer = $underReview && ($canVerify || $canDecide);
                $isOperationalOfficer = $inSpmu && $isOfficer && $request->final_approved_at !== null;

                $hasSpmuHistory = $request->final_approved_at !== null
                    || $steps->contains(fn ($item) => (string) $item->stage_code->value === 'SPMU');

                $isHistoricalViewer = ! $underReview
                    && $hasSpmuHistory
                    && $inSpmu
                    && ($isHead || $isDelegate);

                if ($isBorrowerOwner || $isActiveReviewer || $isOperationalOfficer || $isHistoricalViewer) {
                    $viewable[(int) $request->id] = true;
                }
            });

        return $viewable;
    }
}
