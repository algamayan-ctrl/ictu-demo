<form method="get" action="{{ route('policies.index') }}" class="record-table-toolbar">
    <input type="hidden" name="section" value="academic-periods">
    <label class="record-table-search">Search
        <input id="academic-periods-search" type="search" name="period_q" value="{{ request('period_q') }}" maxlength="200" placeholder="Academic year or term…">
    </label>
    <label>Status
        <select name="period_status">
            @foreach(['ALL' => 'All statuses', 'ACTIVE' => 'Current', 'UPCOMING' => 'Upcoming', 'COMPLETED' => 'Completed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('period_status', 'ALL') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Sort
        <select id="academic-periods-sort" name="period_sort">
            <option value="newest" @selected(request('period_sort', 'newest') === 'newest')>Newest first</option>
            <option value="oldest" @selected(request('period_sort') === 'oldest')>Oldest first</option>
        </select>
    </label>
    <x-record-page-size name="period_per_page" :value="$academicPeriods->perPage()" :entries-inside="true" />
    <div class="record-table-actions">
        <button class="button primary" type="submit">Apply</button>
        <a class="button secondary" href="{{ route('policies.index', ['section' => 'academic-periods']) }}">Clear</a>
    </div>
</form>
