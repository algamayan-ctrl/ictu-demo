@extends('layouts.app', ['title' => 'Laundry Operations'])
@section('content')
@php
    $formArchived = $job->hasVerifiedAccomplishedForm();
    $isCompleted = $job->status === 'LAUNDRY_COMPLETED';
    $serviceableQuantity = (int) $job->lines->sum('completed_quantity');
    $recordStatus = match (true) {
        $isCompleted => 'Recorded',
        $job->status === 'TURNED_OVER_TO_LAUNDRY' => 'Updating Record',
        $formArchived => 'For Return Recording',
        default => 'Awaiting Laundry Form',
    };

    $expectedReturn = $job->custody?->original_due_at;
    $isLateReturn = $job->worker_received_at && $expectedReturn
        ? $job->worker_received_at->copy()->startOfDay()->gt($expectedReturn->copy()->startOfDay())
        : false;
    $adverseConditions = $job->lines
        ->flatMap(fn ($line) => $line->custodyLine?->returnLines ?? collect())
        ->map(fn ($returnLine) => strtoupper((string) $returnLine->condition_code))
        ->filter(fn ($condition) => $condition !== '' && $condition !== 'FINE')
        ->unique()
        ->values();
    $hasAdverseFinding = $adverseConditions->isNotEmpty();
    $adverseReason = $adverseConditions
        ->map(fn ($condition) => match ($condition) {
            'DAMAGED' => 'Damaged Item',
            'DESTROYED' => 'Destroyed Item',
            'MISSING' => 'Missing Item',
            'LOST' => 'Lost Item',
            'STOLEN' => 'Stolen Item',
            default => ucwords(strtolower(str_replace('_', ' ', $condition))),
        })
        ->implode(' + ');
    $accountabilityReason = collect([
        $isLateReturn ? 'Late Return' : null,
        $hasAdverseFinding ? $adverseReason : null,
    ])->filter()->implode(' + ') ?: null;
@endphp

<section class="page-heading">
    <div>
        <h1>Laundry Record</h1>
        <p>{{ $job->custody->borrower->full_name }} · Request {{ $job->custody->request->request_no }}</p>
    </div>
    <a class="button secondary ui-pressable" href="{{ route('laundry.index') }}{{ $isCompleted ? '#completed-laundry-cases' : '' }}"><span>Back to Laundry</span></a>
</section>

