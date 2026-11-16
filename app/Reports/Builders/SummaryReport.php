<?php

namespace App\Reports\Builders;

use App\Enums\RequestStatus;
use App\Models\BorrowingRequest;
use App\Models\RequestVersion;
use App\Reports\ReportBuilder;
use App\Reports\ReportDataset;
use App\Reports\ReportFilters;
use App\Support\ReportAffiliation;
use App\Support\RequestReviewFacts;
use Carbon\Carbon;

class SummaryReport implements ReportBuilder
{
    // This is the function for the generation of the Summary Report for the selected dates.
    public function build(ReportFilters $filters): ReportDataset
    {
        $versions = RequestReviewFacts::latestSubmittedVersions()
            ->whereBetween('submitted_at', [$filters->from, $filters->to])
            ->with(['request.borrower', 'request.custody', 'borrowerSignature', 'items.inventoryItem', 'approvalSteps'])
            ->orderByDesc('submitted_at')->orderByDesc('id')->get();

        $rows = $versions->filter(function (RequestVersion $version) use ($filters): bool {
            return $version->request !== null
                && (! $filters->get('division') || ReportAffiliation::divisionCode($version) === $filters->get('division'))
                && (! $filters->get('unit') || strcasecmp(ReportAffiliation::employeeUnit($version), $filters->get('unit')) === 0)
                && (! $filters->get('borrower') || (int) $version->request->borrower_user_id === (int) $filters->get('borrower'));
        })->map(function (RequestVersion $version): array {
            $request = $version->request;
            $received = RequestReviewFacts::firstReview($version);
            $released = $request->custody?->released_at;
            $elapsed = $received && $released && $released->gte($received)
                ? (int) floor($received->diffInSeconds($released) / 60) : null;
            $office = ReportAffiliation::display($version) ?: 'Not recorded';
            $division = ReportAffiliation::division($version) ?: 'Not recorded';
            $equipment = $version->items->map(fn ($line): string =>
                $line->requested_quantity.' - '.($line->description_snapshot ?: $line->inventoryItem?->unique_description ?: 'Not recorded')
            )->implode("\n");

            return [
                '_request_id' => $request->id,
                '_borrower_user_id' => $request->borrower_user_id,
                'request_no' => (string) $request->request_no,
                'borrower' => $version->borrowerSignature?->signer_name ?: ($request->borrower?->full_name ?: 'Not recorded'),
                'office_department' => $office.' — '.$division,
                'equipment' => $equipment ?: 'Not recorded',
                'date_needed' => $this->date($version->schedule_date ?: $version->needed_from),
                'received_at' => $received ? $this->date($received, true) : 'Not reviewed / not recorded',
                'released_at' => $released ? $this->date($released, true) : 'Not released',
                'elapsed' => $elapsed !== null ? intdiv($elapsed, 60).'h '.($elapsed % 60).'m' : ($released ? 'Not recorded' : 'Not released'),
                'remarks' => $released ? 'MET' : 'UNMET',
            ];
        })->values();

        $undated = BorrowingRequest::query()->where('status', '!=', RequestStatus::Draft->value)
            ->whereDoesntHave('versions', fn ($query) => $query->whereNotNull('submitted_at'))->count();

        $fullMonth = $filters->from->day === 1
            && $filters->to->isSameDay($filters->from->copy()->endOfMonth());
        $periodTitle = $fullMonth
            ? 'FOR THE MONTH OF '.strtoupper($filters->from->format('F Y'))
            : 'FOR THE PERIOD '.strtoupper($filters->from->format('d F Y').' – '.$filters->to->format('d F Y'));

        return new ReportDataset(
            reportKey: 'summary',
            label: 'SUMMARY OF REQUESTS FOR EQUIPMENTS '.$periodTitle,
            columns: [
                ['key' => 'request_no', 'label' => 'Control No.'],
                ['key' => 'borrower', 'label' => 'Borrower'],
                ['key' => 'office_department', 'label' => 'Office & Department'],
                ['key' => 'equipment', 'label' => 'Req. Equipment'],
                ['key' => 'date_needed', 'label' => 'Date Needed'],
                ['key' => 'received_at', 'label' => 'Received Date & Time'],
                ['key' => 'released_at', 'label' => 'Released Date & Time'],
                ['key' => 'elapsed', 'label' => 'Total Time Allocated'],
                ['key' => 'remarks', 'label' => 'Remarks'],
            ],
            rows: $rows,
            notice: $undated ? $undated.' historical request(s) have no recorded submission time and cannot be assigned to a reporting month.' : null,
        );
    }

    private function date(mixed $value, bool $time = false): string
    {
        return $value ? Carbon::parse($value)->timezone('Asia/Manila')->format($time ? 'd/m/Y h:i A' : 'd/m/Y') : 'Not recorded';
    }
}
