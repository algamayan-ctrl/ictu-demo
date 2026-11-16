@props(['records', 'label' => 'Record pages'])
<footer class="record-table-footer">
    <p class="meta" role="status">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records</p>
    <nav aria-label="{{ $label }}">
        @if($records->onFirstPage())
            <span class="button secondary small" aria-disabled="true">Previous</span>
        @else
            <a class="button secondary small" href="{{ $records->previousPageUrl() }}">Previous</a>
        @endif
        @php($nearby = collect([1, $records->lastPage()])->merge(range(max(1, $records->currentPage() - 1), min($records->lastPage(), $records->currentPage() + 1)))->unique()->sort()->values())
        @foreach($nearby as $number)
            @if($loop->index && $number > $nearby[$loop->index - 1] + 1)<span aria-hidden="true">…</span>@endif
            @if($number === $records->currentPage())
                <span class="button primary small" aria-current="page">{{ $number }}</span>
            @else
                <a class="button secondary small" href="{{ $records->url($number) }}" aria-label="Page {{ $number }}">{{ $number }}</a>
            @endif
        @endforeach
        @if($records->hasMorePages())
            <a class="button secondary small" href="{{ $records->nextPageUrl() }}">Next</a>
        @else
            <span class="button secondary small" aria-disabled="true">Next</span>
        @endif
    </nav>
</footer>
