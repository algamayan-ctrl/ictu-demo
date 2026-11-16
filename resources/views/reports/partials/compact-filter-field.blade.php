@php
    $current = $reportFilters->get($filterKey) ?? '';
    $options = $filterKey === 'borrower' ? [] : \App\Reports\ReportFilters::optionsFor($definition, $reportFilters->get('division'));
    if ($filterKey === 'accountability_status') $options = \App\Reports\AccountabilityStatusGroups::OPTIONS;
    if ($filterKey === 'request_status') $options = \App\Reports\RequestOverviewStatus::OPTIONS;
    if ($filterKey === 'availability_status') unset($options['ALLOCATED']);
    if ($current !== '' && !isset($options[$current]) && $filterKey !== 'borrower') {
        $legacyOptions = \App\Reports\ReportFilters::optionsFor($definition, $reportFilters->get('division'));
        if (isset($legacyOptions[$current])) $options[$current] = $legacyOptions[$current].' (historical filter)';
    }
    $searchable = in_array($filterKey, ['unit', 'equipment', 'borrower'], true);
    $choices = collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values();
    $selectedLabel = $options[$current] ?? '';
    if ($filterKey === 'borrower' && $current !== '') {
        $selectedUser = \App\Models\User::find($current);
        $selectedLabel = $selectedUser ? $selectedUser->full_name.' — '.$selectedUser->email : '';
        if ($selectedUser) $choices = collect([['value' => $current, 'label' => $selectedLabel]]);
    }
    $fieldId = 'report-filter-'.$filterKey;
    $placeholder = $filterKey === 'accountability_status' ? 'All Cases' : ($definition['placeholder'] ?? 'All');
@endphp
<div class="report-compact-field" data-report-filter-field="{{ $filterKey }}">
    <label for="{{ $fieldId }}">{{ $definition['label'] }}</label>
    @if($searchable)
        <div class="report-search-choice" data-report-picker="{{ $filterKey }}">
            <input type="hidden" name="{{ $filterKey }}" value="{{ $current }}" data-picker-value>
            <span class="report-picker-search-icon" aria-hidden="true"><x-icon name="search" size="16" /></span>
            <input id="{{ $fieldId }}" type="search" list="{{ $fieldId }}-choices" value="{{ $selectedLabel }}" maxlength="200" autocomplete="off"
                placeholder="{{ match($filterKey) {'unit' => 'Search office / college / unit…', 'equipment' => 'Search equipment…', default => 'Search borrower…'} }}" data-picker-input>
            <datalist id="{{ $fieldId }}-choices" data-picker-list></datalist>
            <script type="application/json" data-picker-choices>{!! json_encode($choices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            <small class="field-error" data-picker-error role="alert" hidden>Search is unavailable. Type again to retry.</small>
        </div>
    @else
        <select id="{{ $fieldId }}" name="{{ $filterKey }}">
            <option value="">{{ $placeholder }}</option>
            @foreach($options as $value => $label)<option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>@endforeach
        </select>
    @endif
</div>
