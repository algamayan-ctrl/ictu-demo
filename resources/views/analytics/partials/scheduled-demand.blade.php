@php
 $groups = $scheduled['divisions']['groups'];
 $columns = $scheduled['units']['columns'];
@endphp

<p class="analytics-period-note">
 Scheduled borrowing dates: {{ $scheduledFrom->format('d M Y') }} to {{ $scheduledTo->format('d M Y') }}.
 These are saved requests. Pending review does not mean approval or stock reservation.
</p>

<div class="analytics-stat-grid">
 @foreach(['requests' => 'Scheduled Demand', 'pending' => 'Pending Review', 'approved' => 'Approved Requests'] as $key => $label)
 <section class="analytics-card">
  <header class="analytics-card-head">
   <span class="analytics-card-mark" aria-hidden="true"><x-icon name="calendar-clock" size="15" /></span>
   <div><h2>{{ $label }}</h2><p>Requests in the scheduled window</p></div>
  </header>
  <div class="analytics-card-body"><strong class="analytics-scheduled-count">{{ number_format($scheduled[$key]) }}</strong></div>
 </section>
 @endforeach
</div>

@if($detail)
 @include('analytics.partials.detail-panel')
@endif

<div class="analytics-demand-main">
 <section class="analytics-card" data-card-detail="{{ App\Support\AnalyticsDetailLink::to('card', 'scheduled', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'scheduled.summary']) }}">
  <header class="analytics-card-head">
   <span class="analytics-card-mark" aria-hidden="true"><x-icon name="users" size="15" /></span>
   <div><h2>Recorded Affiliation</h2><p>Saved current request versions</p></div>
   <a class="analytics-card-open" href="{{ App\Support\AnalyticsDetailLink::to('card', 'scheduled', $periodSelection, $selectedDivision === 'all' ? null : $selectedDivision, $selectedUnit === 'all' ? null : $selectedUnit, ['for' => 'scheduled.summary']) }}" aria-label="View Scheduled Demand details"><x-icon name="chevron-right" size="15" /></a>
  </header>
  <div class="analytics-card-body">
   @forelse($groups as $group)
   <p><strong>{{ $group['label'] }}</strong> <span>{{ $group['count'] }} {{ $group['count'] === 1 ? 'request' : 'requests' }} ({{ $group['percentage'] }}%)</span></p>
   @empty
   <p class="analytics-blank">No requests are scheduled for this window.</p>
   @endforelse
  </div>
 </section>
 <section class="analytics-card">
  <header class="analytics-card-head"><span class="analytics-card-mark" aria-hidden="true"><x-icon name="building" size="15" /></span><div><h2>Employee Units</h2><p>Recorded CSPC affiliation only</p></div></header>
  <div class="analytics-card-body">
   @forelse($columns as $column)
   <h3>{{ $column['label'] }}</h3>
   @foreach($column['units'] as $row)<p>{{ $row['name'] }} <strong>{{ $row['count'] }}</strong></p>@endforeach
   @empty
   <p class="analytics-blank">No employee units recorded in this window.</p>
   @endforelse
  </div>
 </section>
 <section class="analytics-card">
  <header class="analytics-card-head"><span class="analytics-card-mark" aria-hidden="true"><x-icon name="building" size="15" /></span><div><h2>External Companies / Agencies</h2><p>Company saved on the request version</p></div></header>
  <div class="analytics-card-body">
   @forelse($scheduled['external']['companies'] as $company)
   <p>{{ $company['name'] }} <strong>{{ $company['count'] }}</strong></p>
   @empty
   <p class="analytics-blank">No external requests recorded in this window.</p>
   @endforelse
  </div>
 </section>
</div>
