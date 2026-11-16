@php($prefix = $completed ? 'completed' : 'active')
<form method="get" action="{{ $filterAction ?? route('laundry.index') }}{{ $completed && !isset($filterAction) ? '#completed-laundry-cases' : '' }}" class="card record-table-toolbar">
    @foreach(request()->except([$prefix.'_q', $prefix.'_status', $prefix.'_outcome', $prefix.'_sort', $prefix.'_per_page', $prefix.'_page', 'page']) as $key => $value)
        @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <label class="record-table-search">Search<input type="search" name="{{ $prefix }}_q" maxlength="200" value="{{ request($prefix.'_q') }}" placeholder="Borrower, request, custody, event or item…"></label>
    @if($completed)
        <label>Return Result<select name="completed_outcome">
            @foreach(['ALL' => 'All results', 'available' => 'Available for Borrowing', 'accountability' => 'With Return Findings'] as $value => $label)
                <option value="{{ $value }}" @selected(request('completed_outcome', 'ALL') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
    @else
        <label>Status<select name="active_status">
            @foreach(['ALL' => 'All statuses', 'LAUNDRY_FORM_PENDING' => 'Awaiting Laundry Form', 'READY_FOR_SPMU_ENCODING' => 'For Return Recording', 'RECONCILIATION_PENDING' => 'Updating Record'] as $value => $label)
                <option value="{{ $value }}" @selected(request('active_status', 'ALL') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
    @endif
    <label>Sort<select name="{{ $prefix }}_sort"><option value="newest" @selected(request($prefix.'_sort', 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(request($prefix.'_sort') === 'oldest')>Oldest first</option></select></label>
    <x-record-page-size :name="$prefix.'_per_page'" :value="$records->perPage()" :sizes="[10, 20, 50, 100]" />
    <div class="record-table-actions"><button class="button primary" type="submit">Apply</button><a class="button secondary" href="{{ $filterAction ?? route('laundry.index') }}{{ $completed && !isset($filterAction) ? '#completed-laundry-cases' : '' }}">Clear</a></div>
</form>
