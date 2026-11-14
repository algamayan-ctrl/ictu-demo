@php
    $outstanding = $custody->lines->sum(
        fn ($line) => max(
            0,
            (float) $line->actual_released_quantity - (float) $line->returned_quantity
        )
    );

    $version = $custody->request?->currentVersion;
    $scheduleDate = $version?->schedule_date ?: $version?->needed_from;
    $returnDate = $version?->return_date ?: $version?->return_due_at ?: $custody->due_at;

    // Shared with the server-side status filter so the badge and filter agree.
    // Gate-Pass-only processing shows as "Gate Pass Processing", never as
    // "Accountability Pending".
    $borrowerStatus = $custody->borrowerListStatus();
    $operationalLabel = $borrowerStatus['label'];
    $operationalStatusKey = $borrowerStatus['key'];
    $workflowGroup = $borrowerStatus['group'];
    $isCompleted = $workflowGroup === 'completed';
    $isCancelled = $workflowGroup === 'cancelled';
@endphp

<a
    class="operational-record ui-pressable"
    href="{{ route('custody.show', $custody) }}"
    data-borrowings-record
    data-borrowings-group="{{ $workflowGroup }}"
    data-borrowings-status="{{ $operationalStatusKey }}"
>
    <span class="operational-record-primary">
        <strong>{{ $custody->custody_no }}</strong>
        <span>Request {{ $custody->request?->request_no }}</span>
        <small>
            Use {{ optional($scheduleDate)->format('d M Y') }}
            · Return by {{ optional($returnDate)->format('d M Y') }}
        </small>
    </span>

    <span class="operational-record-facts">
        <span><small>Pickup schedule</small><strong>{{ optional($custody->scheduled_release_at)->format('d M Y, g:i A') ?: 'Not scheduled' }}</strong></span>
        <span><small>Items released</small><strong>{{ optional($custody->released_at)->format('d M Y, g:i A') ?: 'Not released' }}</strong></span>

        @if($isCancelled)
            <span>
                <small>Cancelled</small>
                <strong>{{ optional($custody->closed_at)->format('d M Y, g:i A') ?: 'Cancelled' }}</strong>
            </span>
        @elseif($isCompleted)
            <span>
                <small>{{ $operationalLabel }}</small>
                <strong>{{ optional($custody->closed_at)->format('d M Y, g:i A') ?: $operationalLabel }}</strong>
            </span>
        @else
            <span>
                <small>{{ $custody->status === 'OVERDUE' ? 'Items overdue' : 'Items on custody' }}</small>
                <strong>{{ $outstanding + 0 }} {{ $outstanding == 1 ? 'item' : 'items' }}</strong>
            </span>
        @endif
    </span>

    <span class="operational-record-action">
        <x-status-badge
            :status="$operationalStatusKey"
            :label="$operationalLabel"
        />
        <strong>View details</strong>
    </span>
</a>
