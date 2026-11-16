<?php

namespace App\Support;

use App\Models\LaundryJob;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

// This is the search and filter view of Laundry records before pagination.
class LaundryRecordTable
{
    public static function forRequest(Request $request, bool $completed): LengthAwarePaginator
    {
        $prefix = $completed ? 'completed' : 'active';
        $filters = $request->validate([
            $prefix.'_q' => ['nullable', 'string', 'max:200'],
            $prefix.'_status' => ['nullable', Rule::in(['ALL', 'LAUNDRY_FORM_PENDING', 'READY_FOR_SPMU_ENCODING', 'RECONCILIATION_PENDING'])],
            $prefix.'_outcome' => ['nullable', Rule::in(['ALL', 'available', 'accountability'])],
            $prefix.'_sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            $prefix.'_per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            $prefix.'_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $query = LaundryJob::query()->with([
            'custody.borrower.organizationalUnit', 'custody.request.currentVersion', 'custody.returns',
            'latestEvidence.file', 'lines.custodyLine.requestItem.inventoryItem.unit', 'lines.custodyLine.returnLines',
        ])->where('status', $completed ? '=' : '!=', 'LAUNDRY_COMPLETED');
        if (! $completed) {
            $query->orderByRaw("CASE WHEN status = 'FOR_LAUNDRY' THEN 1 WHEN status = 'TURNED_OVER_TO_LAUNDRY' THEN 2 ELSE 3 END");
        }
        $query->orderBy($completed ? 'completed_at' : 'updated_at', ($filters[$prefix.'_sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc')->orderByDesc('id');
        $rows = $query->get()->filter(function (LaundryJob $job) use ($filters, $prefix, $completed): bool {
            $text = implode(' ', [$job->id, 'LND-'.($job->created_at ? $job->created_at->format('Y').'-' : '').str_pad((string) $job->id, 5, '0', STR_PAD_LEFT),
                $job->custody?->request?->request_no, $job->custody?->custody_no, $job->custody?->borrower?->full_name,
                $job->custody?->borrower?->organizationalUnit?->unit_name, $job->custody?->request?->currentVersion?->purpose_event,
                $job->lines->map(fn ($line) => $line->custodyLine?->requestItem?->description_snapshot)->implode(' ')]);
            $terms = preg_split('/\s+/u', mb_strtolower(trim($filters[$prefix.'_q'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            if (! collect($terms)->every(fn ($term) => str_contains(mb_strtolower($text), $term))) return false;
            if (! $completed) {
                $status = match (true) {
                    $job->status === 'FOR_LAUNDRY' && $job->hasVerifiedAccomplishedForm() => 'READY_FOR_SPMU_ENCODING',
                    $job->status === 'FOR_LAUNDRY' => 'LAUNDRY_FORM_PENDING',
                    $job->status === 'TURNED_OVER_TO_LAUNDRY' => 'RECONCILIATION_PENDING',
                    default => $job->status,
                };

                return ($filters['active_status'] ?? 'ALL') === 'ALL' || $status === $filters['active_status'];
            }
            $outcome = $filters['completed_outcome'] ?? 'ALL';
            if ($outcome === 'available') return (int) $job->lines->sum('completed_quantity') > 0;
            if ($outcome === 'accountability') {
                $adverse = $job->lines->flatMap(fn ($line) => $line->custodyLine?->returnLines ?? collect())
                    ->contains(fn ($line) => ! in_array(strtoupper((string) $line->condition_code), ['', 'FINE'], true));
                $due = $job->custody?->original_due_at;
                $late = $job->worker_received_at && $due && $job->worker_received_at->copy()->startOfDay()->gt($due->copy()->startOfDay());

                return $adverse || $late;
            }

            return true;
        });

        return RecordTable::paginate($rows, $request, $prefix, (int) ($filters[$prefix.'_per_page'] ?? 20));
    }
}
