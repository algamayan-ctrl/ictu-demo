@extends('layouts.app', ['title' => 'Notifications'])
@section('content')
<section class="page-heading"><div><p class="eyebrow">Notifications</p><h1>Notifications</h1><p>Review updates about your borrowing requests, pickup schedules, returns, and any action required from you.</p></div><form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="button secondary">Mark all as read</button></form></section>

<section class="content-area">
    <div class="notification-list borrower-notifications">
    @forelse($notifications as $notification)
        @php
            $eventCode = strtoupper((string) $notification->event->event_code);
            $storedMessage = (string) ($notification->event->payload_snapshot_json['message'] ?? $notification->provider_response ?? '');
            $isBorrowerNotification = auth()->user()?->access_classification?->value === 'BORROWER_ONLY';
            $pickupConfirmed = $eventCode === 'PICKUP_SCHEDULED'
                && str_contains(strtolower($storedMessage), 'arrival for pickup');
            $eventLabel = [
                'REQUEST_APPROVED' => 'Borrowing Request Approved',
                'REQUEST_RETURNED_FOR_REVISION' => 'Request Revision Required',
                'REQUEST_REJECTED' => 'Borrowing Request Not Approved',
                'REQUEST_CANCELLED' => 'Borrowing Request Cancelled',
                'PICKUP_SCHEDULED' => 'Pickup Schedule Updated',
                'PICKUP_EXPIRED' => 'Pickup Schedule Missed',
                'PICKUP_RESCHEDULE_REQUESTED' => 'Pickup Reschedule Requested',
                'ITEMS_RELEASED' => 'Borrowed Items Released',
                'RETURN_DUE_TOMORROW' => 'Return Due Tomorrow',
                'RETURN_DUE_TODAY' => 'Return Due Today',
                'RETURN_SCHEDULE_ADJUSTED' => 'Return Schedule Updated',
                'RETURN_INSPECTED' => 'Return Inspected',
                'TRANSACTION_CLOSED' => 'Borrowing Transaction Completed',
                'BORROWING_OVERDUE' => 'Return Overdue',
                'LATE_RETURN_NOTICE_ISSUED' => 'Late Return Notice',
                'LATE_RETURN_BILLING_STATEMENT_ISSUED' => 'Late Return Billing Statement',
                'ACCOUNTABILITY_OPENED' => 'Property Accountability Case Opened',
                'PROPERTY_ACCOUNTABILITY_HEAD_DECISION_RECORDED' => 'Property Accountability Decision',
                'PROPERTY_ACCOUNTABILITY_CASE_RESOLVED' => 'Property Accountability Resolved',
                'LATE_RETURN_RESOLVED_WITHOUT_CHARGE' => 'Late Return Resolved — No Charge',
                'PAYMENT_VERIFIED' => 'Payment Confirmed',
                'RECEIPT_VERIFIED' => 'Payment Confirmed',
                'EVIDENCE_VERIFIED' => 'Supporting Document Verified',
                'EVIDENCE_REJECTED' => 'Supporting Document Needs Replacement',
                'ADMINISTRATIVE_SANCTION_RECORDED' => 'Administrative Sanction Notice',
                'EARLY_RETURN_REQUESTED' => 'Early Return Request',
            ][$eventCode] ?? str($eventCode)->replace('_',' ')->lower()->title();
            if ($isBorrowerNotification && $pickupConfirmed) {
                $eventLabel = 'Pickup Confirmed';
            }

            $message = $isBorrowerNotification
                ? match ($eventCode) {
                    'REQUEST_CANCELLED' => 'Your borrowing request has been cancelled. No further action is required.',
                    'RETURN_DUE_TODAY' => 'Your borrowed items are due for return today. Please return them to SPMU during the designated return hours.',
                    'RETURN_DUE_TOMORROW' => 'Your borrowed items are due for return tomorrow. Please return them to SPMU on or before the return date in your borrowing record.',
                    'PICKUP_EXPIRED' => 'Your pickup schedule was not completed. SPMU will review your request and notify you of the next step. No action is required from you at this time.',
                    'PICKUP_SCHEDULED' => $pickupConfirmed
                        ? 'Your arrival for pickup has been confirmed. SPMU will notify you once the approved items are ready for release.'
                        : 'Your pickup schedule has been updated. Please check your borrowing record for the schedule and required documents.',
                    default => $storedMessage !== '' ? $storedMessage : 'An update has been recorded.',
                }
                : ($storedMessage !== '' ? $storedMessage : 'An update has been recorded.');
            $cancellationReason = $eventCode === 'REQUEST_CANCELLED'
                ? trim((string) ($cancellationReasons[(int) ($notification->event->source_id ?? 0)] ?? ''))
                : '';

            if (auth()->user()?->access_classification?->value === 'SPMU_OFFICER' && $eventCode === 'PICKUP_SCHEDULED') {
                $message = str_replace(
                    'Keep the same approved request/reservation and prepare for the new window.',
                    'Keep the same approved request and allocation, and prepare for the new window.',
                    $message
                );
            }
            $target = $targets[$notification->id] ?? null;
        @endphp
        <article class="notification-item {{ $notification->read_at ? '' : 'unread' }} {{ $target ? 'is-linked' : '' }}">
            @if($target)
                {{--
                    The whole row is the link. It is stretched over the card
                    rather than wrapping it so the "Mark as read" form stays a
                    valid, separately clickable control inside the same item.
                --}}
                <a class="notification-open" href="{{ route('notifications.open', $notification) }}">
                    <span class="visually-hidden">Open {{ $eventLabel }}: {{ $message }}</span>
                </a>
            @endif
            <div class="notification-date"><strong>{{ optional($notification->attempted_at)->format('d') }}</strong><span>{{ optional($notification->attempted_at)->format('M Y') }}</span></div>
            <div class="notification-copy"><div class="notification-heading"><h2>{{ $eventLabel }}</h2>@if(!$notification->read_at)<x-status-badge status="UNREAD" label="New" />@endif</div><p>{{ $message }}</p>@if($cancellationReason !== '')<p class="notification-reason"><strong>Reason for cancellation:</strong> {{ $cancellationReason }}</p>@endif<small>{{ optional($notification->attempted_at)->format('d M Y, g:i A') ?: 'Time not recorded' }}</small></div>
            @if(!$notification->read_at)<form method="post" action="{{ route('notifications.read',$notification) }}">@csrf<button class="button ghost small">Mark as read</button></form>@endif
        </article>
    @empty
        <x-empty-state icon="notifications" title="No notifications yet." message="Updates addressed to your account will appear here." />
    @endforelse
    </div>
    <footer class="notification-pagination">
        <div class="notification-pagination-meta">
            <span>Showing {{ $notifications->firstItem() ?? 0 }} to {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications</span>
            <form method="get" action="{{ route('notifications.index') }}">
                <label class="visually-hidden" for="notifications-per-page">Notifications per page</label>
                <select id="notifications-per-page" name="per_page" aria-label="Notifications per page" onchange="this.form.submit()">
                    @foreach([7, 15, 30, 50] as $size)<option value="{{ $size }}" @selected($notifications->perPage() === $size)>{{ $size }} items per page</option>@endforeach
                </select>
            </form>
        </div>
        {{ $notifications->onEachSide(1)->links('partials.pagination') }}
    </footer>
</section>
@endsection

<style>
{{-- /* NOTIFICATION READ WHITE ONLY START */ --}}

{{-- /*
 * Keep the existing notification design completely unchanged.
 *
 * Only an already-read notification becomes white.
 *
 * IMPORTANT:
 * Direct-child selectors identify one notification row only.
 * This prevents the parent list/card from inheriting the unread
 * blue state from another notification.
 */ --}}
:where(article, section, li, div)
:has(> .notification-date)
:has(> .notification-copy)
:not(:has(> .notification-copy .notification-heading .status-badge)) {
    background: #ffffff !important;
}


{{-- /*
 * The date and message sections must remain transparent so the
 * row appears as one continuous white notification.
 */ --}}
:where(article, section, li, div)
:has(> .notification-date)
:has(> .notification-copy)
:not(:has(> .notification-copy .notification-heading .status-badge))
> .notification-date,

:where(article, section, li, div)
:has(> .notification-date)
:has(> .notification-copy)
:not(:has(> .notification-copy .notification-heading .status-badge))
> .notification-copy {
    background: transparent !important;
}


{{-- /*
 * Dark appearance uses the normal application surface rather
 * than forcing a bright white notification.
 */ --}}
html[data-theme="dark"]
:where(article, section, li, div)
:has(> .notification-date)
:has(> .notification-copy)
:not(:has(> .notification-copy .notification-heading .status-badge)) {
    background: var(--surface-elevated) !important;
}

.notification-copy .notification-reason {
    margin-top: 8px;
    padding: 8px 10px;
    color: var(--text-secondary);
    background: var(--surface-subtle);
    border-left: 3px solid var(--info);
    border-radius: 4px;
}

.notification-copy .notification-reason strong {
    color: var(--heading);
}

.notification-pagination { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:16px; padding-top:14px; border-top:1px solid var(--border); }
.notification-pagination-meta { display:inline-flex; align-items:center; flex-wrap:wrap; gap:10px; color:var(--text-muted); font-size:11px; }
.notification-pagination-meta form { margin:0; }
.notification-pagination-meta select { min-height:32px; padding:4px 28px 4px 9px; font-size:11px; }

{{-- /* NOTIFICATION READ WHITE ONLY END */ --}}
</style>

<script>











window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    window.location.reload();
});


</script>
