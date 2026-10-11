<?php

namespace App\Services;

use App\Models\BorrowingRequest;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\RequestRecordAccess;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, cross-workflow event ledger for the Reports landing screen.
 * Each source row represents its own recorded event, not another copy of a request.
 * SQL pagination is applied before item details are hydrated.
 */
final class AllTransactionsService
{
    public const TYPES = [
        'Borrowing', 'Approval', 'Issuance', 'Return', 'Gate Pass',
        'Laundry', 'Incident', 'Penalty', 'Billing', 'Payment',
        'Inventory Adjustment',
    ];

    /** @return array{records:LengthAwarePaginator, filters:array<string, string>} */
    public function page(Request $request, User $viewer): array
    {
        $data = $request->validate([
            'type' => 'nullable|string|in:'.implode(',', self::TYPES),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|in:10,25,50,100',
            'page' => 'nullable|integer|min:1',
        ]);

        if (! empty($data['from']) && ! empty($data['to']) && $data['to'] < $data['from']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'End date must not precede start date.']);
        }

        $filters = [
            'type' => $data['type'] ?? '',
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
            'search' => trim($data['search'] ?? ''),
        ];

        $query = $this->query();

        if ($filters['type'] !== '') {
            $query->where('events.transaction_type', $filters['type']);
        }
        if ($filters['from'] !== '') {
            $query->where('events.occurred_at', '>=', $filters['from'].' 00:00:00');
        }
        if ($filters['to'] !== '') {
            $query->where('events.occurred_at', '<=', $filters['to'].' 23:59:59');
        }
        if ($filters['search'] !== '') {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('events.reference_no', 'like', $term)
                    ->orWhere('events.transaction_type', 'like', $term)
                    ->orWhere('events.status', 'like', $term)
                    ->orWhere('people.full_name', 'like', $term)
                    ->orWhere('units.unit_name', 'like', $term);
            });
        }

        $perPage = (int) ($data['per_page'] ?? 10);
        $records = $query
            ->orderByDesc('events.occurred_at')
            ->orderByDesc('events.record_id')
            ->orderBy('events.transaction_type')
            ->paginate($perPage)
            ->withQueryString();

        $requestIds = collect($records->items())->pluck('request_id')->filter()->unique()->all();
        $requests = BorrowingRequest::query()
            ->whereIn('id', $requestIds)
            ->with('currentVersion.items')
            ->get()
            ->keyBy('id');

        $adjustmentItemIds = collect($records->items())
            ->where('transaction_type', 'Inventory Adjustment')
            ->pluck('item_id')->filter()->unique()->all();
        $adjustmentItems = InventoryItem::query()
            ->whereIn('id', $adjustmentItemIds)
            ->pluck('unique_description', 'id');

        $idsForType = fn (string $type): array => collect($records->items())
            ->where('transaction_type', $type)->pluck('record_id')->all();
        $released = DB::table('custody_lines')
            ->whereIn('custody_transaction_id', $idsForType('Issuance'))
            ->selectRaw('custody_transaction_id, SUM(actual_released_quantity) AS total')
            ->groupBy('custody_transaction_id')->pluck('total', 'custody_transaction_id');
        $returned = DB::table('return_lines')
            ->whereIn('return_transaction_id', $idsForType('Return'))
            ->selectRaw('return_transaction_id, SUM(quantity_received) AS total')
            ->groupBy('return_transaction_id')->pluck('total', 'return_transaction_id');
        $laundryQuantities = DB::table('laundry_job_lines')
            ->whereIn('laundry_job_id', $idsForType('Laundry'))
            ->selectRaw('laundry_job_id, SUM(issued_quantity) AS total')
            ->groupBy('laundry_job_id')->pluck('total', 'laundry_job_id');

        $viewable = RequestRecordAccess::viewableIds(
            $viewer,
            $request->session()->get('active_workspace'),
            array_map('intval', $requestIds)
        );

        $records->setCollection($records->getCollection()->map(function ($row) use (
            $requests, $adjustmentItems, $viewable, $released, $returned, $laundryQuantities
        ) {
            $request = $row->request_id ? $requests->get((int) $row->request_id) : null;
            $items = $request?->currentVersion?->items ?? collect();
            $row->items_text = $items->pluck('description_snapshot')->filter()->unique()->implode(', ');
            $row->quantity = match ($row->transaction_type) {
                'Borrowing', 'Approval' => $items->isEmpty() ? null : $items->sum('requested_quantity'),
                'Issuance' => $released->get($row->record_id),
                'Return' => $returned->get($row->record_id),
                'Laundry' => $laundryQuantities->get($row->record_id),
                default => null,
            };

            if ($row->transaction_type === 'Inventory Adjustment') {
                $row->items_text = $adjustmentItems->get((int) $row->item_id, '—');
                $row->quantity = null;
            }

            $row->items_text = $row->items_text !== '' ? $row->items_text : '—';
            $row->person_name = trim((string) $row->person_name) ?: '—';
            $row->unit_name = trim((string) $row->unit_name) ?: '—';
            $row->status_label = ucwords(strtolower(str_replace('_', ' ', (string) $row->status)));
            $row->date_label = Carbon::parse($row->occurred_at)->format('d M Y, g:i A');
            $row->can_view = $row->request_id !== null && isset($viewable[(int) $row->request_id]);
            $row->view_url = $row->can_view ? route('requests.show', ['borrowingRequest' => $row->request_id]) : null;

            return $row;
        }));

        return compact('records', 'filters');
    }

    /**
     * All branches project the same fields. No date restriction is imposed
     * unless the operator explicitly selects one.
     */
    private function query(): Builder
    {
        $branches = [];
        // SQLite is used in the isolated test suite; MariaDB is used in deployment.
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $label = static fn (string $prefix, string $column): string => $sqlite
            ? "'".$prefix."' || ".$column
            : "CONCAT('".$prefix."', ".$column.")";
        $add = static function (Builder $query, string $type, string $columns) use (&$branches): void {
            $branches[] = $query->selectRaw('? AS transaction_type, '.$columns, [$type]);
        };

        $add(DB::table('borrowing_requests as b')
            ->join('request_versions as v', function ($join): void {
                $join->on('v.request_id', '=', 'b.id')->on('v.version_no', '=', 'b.current_version_no');
            })->whereNotNull('v.submitted_at'),
            'Borrowing',
            'b.id AS record_id, b.request_no AS reference_no, b.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, b.status AS status, v.submitted_at AS occurred_at');

        $add(DB::table('approval_steps as a')
            ->join('request_versions as v', 'v.id', '=', 'a.request_version_id')
            ->join('borrowing_requests as b', 'b.id', '=', 'v.request_id')
            ->whereNotNull('a.decided_at'),
            'Approval',
            'a.id AS record_id, '.$label('Approval #', 'a.id').' AS reference_no, b.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, a.decision AS status, a.decided_at AS occurred_at');

        $add(DB::table('custody_transactions as c')
            ->join('borrowing_requests as b', 'b.id', '=', 'c.request_id')
            ->whereNotNull('c.released_at'),
            'Issuance',
            'c.id AS record_id, c.custody_no AS reference_no, c.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, c.status AS status, c.released_at AS occurred_at');

        $add(DB::table('return_transactions as r')
            ->join('custody_transactions as c', 'c.id', '=', 'r.custody_transaction_id')
            ->join('borrowing_requests as b', 'b.id', '=', 'c.request_id'),
            'Return',
            'r.id AS record_id, r.return_no AS reference_no, c.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, r.status AS status, r.received_at AS occurred_at');

        $add(DB::table('gate_passes as g')
            ->join('custody_transactions as c', 'c.id', '=', 'g.custody_transaction_id')
            ->join('borrowing_requests as b', 'b.id', '=', 'c.request_id'),
            'Gate Pass',
            'g.id AS record_id, '.$label('Gate Pass #', 'g.id').' AS reference_no, c.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, g.status AS status, g.created_at AS occurred_at');

        $add(DB::table('laundry_jobs as l')
            ->join('custody_transactions as c', 'c.id', '=', 'l.custody_transaction_id')
            ->join('borrowing_requests as b', 'b.id', '=', 'c.request_id'),
            'Laundry',
            'l.id AS record_id, '.$label('Laundry #', 'l.id').' AS reference_no, c.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, l.status AS status, l.created_at AS occurred_at');

        $add(DB::table('incidents as i')
            ->join('custody_transactions as c', 'c.id', '=', 'i.custody_transaction_id')
            ->join('borrowing_requests as b', 'b.id', '=', 'c.request_id'),
            'Incident',
            'i.id AS record_id, i.incident_no AS reference_no, i.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, i.status AS status, i.reported_at AS occurred_at');

        $add(DB::table('penalties as p')
            ->leftJoin('custody_transactions as c', 'c.id', '=', 'p.custody_transaction_id')
            ->leftJoin('borrowing_requests as b', 'b.id', '=', 'c.request_id'),
            'Penalty',
            'p.id AS record_id, '.$label('Penalty #', 'p.id').' AS reference_no, p.borrower_user_id AS person_id, b.accountable_unit_id AS unit_id, b.id AS request_id, NULL AS item_id, p.status AS status, p.assessed_at AS occurred_at');

        $add(DB::table('billing_statements as s'),
            'Billing',
            's.id AS record_id, s.billing_no AS reference_no, s.borrower_user_id AS person_id, NULL AS unit_id, NULL AS request_id, NULL AS item_id, s.status AS status, s.issued_at AS occurred_at');

        $add(DB::table('payments as p')
            ->join('billing_statements as s', 's.id', '=', 'p.billing_statement_id'),
            'Payment',
            "p.id AS record_id, COALESCE(NULLIF(p.official_receipt_no, ''), ".$label('Payment #', 'p.id').") AS reference_no, s.borrower_user_id AS person_id, NULL AS unit_id, NULL AS request_id, NULL AS item_id, p.status AS status, COALESCE(p.verified_at, p.submitted_at, p.created_at) AS occurred_at");

        $add(DB::table('inventory_adjustments as ia'),
            'Inventory Adjustment',
            'ia.id AS record_id, ia.adjustment_no AS reference_no, ia.requested_by_user_id AS person_id, NULL AS unit_id, NULL AS request_id, ia.inventory_item_id AS item_id, ia.status AS status, ia.requested_at AS occurred_at');

        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return DB::query()->fromSub($union, 'events')
            ->leftJoin('users as people', 'people.id', '=', 'events.person_id')
            ->leftJoin('organizational_units as units', 'units.id', '=', 'events.unit_id')
            ->select('events.*', 'people.full_name AS person_name', 'units.unit_name AS unit_name');
    }
}
