@php
    $printQuery = array_filter(['stock_q' => $card['search'], 'stock_movement' => $card['movement']], fn ($value) => $value !== '');
@endphp
<article class="card sao-stock-card" id="stock-card">
    <div class="sao-stock-card-heading">
        <div><h2>Stock Card</h2></div>
        <a class="button secondary small" href="{{ route('inventory.stock-card.print', array_merge(['inventory' => $item->id], $printQuery)) }}" target="_blank" rel="noopener"><x-icon name="printer" size="17" /> Print Stock Card</a>
    </div>
    @if($card['warning'])<p class="sao-stock-card-warning" role="alert">{{ $card['warning'] }}</p>@endif
    <form method="GET" action="{{ route('inventory.show', $item) }}" id="sao-stock-card-filters">
        <input type="hidden" name="tab" value="stock-card">
        <div class="sao-stock-card-filters">
            <label>Search<input type="search" name="stock_q" value="{{ $card['search'] }}" placeholder="Search reference, office, or date…" maxlength="200"></label>
            <label>Filter<select name="stock_movement" aria-label="Filter"><option value="ALL" @selected($card['movement'] === 'ALL')>All Entries</option><option value="RECEIPT" @selected($card['movement'] === 'RECEIPT')>Received Items</option><option value="ISSUE" @selected($card['movement'] === 'ISSUE')>Issued Items</option></select></label>
        </div>
        <div class="table-wrap sao-stock-card-table-wrap">
            @include('inventory.partials.stock-card-identity')
            @include('inventory.partials.stock-card-table', ['rows' => $card['page']])
        </div>
        <div class="sao-stock-card-footer">
            <p class="meta" role="status">
                @if($card['page']->total())
                    Showing {{ $card['page']->firstItem() }}–{{ $card['page']->lastItem() }} of {{ $card['page']->total() }} entries
                @else
                    {{ $card['filtered'] ? 'No matching entries' : 'No recorded entries' }}
                @endif
            </p>
            <div class="sao-stock-card-pagination">
                <select name="stock_per_page" aria-label="Stock card entries per page">@foreach([10, 30, 50, 100] as $size)<option value="{{ $size }}" @selected($card['per_page'] === $size)>{{ $size }} entries per page</option>@endforeach</select>
                @if($card['page']->total())
                    <nav aria-label="Stock card pages">
                        @if($card['page']->onFirstPage())<span class="button secondary small" aria-disabled="true">Previous</span>@else<a class="button secondary small" href="{{ $card['page']->previousPageUrl() }}">Previous</a>@endif
                        <span class="meta">Page {{ $card['page']->currentPage() }} of {{ $card['page']->lastPage() }}</span>
                        @if($card['page']->hasMorePages())<a class="button secondary small" href="{{ $card['page']->nextPageUrl() }}">Next</a>@else<span class="button secondary small" aria-disabled="true">Next</span>@endif
                    </nav>
                @endif
            </div>
        </div>
    </form>
</article>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('sao-stock-card-filters');
    if (!form) return;
    let searchTimer;
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
        clearTimeout(searchTimer);
        form.requestSubmit();
    }));
    form.querySelector('input[type="search"]').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => form.requestSubmit(), 500);
    });
});
</script>
