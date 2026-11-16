@php($prefix = $completed ? 'completed' : 'active')
{{-- This is the complete-register Laundry filter form. --}}
<form method="get" action="{{ $filterAction ?? route('laundry.index') }}{{ $completed && !isset($filterAction) ? '#completed-laundry-cases' : '' }}" class="card record-table-toolbar {{ $toolbarClass ?? '' }}">
    <input type="hidden" name="laundry_tab" value="{{ $prefix }}">
    @foreach(request()->except([$prefix.'_q', $prefix.'_status', $prefix.'_outcome', $prefix.'_sort', $prefix.'_per_page', $prefix.'_page', 'page', 'per_page', 'laundry_tab']) as $key => $value)
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
    @if($showPageSize ?? true)
        <x-record-page-size :name="$prefix.'_per_page'" :value="$records->perPage()" :sizes="array_values(array_unique([7, 15, 30, 50, $records->perPage()]))" :entries-inside="true" />
    @else
        <input type="hidden" name="{{ $prefix }}_per_page" value="{{ $records->perPage() }}">
    @endif
    <div class="record-table-actions"><button class="button primary" type="submit">Apply</button><a class="button secondary" href="{{ route(isset($filterAction) ? 'laundry.completed' : 'laundry.index', array_merge(request()->except([$prefix.'_q', $prefix.'_status', $prefix.'_outcome', $prefix.'_sort', $prefix.'_per_page', $prefix.'_page', 'page', 'per_page', 'laundry_tab']), ['laundry_tab' => $prefix])) }}{{ $completed && !isset($filterAction) ? '#completed-laundry-cases' : '' }}">Clear</a></div>
</form>
