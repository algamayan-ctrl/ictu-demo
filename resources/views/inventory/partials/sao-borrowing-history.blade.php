<article class="card sao-borrowing-history" id="borrowing-history">
    <header class="sao-history-heading"><h2>Borrowing History</h2></header>
    <form method="GET" action="{{ route('inventory.show', $item) }}" id="sao-borrowing-history-filters">
        <input type="hidden" name="tab" value="borrowing-history">
        <div class="sao-history-filters">
            <label class="sao-history-search">Search<input type="search" name="history_search" aria-label="Search borrowing history" value="{{ $historySearch }}" maxlength="120" placeholder="Search borrower or reference…"></label>
            <label>From<input type="date" name="history_from" aria-label="From" value="{{ $historyFrom?->toDateString() }}"></label>
            <label>To<input type="date" name="history_to" aria-label="To" value="{{ $historyTo?->toDateString() }}"></label>
            <label>Status<select name="history_status" aria-label="Status">
                <option value="ALL" @selected($historyStatus === 'ALL')>All Statuses</option>
                <option value="ON_CUSTODY" @selected($historyStatus === 'ON_CUSTODY')>On Custody</option>
                <option value="OVERDUE" @selected($historyStatus === 'OVERDUE')>Overdue</option>
                <option value="RETURNED_ON_TIME" @selected($historyStatus === 'RETURNED_ON_TIME')>Returned On Time</option>
                <option value="RETURNED_LATE" @selected($historyStatus === 'RETURNED_LATE')>Returned Late</option>
                @if($item->laundry_required)<option value="IN_LAUNDRY" @selected($historyStatus === 'IN_LAUNDRY')>In Laundry</option>@endif
            </select></label>
        </div>
        @error('history_from')<p class="field-error sao-history-error">{{ $message }}</p>@enderror
        @error('history_to')<p class="field-error sao-history-error">{{ $message }}</p>@enderror
        <div id="sao-borrowing-history-results">
        <div class="table-wrap sao-history-table-wrap">
            <table class="sao-history-table">
                <colgroup><col style="width:22%"><col style="width:9%"><col style="width:9%"><col style="width:10%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:14%"><col style="width:12%"></colgroup>
                <thead><tr><th scope="col">Borrower</th><th scope="col">Released</th><th scope="col">Due Date</th><th scope="col">Return Date</th><th scope="col">Issued</th><th scope="col">Returned</th><th scope="col">To Return</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead>
                <tbody>
                    @forelse($history['page'] as $row)
                        <tr data-borrowing-history-row>
                            <td><strong>{{ $row['borrower']?->full_name ?: 'Not recorded' }}</strong>@if(filled($row['office']))<small>{{ $row['office'] }}</small>@endif</td>
                            <td>{{ $row['released_at']?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $row['expected_return_date']?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $row['actual_return_at']?->format('d M Y') ?: '—' }}</td>
                            <td class="sao-history-quantity">{{ $row['issued_quantity'] + 0 }}</td>
                            <td class="sao-history-quantity">{{ $row['returned_quantity'] + 0 }}</td>
                            <td class="sao-history-quantity">{{ $row['outstanding_quantity'] + 0 }}</td>
                            <td class="sao-history-status">
                                @if($row['item_status'] === 'RETURNED_ON_TIME')
                                    <x-status-badge status="CLOSED" label="Returned On Time" />
                                @elseif($row['item_status'] === 'RETURNED_LATE')
                                    <x-status-badge status="OVERDUE" label="Returned Late" />
                                @elseif($row['item_status'] === 'OVERDUE')
                                    <x-status-badge status="OVERDUE" label="Overdue" />
                                @elseif($row['item_status'] === 'IN_LAUNDRY')
                                    <x-status-badge status="PENDING" label="In Laundry" />
                                @else
                                    <x-status-badge status="ACTIVE" label="On Custody" />
                                @endif
                                @if(filled($row['accountability_label'] ?? null))<small class="inventory-history-accountability {{ ($row['accountability_active'] ?? false) ? 'is-active' : 'is-resolved' }}">{{ $row['accountability_label'] }}</small>@endif
                            </td>
                            <td><a class="button secondary small sao-history-view" href="{{ route('custody.show', $row['custody']) }}" title="{{ $row['request_no'] }} · {{ $row['custody']->custody_no }}">View Details</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">No borrowing records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="sao-history-footer">
            <p class="meta" role="status">
                @if($history['page']->total())
                    Showing {{ $history['page']->firstItem() }}–{{ $history['page']->lastItem() }} of {{ $history['page']->total() }} {{ \Illuminate\Support\Str::plural('record', $history['page']->total()) }}
                @else
                    No matching records
                @endif
            </p>
            <div class="sao-history-pagination">
                <select name="history_per_page" aria-label="Borrowing records per page">@foreach([10, 30, 50, 100] as $size)<option value="{{ $size }}" @selected($history['per_page'] === $size)>{{ $size }} records per page</option>@endforeach</select>
                @if($history['page']->total())
                    <nav aria-label="Borrowing history pages">
                        @if($history['page']->onFirstPage())<span class="button secondary small" aria-disabled="true">Previous</span>@else<a class="button secondary small" href="{{ $history['page']->previousPageUrl() }}">Previous</a>@endif
                        <span class="meta">Page {{ $history['page']->currentPage() }} of {{ $history['page']->lastPage() }}</span>
                        @if($history['page']->hasMorePages())<a class="button secondary small" href="{{ $history['page']->nextPageUrl() }}">Next</a>@else<span class="button secondary small" aria-disabled="true">Next</span>@endif
                    </nav>
                @endif
            </div>
        </footer>
        </div>
    </form>
</article>
@include('inventory.partials.sao-history-interactions')
