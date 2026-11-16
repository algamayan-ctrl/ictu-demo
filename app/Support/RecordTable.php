<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

// This is the pagination helper for complete, filtered record collections.
class RecordTable
{
    public static function paginate(Collection $rows, Request $request, string $prefix, int $perPage): LengthAwarePaginator
    {
        $pageName = $prefix.'_page';
        $page = min(max(1, (int) $request->input($pageName, 1)), max(1, (int) ceil($rows->count() / $perPage)));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
            'pageName' => $pageName,
            'query' => $request->except($pageName),
        ]);
    }
}
