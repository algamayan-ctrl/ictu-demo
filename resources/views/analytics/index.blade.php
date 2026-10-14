@extends('layouts.app', ['title' => 'Analytics'])

@section('content')

@include('analytics.partials.analytics-styles')
@include('analytics.partials.card-interactions')
@include('analytics.partials.chart-tooltip')

@php
 /* Filters carry across the sub-navigation so switching tab keeps context. */
 $carry = array_filter([
 'academic_period' => $periodSelection,
 'group' => $selectedDivision !== 'all' ? $selectedDivision : null,
 'unit' => $selectedUnit !== 'all' ? $selectedUnit : null,
 'borrower_type' => $selectedBorrowerType !== 'all' ? $selectedBorrowerType : null,
 ]);
@endphp

<section class="page-heading analytics-heading">
 <div>
 <p class="eyebrow">Borrowing insights</p>
 <h1>Analytics</h1>
 <p>Understand borrowing activity, equipment usage, inventory, and returns at a glance.</p>
 </div>
</section>

<section class="content-area">
 <div class="analytics-page">
{{--
 Filters. Every control below re-runs the calculations on the
 server; nothing here is cosmetic.
 --}}
 <form class="analytics-filters" method="get" aria-label="Analytics filters">
 <input type="hidden" name="section" value="{{ $section }}">

 <label for="analytics-period">
 <span class="analytics-filter-label">
 Reporting period
 </span>
 <select id="analytics-period" name="academic_period" onchange="this.form.submit()">
 <option value="week" @selected($periodSelection === 'week')>This week</option>
 <option value="month" @selected($periodSelection === 'month')>This month</option>
 <option value="semester" @selected($periodSelection === 'semester')>This semester</option>
 <option value="academic_year" @selected($periodSelection === 'academic_year')>This academic year</option>
 </select>
 </label>

 <label for="analytics-borrower-type">
 <span class="analytics-filter-label">
 Borrower Type
 </span>
 <select id="analytics-borrower-type" name="borrower_type" onchange="this.form.submit()">
 @foreach($borrowerTypes as $typeValue => $typeLabel)
 <option value="{{ $typeValue }}" @selected($selectedBorrowerType === $typeValue)>{{ $typeLabel }}</option>
 @endforeach
 </select>
 </label>

 {{-- External Borrowers have no CSPC Division or Office: both read Not applicable. --}}
 @php($externalOnly = $selectedBorrowerType === 'EXTERNAL')

 <label for="analytics-group">
 <span class="analytics-filter-label">
 Division
 </span>
 <select id="analytics-group" name="group" onchange="this.form.submit()" @disabled($externalOnly)>
 @if($externalOnly)
 <option value="all" selected>Not applicable</option>
 @else
 <option value="all" @selected($selectedDivision === 'all')>All divisions</option>
 @foreach($divisions as $code => $label)
 <option value="{{ $code }}" @selected($selectedDivision === $code)>{{ $label }}</option>
 @endforeach
 @endif
 </select>
 </label>

 <label for="analytics-unit">
 <span class="analytics-filter-label">
 Office / College / Unit
 </span>
 <select id="analytics-unit" name="unit" onchange="this.form.submit()" @disabled($externalOnly)>
 @if($externalOnly)
 <option value="all" selected>Not applicable</option>
 @else
 <option value="all" @selected($selectedUnit === 'all')>All units</option>
 @foreach($selectableUnits as $unitName)
 <option value="{{ $unitName }}" @selected($selectedUnit === $unitName)>{{ $unitName }}</option>
 @endforeach
 @endif
 </select>
 </label>

 <p class="analytics-period-note">
 Showing {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}.
 @if($selectedAcademicPeriod)
 Academic period: {{ $selectedAcademicPeriod->term_label ?? $selectedAcademicPeriod->academic_year }}.
 @endif
 </p>


 </form>

 {{--
 A month of data labelled as a semester would be a false reading,
 not a small one, so the fallback is stated rather than hidden.
 --}}
 @if($academicPeriodMissing)
 <p class="analytics-notice" role="status">
 <x-icon name="warning" size="17" />
 <span>
 No academic period is configured, so semester and academic-year analytics cannot be
 calculated yet. The figures below cover
 {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }} instead.
 Configure academic periods in Operational Configuration to enable them.
 </span>
 </p>
 @endif


 {{-- Each section places the shared detail after its complete KPI group. --}}
 @include('analytics.partials.'.$sectionPartial)
 </div>
</section>
@endsection
