<?php

namespace App\Reports\Builders;

use App\Reports\ReportBuilder;
use App\Reports\ReportDataset;
use App\Reports\ReportFilters;
use App\Reports\RequestOverviewStatus;
use App\Support\ReportAffiliation;
use App\Support\RequestReviewFacts;

// This is the function for the generation of one row per submitted request.
class RequestOverviewReport implements ReportBuilder
{
    public function build(ReportFilters $filters): ReportDataset
    {
        $versions = RequestReviewFacts::latestSubmittedVersions()
            ->whereBetween('submitted_at', [$filters->from, $filters->to])
            ->with(['approvalSteps', 'borrowerSignature', 'items.inventoryItem', 'request.borrower',
                'request.custody.returns', 'request.custody.incidents', 'request.custody.overdueCase'])
            ->orderByDesc('submitted_at')->orderByDesc('id')->get();
        $rows = $versions->filter(fn ($version) => $version->request !== null
            && (! $filters->get('division') || ReportAffiliation::divisionCode($version) === $filters->get('division'))
            && (! $filters->get('unit') || strcasecmp(ReportAffiliation::employeeUnit($version), $filters->get('unit')) === 0)
            && (! $filters->get('borrower') || (int) $version->request->borrower_user_id === (int) $filters->get('borrower')))
            ->map(function ($version): array {
                $request = $version->request;
                [$key, $label] = RequestOverviewStatus::forRequest($request);
                $review = RequestReviewFacts::firstReview($version);

                return [
                    '_request_id' => $request->id, '_borrower_user_id' => $request->borrower_user_id,
                    '_link' => route('requests.show', $request), '_status_code' => $key,
                    'request_no' => (string) $request->request_no,
                    'borrower' => $version->borrowerSignature?->signer_name ?: ($request->borrower?->full_name ?: 'Not recorded'),
                    'office_unit' => ReportAffiliation::display($version) ?: 'Not recorded',
                    'equipment' => $version->items->map(fn ($line) => ($line->requested_quantity + 0).' '.($line->description_snapshot ?: $line->inventoryItem?->unique_description ?: 'Not recorded'))->implode("\n") ?: 'Not recorded',
                    'date_needed' => RequestReviewFacts::date($version->needed_from ?: $version->schedule_date),
                    'received_at' => $review ? RequestReviewFacts::date($review, true) : 'Not reviewed / not recorded',
                    'status' => $label,
                ];
            })
            ->filter(fn ($row) => ! $filters->get('request_status') || $row['_status_code'] === $filters->get('request_status'))->values();
        $undated = \App\Models\BorrowingRequest::query()->where('status', '!=', \App\Enums\RequestStatus::Draft->value)
            ->whereDoesntHave('versions', fn ($query) => $query->whereNotNull('submitted_at'))->count();

        return new ReportDataset('all-transactions', 'All Transactions (General Overview)', [
            ['key' => 'request_no', 'label' => 'Control No.'], ['key' => 'borrower', 'label' => 'Borrower'],
            ['key' => 'office_unit', 'label' => 'Requesting Office/College/Unit'], ['key' => 'equipment', 'label' => 'Requested Equipment'],
            ['key' => 'date_needed', 'label' => 'Date/s Needed'], ['key' => 'received_at', 'label' => 'Received Date & Time'],
            ['key' => 'status', 'label' => 'Status'],
        ], $rows, ['Requests' => $rows->count()], notice: $undated
            ? $undated.' historical request(s) have no recorded submission time and cannot be assigned to a reporting period.' : null);
    }
}
