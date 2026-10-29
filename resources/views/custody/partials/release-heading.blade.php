@php
    $releaseNeedsSchedule = ! $hasPickupSchedule || $pickupWindowPassed;
    $releaseSummaryTone = $pickupWindowPassed || $custody->pickup_expired_at
        ? 'warning'
        : ($preparationComplete && $pickupWindowOpen ? 'success' : 'info');
    $operationalLabel = \App\Support\ActionOfficerStatusPresentation::releaseStatus($custody->workflowStatus())['label'];
@endphp

<section class="page-heading release-page-heading">
    <div>
        <p class="release-eyebrow">Release Record</p>
        <h1>{{ $custody->custody_no }}</h1>
        <p>Request {{ $custody->request?->request_no }} · {{ $custody->borrower?->full_name }}</p>
    </div>
    <aside class="release-status-summary" aria-label="Release status and schedule">
        <div class="release-status-heading release-status-heading--{{ $releaseSummaryTone }}">
            <x-icon :name="$releaseSummaryTone === 'success' ? 'success' : ($releaseSummaryTone === 'warning' ? 'warning' : 'information')" size="20" />
            <div><strong>{{ $operationalLabel }}</strong></div>
        </div>
        <div class="release-status-dates">
            <div>
                <x-icon name="calendar" size="20" />
                <div><strong>Pickup</strong><span>{{ $custody->scheduled_release_at?->format('M j, Y') ?: 'Not scheduled' }}@if($custody->scheduled_release_at && $custody->pickup_expires_at) · {{ $custody->scheduled_release_at->format('g:i A') }} – {{ $custody->pickup_expires_at->format('g:i A') }}@endif</span></div>
            </div>
        </div>
    </aside>
</section>
