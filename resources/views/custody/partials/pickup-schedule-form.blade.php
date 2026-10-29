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
    // Approved after the originally suggested pickup date: no schedule was ever
    // set or communicated, so this is a schedule review, not a missed pickup.
    $delayedApprovalReview = $scheduleException && ($pickupScheduleReviewPending ?? false);
    $scheduleReviewDetails = (array) ($pickupScheduleReview?->after_json ?? []);
    $scheduleReviewDate = static function (?string $value, string $format) use ($timezone): ?string {
        return $value ? \Carbon\CarbonImmutable::parse($value)->setTimezone($timezone)->format($format) : null;
    };
    $scheduleDeliveries = collect($pickupScheduleNotification?->deliveries ?? [])->keyBy('channel');
    $missedDeliveryRows = collect($pickupMissedDeliveries ?? $pickupMissedNotification?->deliveries ?? []);

    $deliveryFailed = static function ($delivery): bool {
        return $delivery && strtoupper((string) $delivery->delivery_status) === 'FAILED';
    };

    $scheduleNotificationFailed = $deliveryFailed($scheduleDeliveries->get('SYSTEM'))
        || $deliveryFailed($scheduleDeliveries->get('EMAIL'));
@endphp

<div id="pickup-schedule" class="release-schedule-details">
    @if($pickupWasScheduled && !$pickupMissed && $scheduleNotificationFailed)
        <p class="release-schedule-detail-help">Borrower notification failed. Check notification history.</p>
    @endif
</div>

@if($pickupMissed)
    @php
        $missedNotificationFailed = $missedDeliveryRows->contains(
            fn ($delivery) => strtoupper((string) $delivery->delivery_status) === 'FAILED'
        );
    @endphp

    <div class="release-schedule-exception release-pickup-exception-actions release-compact-pickup">
        <div>
            @if($pickupRescheduleAvailable)
                <p>Confirm pickup when the borrower arrives.</p>
                <div class="release-form-actions release-schedule-review-actions">
                    @include('custody.partials.pickup-confirmation')

                    @if($canCancelBorrowing)
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button release-outline ui-pressable',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled and the borrower will be notified.',
                            'cancelConfirmLabel' => 'Confirm Cancellation',
                            'cancelConfirmClass' => 'button primary ui-pressable',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    @endif
                </div>
            @else
                <p>The pickup period has ended. @if($canCancelBorrowing)Cancel this borrowing.@else Refer this borrowing to the SPMU Head.@endif</p>

                @if($canCancelBorrowing)
                    <div class="release-form-actions release-schedule-review-actions">
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button primary ui-pressable release-primary',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled and the borrower will be notified.',
                            'cancelConfirmLabel' => 'Confirm Cancellation',
                            'cancelConfirmClass' => 'button primary ui-pressable',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    </div>
                @endif
            @endif
            @if($missedNotificationFailed)
                <p class="release-pickup-notice">Borrower notification failed. Check notification history.</p>
            @endif
        </div>
    </div>
@elseif($delayedApprovalReview)
    @php
        $originalSuggestedPickup = $scheduleReviewDate($scheduleReviewDetails['original_suggested_pickup_date'] ?? null, 'F j, Y');
        $approvedAt = $scheduleReviewDate($scheduleReviewDetails['approved_at'] ?? null, 'F j, Y g:i A');
        $itemsNeededFrom = $scheduleReviewDate($scheduleReviewDetails['items_needed_from'] ?? null, 'F j, Y');
        $nextPickupEnd = $nextPickupRescheduleAt
            ? app(\App\Services\OperationalCalendarService::class)->operatingWindow(
                \App\Services\OperationalCalendarService::PICKUP,
                $nextPickupRescheduleAt
            )[1]
            : null;
    @endphp

    <div class="release-schedule-exception release-pickup-exception-actions release-delayed-review release-compact-pickup">
        <div>
            @error('borrower_need_confirmed')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
            @if($pickupRescheduleAvailable)
                <p>Confirm pickup when the borrower arrives.</p>

                <div class="release-form-actions release-schedule-review-actions">
                    @include('custody.partials.pickup-confirmation')

                    @if($canCancelBorrowing)
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button release-outline ui-pressable',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled and the borrower will be notified.',
                            'cancelConfirmClass' => 'button primary ui-pressable',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    @endif
                </div>
            @else
                <p>No pickup time is available. @if($canCancelBorrowing)Cancel this borrowing.@else Refer this borrowing to the SPMU Head.@endif</p>

                @if($canCancelBorrowing)
                    <div class="release-form-actions release-schedule-review-actions">
                        @include('requests.partials.borrower-cancel-control', [
                            'borrowingRequest' => $custody->request,
                            'cancelTriggerClass' => 'button primary ui-pressable release-primary',
                            'cancelTriggerLabel' => 'Cancel Borrowing',
                            'cancelDialogTitle' => 'Cancel this borrowing?',
                            'cancelDialogCopy' => 'This borrowing will be cancelled and the borrower will be notified.',
                            'cancelConfirmClass' => 'button primary ui-pressable',
                            'cancelReasonPlaceholder' => 'Briefly explain why this borrowing is being cancelled...',
                        ])
                    </div>
                @endif
            @endif
            <details class="release-schedule-history">
                <summary>Schedule details</summary>
                @if($pickupRescheduleAvailable)<p class="meta">Pickup: {{ $nextPickupRescheduleAt->format('F j, Y g:i A') }}@if($nextPickupEnd) to {{ $nextPickupEnd->format('g:i A') }}@endif.</p>@endif
                <p class="meta">@if($originalSuggestedPickup)Suggested pickup: {{ $originalSuggestedPickup }}. @endif @if($approvedAt)Approved: {{ $approvedAt }}. @endif @if($itemsNeededFrom)Date needed: {{ $itemsNeededFrom }}.@endif</p>
            </details>
        </div>
    </div>
@elseif($scheduleException)
    <div class="release-schedule-exception release-pickup-exception-actions">
        <p>Review the pickup schedule before proceeding.</p>
        @if($pickupRescheduleAvailable)
            <form method="post" action="{{ route('custody.reschedule-pickup', $custody) }}">
                @csrf
                <div class="release-form-actions">
                    <button class="button primary ui-pressable release-primary" type="submit">
                        Set Pickup Schedule
                    </button>
                </div>
            </form>
        @else
            <div class="release-schedule-exception-note">
                <p>No pickup time is available. Review this borrowing for cancellation.</p>
            </div>
        @endif
    </div>
@endif
