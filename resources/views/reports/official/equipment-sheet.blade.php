@php
    $template = $report['template'];
    $config = $report['configuration'];
    $head = $config['header'] ?? [];
    $sign = $config['signatories'] ?? [];
    $formula = $config['formula'] ?? [];
    $columns = $report['columns'];
    $groups = [];
    foreach ($columns as $index => $column) {
        $group = $column['group'];
        if ($group && $index > 0 && $columns[$index - 1]['group'] === $group) {
            continue;
        }
        $span = 1;
        if ($group) {
            for ($i = $index + 1; $i < count($columns) && $columns[$i]['group'] === $group; $i++) {
                $span++;
            }
        }
        $groups[] = ['label' => $group ?: $column['label'], 'span' => $span, 'group' => $group, 'width' => array_sum(array_column(array_slice($columns, $index, $span), 'width'))];
    }
    $hasGrouped = collect($columns)->contains(fn($c) => filled($c['group']));
@endphp
<main class="sheet">
    <header class="institutional-head">
        <div class="seal">@if(($head['show_logo'] ?? true) && $sealSrc)<img src="{{ $sealSrc }}" alt="CSPC seal">@endif</div>
        <div class="institution">
            {{ $head['republic'] ?? 'Republic of the Philippines' }}<br>
            <strong>{{ $head['institution'] ?? 'CAMARINES SUR POLYTECHNIC COLLEGES' }}</strong><br>
            {{ $head['location'] ?? 'Nabua, Camarines Sur' }}
        </div>
        <div class="code">{{ $template->form_code }}</div>
    </header>
    <h2 class="form-title">{{ $report['title'] }}</h2>
    <table class="form-table">
        <colgroup>@foreach($columns as $col)<col style="width:{{ $col['width'] }}%">@endforeach</colgroup>
        <thead>
        @if($hasGrouped)
            <tr>
                @foreach($groups as $group)
                    <th style="width:{{ $group['width'] }}%" @if($group['group']) colspan="{{ $group['span'] }}" @else rowspan="2" @endif>{{ $group['label'] }}</th>
                @endforeach
            </tr>
            <tr>@foreach($columns as $col)@if($col['group'])<th style="width:{{ $col['width'] }}%">{{ $col['label'] }}</th>@endif @endforeach</tr>
        @else
            <tr>@foreach($columns as $col)<th style="width:{{ $col['width'] }}%">{{ $col['label'] }}</th>@endforeach</tr>
        @endif
        </thead>
        <tbody>
        @forelse($report['rows'] as $row)
            <tr>@foreach($columns as $col)<td>{{ $row[$col['key']] ?? '—' }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ max(1,count($columns)) }}" style="text-align:center">No submitted equipment requests recorded in the selected period.</td></tr>
        @endforelse
        </tbody>
    </table>
    @if($formula['enabled'] ?? true)
        <p class="formula"><strong>{{ $formula['label'] ?? 'Formula' }}:</strong>
            {{ $formula['numerator_label'] ?? 'Number of requests responded on time' }}
            / {{ $formula['denominator_label'] ?? 'Total number of requests' }} × 100%<br>
            Total requests: <strong>{{ $report['total'] }}</strong> · On-time result: <strong>Not evaluated</strong>
            <span class="muted">(The approved institutional on-time response rule must be defined before a percentage or MET status can be shown.)</span>
        </p>
    @endif
    {{-- Signatures and the form footer stay together so the footer never lands alone on a last page. --}}
    <div class="closing">
    <section class="signatures">
        @foreach(['prepared_by', 'noted_by'] as $key)
            <div>
                <div class="sign-label">{{ $sign[$key.'_label'] ?? ($key === 'prepared_by' ? 'Prepared by' : 'Noted by') }}:</div>
                <div class="ink-line" aria-hidden="true"></div>
                <div class="sign-name">{{ $sign[$key.'_name'] ?? '' }}</div>
                <div class="designation">{{ $sign[$key.'_designation'] ?? '' }}</div>
                @if($sign['show_date'] ?? true)<div class="sign-date">Date: __________________</div>@endif
            </div>
        @endforeach
    </section>
    <footer class="footer">
        <span>Effectivity Date: {{ $template->effectivity_date?->format('F Y') ?? '—' }}</span>
        <span>{{ $template->revision_label }}</span>
        <span>Official form · physical signatures required</span>
    </footer>
    </div>
</main>
