@php
    $custody = $gatePass->custody;
    $borrower = $custody?->borrower;
    $requestRecord = $custody?->request;
    $version = $requestRecord?->currentVersion;
    $destination = $gatePass->destination ?: ($version?->location ?: '—');
    $statusMeta = $gatePass->workflowStatus();
    $statusLabel = $statusMeta['label'];
    $statusTone = $statusMeta['tone'];
    $statusKey = $statusMeta['key'];
    $recordedAt = $gatePass->verified_at ?: $gatePass->uploaded_at;
    $search = implode(' ', [
        $requestRecord?->request_no,
        $custody?->custody_no,
        $borrower?->full_name,
        $borrower?->organizationalUnit?->unit_name,
        $destination,
        $gatePass->purpose,
        $statusLabel,
    ]);
@endphp
<tr data-gate-pass-record data-search="{{ $search }}" data-status="{{ $statusKey }}" data-date="{{ $gatePass->updated_at?->timestamp ?? 0 }}">
    <td><a class="gate-pass-request-link" href="{{ route('gate-passes.show', $gatePass) }}">{{ $requestRecord?->request_no ?: 'Gate Pass #'.$gatePass->id }}</a></td>
    <td><strong>{{ $borrower?->full_name ?: '—' }}</strong><small>{{ $borrower?->organizationalUnit?->unit_name ?: '—' }}</small></td>
    <td class="gate-pass-destination"><strong>{{ $gatePass->purpose ?: '—' }}</strong><small>{{ $destination }}</small></td>
    <td class="gate-pass-release-date">
        @if($recordedAt)
            <time datetime="{{ $recordedAt->toIso8601String() }}">{{ $recordedAt->format('M d, Y') }}</time>
        @else
            <span title="Recording date unavailable">—</span>
        @endif
    </td>
    <td><x-status-badge :status="$statusKey" :label="$statusLabel" title="{{ $statusLabel }}" /></td>
    <td>
        <div class="gate-pass-row-actions">
            <a class="gate-pass-icon-action" href="{{ route('gate-passes.show', $gatePass) }}" aria-label="View Record" data-tooltip="View Record">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.06 12a10.94 10.94 0 0 1 19.88 0 10.94 10.94 0 0 1-19.88 0Z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            @if($gatePass->accomplishedFile)
                <a class="gate-pass-icon-action" href="{{ route('files.preview', $gatePass->accomplishedFile, false) }}" aria-label="View Accomplished Gate Pass" data-tooltip="View Upload">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>
                </a>
            @endif
        </div>
    </td>
</tr>
