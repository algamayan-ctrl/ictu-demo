@include('requests.partials.my-requests-art')

<div class="mr-empty-copy">
    <strong>{{ $emptyTitle ?? 'No matching requests.' }}</strong>
    <span>{{ $emptyMessage ?? 'Try another search term or choose a different status filter.' }}</span>
</div>

<a class="button secondary ui-pressable" id="request-filter-reset" href="{{ $emptyActionUrl ?? route('requests.index') }}">
    {{ $emptyActionLabel ?? 'Clear filters' }}
</a>