<div class="content-area laundry-detail">
    <article class="card laundry-record-card">
        <div class="card-header laundry-record-header">
            <h2>Return Details</h2>
            <x-status-badge :status="$isCompleted ? 'CLOSED' : $job->status" :label="$recordStatus" />
        </div>

        <dl class="laundry-record-facts">
            <div><dt>Custody No.</dt><dd>{{ $job->custody->custody_no }}</dd></div>
            <div><dt>Received by Laundry</dt><dd>{{ $job->worker_received_at?->format('d M Y') ?: 'Not yet recorded' }}</dd></div>
            @if($recordedReturn || $isCompleted)
                <div><dt>Recorded by</dt><dd>{{ $recordedReturn?->receivedBy?->full_name ?: 'Not recorded' }}</dd></div>
                <div><dt>Recorded on</dt><dd>{{ $recordedReturn?->created_at?->format('d M Y, g:i A') ?: 'Not recorded' }}</dd></div>
            @endif
            @if($isCompleted)<div><dt>Returned in Good Condition</dt><dd>{{ $serviceableQuantity }}</dd></div>@endif
        </dl>

        <section class="laundry-record-items" aria-labelledby="laundry-record-items-title">
            <h3 id="laundry-record-items-title">Linen Items</h3>
            <div class="table-wrap laundry-record-table-wrap">
                <table class="laundry-record-table">
                    <colgroup><col style="width:40%"><col style="width:15%"><col style="width:15%"><col style="width:30%"></colgroup>
                    <thead><tr><th scope="col">Item</th><th scope="col" class="is-numeric">Quantity Issued</th><th scope="col" class="is-numeric">Quantity Returned</th><th scope="col">Condition</th></tr></thead>
                    <tbody>
                        @forelse($job->lines as $line)
                            @php
                                $requestItem = $line->custodyLine?->requestItem;
                                $returnFindings = ($line->custodyLine?->returnLines ?? collect())
                                    ->filter(fn ($finding) => (int) $finding->quantity_received > 0);
                                $returnedQuantity = $returnFindings->isEmpty() ? null : $returnFindings
                                    ->reject(fn ($finding) => in_array(strtoupper((string) $finding->condition_code), ['MISSING', 'LOST', 'STOLEN', 'UNRETURNED', 'NOT_RETURNED'], true))
                                    ->sum('quantity_received');
                                $conditionSummary = $returnFindings->groupBy(fn ($finding) => strtoupper((string) $finding->condition_code))
                                    ->map(function ($findings, $condition) {
                                        $label = match ($condition) {
                                            'FINE', 'GOOD', 'SERVICEABLE' => 'Good',
                                            'DAMAGED' => 'Damaged',
                                            'DESTROYED' => 'Destroyed',
                                            'MISSING', 'UNRETURNED', 'NOT_RETURNED' => 'Not Returned',
                                            'LOST' => 'Lost',
                                            'STOLEN' => 'Stolen',
                                            default => ucwords(strtolower(str_replace('_', ' ', $condition))),
                                        };
                                        return $label.': '.(int) $findings->sum('quantity_received');
                                    });
                            @endphp
                            <tr data-laundry-item-row>
                                <td><strong>{{ $requestItem?->description_snapshot ?: 'Item not recorded' }}</strong><small>{{ $requestItem?->unit_snapshot ?: $requestItem?->inventoryItem?->unit?->unit_name }}</small></td>
                                <td class="is-numeric">{{ $line->issued_quantity }}</td>
                                <td class="is-numeric">{{ $returnedQuantity === null ? '—' : $returnedQuantity }}</td>
                                <td>@forelse($conditionSummary as $finding)<span class="laundry-condition-finding">{{ $finding }}</span>@empty<span class="meta">Not yet recorded</span>@endforelse</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="meta">No linen items recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="laundry-record-form">
            <div class="laundry-form-label">
                <x-icon name="requests" size="24" />
                <div>
                    <h3>{{ $formArchived ? 'Accomplished Laundry Form' : 'Laundry Form' }}</h3>
                    @unless($formArchived)<p>Use the accomplished form to record the return and the date received by Laundry.</p>@endunless
                </div>
            </div>
            <div class="inline-actions laundry-record-actions">
                @if($job->latestEvidence?->file)
                    <a class="button secondary small ui-pressable" href="{{ route('files.preview', $job->latestEvidence->file) }}">View Form</a>
                @elseif($job->document)
                    <a class="button secondary small ui-pressable" href="{{ route('documents.preview', $job->document) }}">View Form</a>
                @else
                    <span class="meta">No form on file</span>
                @endif
                @if($job->status === 'FOR_LAUNDRY')
                    <a class="button primary small ui-pressable" href="{{ route('custody.return.show', $job->custody) }}{{ $formArchived ? '#return-primary' : '#return-documents' }}">{{ $formArchived ? 'Record Return' : 'Record Laundry Form' }}</a>
                @endif
            </div>
        </div>
        @if($job->status === 'TURNED_OVER_TO_LAUNDRY')
            <p class="meta laundry-record-note">The return has been recorded. Availability is being updated automatically; no further action is needed.</p>
        @endif
        @if($isCompleted && $accountabilityReason)
            <div class="laundry-record-findings">
                <strong>Return Findings: {{ $accountabilityReason }}</strong>
                @if($isLateReturn && $expectedReturn && $job->worker_received_at)
                    <p class="meta">Expected return: {{ $expectedReturn->format('d M Y') }} · Actual linen return: {{ $job->worker_received_at->format('d M Y') }}</p>
                @endif
                <a href="{{ route('requests.show', $job->custody->request) }}">View Transaction</a>
            </div>
        @endif
    </article>
</div>
@include('laundry.partials.detail-styles')
@endsection
