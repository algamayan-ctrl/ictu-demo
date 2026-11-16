<?php

namespace App\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// This is the shared matching dataset for screen, print and downloaded reports.
final class ReportSearch
{
    public static function apply(ReportDataset $dataset, ?string $search): ReportDataset
    {
        $ids = $dataset->rows->pluck('_request_id')->filter()->unique();
        $aliases = $ids->isNotEmpty() && Schema::hasTable('request_number_aliases')
            ? DB::table('request_number_aliases')->whereIn('request_id', $ids)->get()->groupBy('request_id') : collect();
        $rows = $dataset->rows->map(function ($row) use ($aliases) {
            $row['_search_aliases'] = ($aliases->get($row['_request_id'] ?? 0) ?? collect())->pluck('alias')->implode(' ');
            return $row;
        });
        if ($search === null || $search === '') {
            return new ReportDataset($dataset->reportKey, $dataset->label, $dataset->columns, $rows, $dataset->summary, $dataset->meta, $dataset->notice);
        }
        $needle = mb_strtolower($search);
        $keys = $dataset->columnKeys();
        $rows = $rows->filter(function ($row) use ($keys, $needle): bool {
            $text = implode(' ', array_map(fn ($key) => (string) ($row[$key] ?? ''), $keys)).' '.($row['_search_aliases'] ?? '');
            return str_contains(mb_strtolower($text), $needle);
        })->values();
        $summary = match ($dataset->reportKey) {
            'summary' => [],
            'all-transactions' => ['Requests' => $rows->count()],
            'inventory' => ['Tracked inventory items' => $rows->count(), 'Available to allocate' => $rows->sum('_available'),
                'Allocated' => $rows->sum('_allocated'), 'On custody' => $rows->sum('_borrowed')],
            'utilization' => ['Active items compared' => $rows->count(), 'Items with utilization' => $rows->filter(fn ($row) => $row['_released'] > 0)->count(),
                'No utilization' => $rows->filter(fn ($row) => $row['_released'] <= 0)->count(), 'Released quantity' => $rows->sum('_released'),
                'Released outside CSPC division columns (External Borrowers / no recorded division)' => $rows->sum('_outside_divisions')],
            'accountability-cases' => ['Cases' => $rows->count(), 'Property accountability' => $rows->where('case_type', 'Property Accountability')->count(),
                'Late return' => $rows->where('case_type', 'Late Return')->count(), 'With active restriction' => $rows->where('restriction_status', 'Active')->count()],
            'billing-settlement' => ['Statements' => $rows->count(), 'Paid' => $rows->where('payment_status', 'Paid')->count(),
                'Partially paid' => $rows->where('payment_status', 'Partially Paid')->count(), 'Unpaid' => $rows->where('payment_status', 'Unpaid')->count()],
            default => ['Matching records' => $rows->count()],
        };

        return new ReportDataset($dataset->reportKey, $dataset->label, $dataset->columns, $rows, $summary, $dataset->meta, $dataset->notice);
    }
}
