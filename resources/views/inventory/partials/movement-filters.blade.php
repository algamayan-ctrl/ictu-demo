<form method="get" action="{{ route('inventory.show', $item) }}#inventory-movement-details" class="record-table-toolbar">
    <input type="hidden" name="tab" value="stock-card">
    <label class="record-table-search">Search
        <input type="search" name="movement_q" value="{{ request('movement_q') }}" maxlength="200" placeholder="Reference, actor, reason or movement…">
    </label>
    <label>Transaction type
        <select name="movement_type">
            <option value="ALL">All transactions</option>
            @foreach($movementTypes as $type)<option value="{{ $type }}" @selected(request('movement_type') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>@endforeach
        </select>
    </label>
    <label>Date from<input type="date" name="movement_from" value="{{ request('movement_from') }}"></label>
    <label>Date to<input type="date" name="movement_to" value="{{ request('movement_to') }}"></label>
    <x-record-page-size name="movement_per_page" :value="$movementPage->perPage()" />
    <div class="record-table-actions">
        <button class="button primary" type="submit">Apply</button>
        <a class="button secondary" href="{{ route('inventory.show', ['inventory' => $item, 'tab' => 'stock-card']) }}#inventory-movement-details">Clear</a>
    </div>
</form>
@foreach(['movement_q', 'movement_type', 'movement_from', 'movement_to', 'movement_per_page'] as $field)
    @error($field)<p class="field-error">{{ $message }}</p>@enderror
@endforeach
