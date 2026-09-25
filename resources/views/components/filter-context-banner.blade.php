@props(['label', 'count', 'clearUrl'])

<div class="filter-context-banner">
    <span><strong>Filtered by:</strong> {{ $label }} <span class="filter-context-count">&middot; {{ $count }} {{ Str::plural('record', (int) $count) }}</span></span>
    <a class="filter-context-clear" href="{{ $clearUrl }}">Show all &rarr;</a>
</div>
