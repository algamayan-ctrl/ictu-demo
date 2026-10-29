@php
    $saoUnavailableCounts = collect([
        ['label' => 'In Laundry', 'quantity' => $laundry],
        ['label' => 'Incident Hold', 'quantity' => $incident],
        ['label' => 'Under Maintenance', 'quantity' => $conditionHold],
        ['label' => $item->active ? 'Other Condition Hold' : 'Archived Stock', 'quantity' => $masterUnavailable],
    ])->filter(fn ($row) => $row['quantity'] > 0);
    $saoConditionCounts = collect([
        ['label' => 'Good / Serviceable', 'quantity' => $recordedGood],
        ['label' => 'Damaged / Under Repair', 'quantity' => $damagedMaintenance],
        ['label' => 'Lost', 'quantity' => $lost],
        ['label' => 'Stolen', 'quantity' => $stolen],
        ['label' => 'Destroyed', 'quantity' => $destroyed],
        ['label' => 'Condemned', 'quantity' => $condemned],
    ])->filter(fn ($row) => $row['quantity'] > 0);
    $saoHasStockIssue = $unavailable > 0 || $knownNonGood > 0;
    $saoConditionLabel = $knownNonGood > 0
        ? 'Condition findings recorded'
        : ($item->condition_code === 'SERVICEABLE' ? 'Good / Serviceable' : str($item->condition_code)->replace('_', ' ')->title()->toString());
@endphp

<article class="card sao-item-details">
    <h2>Item Details</h2>
    @if(filled($item->specification))
        <dl class="sao-inventory-facts sao-item-description">
            <div class="sao-inventory-description"><dt>Description</dt><dd>{{ $item->specification }}</dd></div>
        </dl>
    @endif
    <dl class="sao-inventory-facts sao-item-facts-row">
        <div><dt>Borrowing Eligibility</dt><dd>{{ $item->borrowable ? 'Borrowable' : 'Not borrowable' }}</dd></div>
        <div><dt>Use Location</dt><dd>{{ $item->off_campus_allowed ? 'Off-campus allowed' : 'On-campus only' }}</dd></div>
    </dl>
    <dl class="sao-inventory-facts sao-item-facts-row">
        <div><dt>Laundry After Use</dt><dd>{{ $item->laundry_required ? 'Required' : 'Not required' }}</dd></div>
        <div><dt>Condition</dt><dd>{{ $saoConditionLabel ?: 'Not recorded' }}</dd></div>
    </dl>
    <details class="sao-record-dates">
        <summary>Record Dates</summary>
        <dl class="sao-inventory-facts">
            <div><dt>Added</dt><dd>{{ $item->created_at?->format('d M Y, g:i A') ?: 'Not recorded' }}</dd></div>
            <div><dt>Last Updated</dt><dd>{{ $item->updated_at?->format('d M Y, g:i A') ?: 'Not recorded' }}</dd></div>
        </dl>
    </details>
</article>

<details class="card sao-stock-details" @if($saoHasStockIssue) open @endif>
    <summary>Stock Details <span>{{ $saoHasStockIssue ? 'Stock findings recorded' : 'Availability and condition breakdown' }}</span></summary>
    <div class="sao-stock-detail-grid">
        <section aria-label="Unavailable stock">
            <h3>Unavailable Stock</h3>
            @forelse($saoUnavailableCounts as $row)
                <div class="sao-stock-detail-row"><span>{{ $row['label'] }}</span><strong>{{ $row['quantity'] + 0 }}</strong></div>
            @empty
                <p class="meta">No unavailable stock.</p>
            @endforelse
        </section>
        <section aria-label="Recorded physical condition">
            <h3>Physical Condition</h3>
            @foreach($saoConditionCounts as $row)
                <div class="sao-stock-detail-row"><span>{{ $row['label'] }}</span><strong>{{ $row['quantity'] + 0 }}</strong></div>
            @endforeach
            @if($saoConditionCounts->isEmpty())
                <p class="meta">{{ $saoConditionLabel ?: 'Condition not recorded.' }}</p>
            @endif
        </section>
    </div>
</details>

@if($currentSources->isNotEmpty())
    <details class="card sao-current-records" @if($saoHasStockIssue || $currentSourceGroups->has('DISCREPANCY')) open @endif>
        <summary>Current Records <span>{{ $currentSources->count() }} {{ \Illuminate\Support\Str::plural('record', $currentSources->count()) }}</span></summary>
        <div class="sao-source-groups">
            @foreach($currentSourceOrder as $groupKey => $groupLabel)
                @php($groupRows = $currentSourceGroups->get($groupKey, collect()))
                @continue($groupRows->isEmpty())
                <section aria-label="{{ $groupLabel }} source records">
                    <h3>{{ $groupLabel }}</h3>
                    @foreach($groupRows as $record)
                        <div class="sao-source-record">
                            <div>
                                <strong>{{ $record['reference'] }}</strong>
                                <p>{{ $record['primary'] }}</p>
                                @if(filled($record['secondary'] ?? null))<small>{{ $record['secondary'] }}</small>@endif
                            </div>
                            <div class="sao-source-quantity"><strong>{{ (float) $record['quantity'] + 0 }} {{ \Illuminate\Support\Str::plural($item->unit->unit_name, (int) $record['quantity']) }}</strong><small>{{ $record['status'] }}</small></div>
                            @if(filled($record['url'] ?? null))
                                <a class="button secondary small ui-pressable" href="{{ $record['url'] }}">{{ $record['action_label'] ?? 'View Record' }}</a>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    </details>
@endif
