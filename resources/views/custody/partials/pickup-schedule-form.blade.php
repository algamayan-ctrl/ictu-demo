@php
    $timezone = config('app.timezone') ?: 'Asia/Manila';
    $now = now($timezone);
    $systemPickupWindowExpired = $custody->pickup_expires_at
        && $now->gte($custody->pickup_expires_at);
    $pickupWasScheduled = (bool) $custody->pickup_scheduled_at;
    $pickupMissed = ! $custody->released_at
        && $pickupWasScheduled
        && ($custody->pickup_expired_at || $systemPickupWindowExpired);
    $scheduleException = ! $custody->released_at
        && ! $pickupWasScheduled
        && (
            ! $custody->scheduled_release_at
            || ! $custody->pickup_expires_at
            || $systemPickupWindowExpired
        );
    $scheduleDeliveries = collect($pickupScheduleNotification?->deliveries ?? [])->keyBy('channel');
    $missedDeliveryRows = collect($pickupMissedDeliveries ?? $pickupMissedNotification?->deliveries ?? []);

    $deliveryFailed = static function ($delivery): bool {
        return $delivery && strtoupper((string) $delivery->delivery_status) === 'FAILED';
    };

    $scheduleNotificationFailed = $deliveryFailed($scheduleDeliveries->get('SYSTEM'))
        || $deliveryFailed($scheduleDeliveries->get('EMAIL'));
@endphp

<div id="pickup-schedule" class="release-schedule-details">
    @if(!$pickupWasScheduled)
        <div class="release-schedule-detail-row">
            <span class="release-schedule-detail-label">Schedule status</span>
            <span class="release-schedule-detail-value">
                {{ $custody->scheduled_release_at && $custody->pickup_expires_at
                    ? 'Waiting for automatic schedule activation'
                    : 'SPMU follow-up required' }}
            </span>
        </div>
    @elseif(!$pickupMissed)
        <div class="release-schedule-detail-row">
            <span class="release-schedule-detail-label">Borrower notification</span>
            <span class="release-schedule-detail-value">
                @if($pickupScheduleNotification)
                    {{ $scheduleNotificationFailed ? 'Needs attention' : 'Sent' }}
                @else
                    Pending
                @endif
            </span>
        </div>

        @if($pickupScheduleNotification && $scheduleNotificationFailed)
            <p class="release-schedule-detail-help">
                At least one notification channel failed. Check Notification History before physical release.
            </p>
        @elseif(!$pickupScheduleNotification)
            <p class="release-schedule-detail-help">
                The pickup schedule is active, but no pickup notification record is available yet.
            </p>
        @endif
    @endif
</div>

@if($pickupMissed)
    @php
        $missedNotificationFailed = $missedDeliveryRows->contains(
            fn ($delivery) => strtoupper((string) $delivery->delivery_status) === 'FAILED'
        );
        $missedNotificationSent = $missedDeliveryRows->contains(
            fn ($delivery) => strtoupper((string) $delivery->delivery_status) === 'SENT'
        );
    @endphp

    <div class="release-schedule-exception release-pickup-exception-actions">
        <div>
            <strong>Pickup Missed &mdash; For SPMU Review</strong>

            <p>The scheduled pickup was not completed. Decide whether to continue this approved release or cancel the borrowing.</p>

            @if($pickupRescheduleRequested)
                <p class="meta">The borrower previously asked about this missed pickup.</p>
            @endif

            @if($missedNotificationFailed)
                <p class="meta">Borrower notification needs attention.</p>
            @elseif($missedNotificationSent)
                <p class="meta">Borrower notified.</p>
            @endif

            @if($pickupRescheduleAvailable)
                <div class="release-form-actions">
                    <form method="post" action="{{ route('custody.reschedule-pickup', $custody) }}">
                        @csrf
                        <button class="button primary ui-pressable release-primary" type="submit">
                            Continue Release
                        </button>
                    </form>

                    @if($canCancelBorrowing)
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button release-outline ui-pressable',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled. The reserved items will return to Available inventory and any pending pickup documents will be invalidated.',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    @endif
                </div>
            @else
                <p class="meta">No valid pickup window remains on or before the Items Needed From date. This approved release can no longer be continued.</p>

                @if($canCancelBorrowing)
                    <div class="release-form-actions">
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button primary ui-pressable release-primary',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled. The reserved items will return to Available inventory and any pending pickup documents will be invalidated.',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    </div>
                @endif
            @endif
        </div>
    </div>
@elseif($scheduleException)
    <div class="release-schedule-exception release-pickup-exception-actions">
        <strong>Pickup schedule needs SPMU follow-up</strong>
        <p>
            The system could not keep a usable pickup window from the SPMU Operational Calendar. This is an SPMU schedule exception, not a borrower missed pickup.
        </p>
        @if($pickupRescheduleAvailable)
            <form method="post" action="{{ route('custody.reschedule-pickup', $custody) }}">
                @csrf
                <div class="release-form-actions">
                    <button class="button primary ui-pressable release-primary" type="submit">
                        Set Next Valid Pickup Schedule
                    </button>
                </div>
            </form>
        @else
            <div class="release-schedule-exception-note">
                <strong>No valid pickup window remains inside the approved period.</strong>
                <p>A new pickup cannot be scheduled on or after the Items Needed From date. Cancel the unreleased request or revise the borrowing dates for approval.</p>
            </div>
        @endif
    </div>
@endif
