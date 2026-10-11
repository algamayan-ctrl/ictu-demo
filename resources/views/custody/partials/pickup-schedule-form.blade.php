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
    @if(!$pickupWasScheduled)
        <div class="release-schedule-detail-row">
            <span class="release-schedule-detail-label">Schedule status</span>
            <span class="release-schedule-detail-value">
                @if($delayedApprovalReview)
                    Schedule Review Required
                @else
                    {{ $custody->scheduled_release_at && $custody->pickup_expires_at
                        ? 'Waiting for automatic schedule activation'
                        : 'SPMU follow-up required' }}
                @endif
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

    {{-- Scoped to this state; the shared release styles are left untouched. --}}
    <style>
        .release-schedule-review-form { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 10px; margin: 0; }
        .release-schedule-attestation { display: flex; align-items: flex-start; gap: 8px; margin: 0; max-width: 360px; color: var(--text-secondary); font-size: 12px; font-weight: 600; line-height: 1.45; }
        .release-schedule-attestation input { flex: 0 0 auto; width: 16px; height: 16px; margin: 1px 0 0; }
        .release-schedule-attestation-note { display: grid; gap: 4px; margin: 0; flex: 1 1 220px; max-width: 320px; color: var(--text-secondary); font-size: 11.5px; font-weight: 600; }
        .release-schedule-attestation-note input { width: 100%; min-height: 34px; }
    </style>

    <div class="release-schedule-exception release-pickup-exception-actions">
        <div>
            <strong>Schedule Review Required</strong>

            @if(($scheduleReviewDetails['reason'] ?? 'DELAYED_APPROVAL') === 'APPROVED_DURING_SUGGESTED_PICKUP_WINDOW')
                <p>The request was approved on its suggested pickup date, so no pickup schedule was confirmed automatically. Confirm with the borrower when they can collect the equipment before proceeding.</p>
            @else
                <p>The original pickup schedule passed before the request was approved. Confirm whether the borrower still needs the equipment before proceeding.</p>
            @endif

            <p class="meta">
                @if($originalSuggestedPickup)Suggested pickup: {{ $originalSuggestedPickup }}. @endif
                @if($approvedAt)Approved: {{ $approvedAt }}. @endif
                @if($itemsNeededFrom)Items Needed From: {{ $itemsNeededFrom }}. @endif
                This is not a missed pickup.
            </p>

            @error('borrower_need_confirmed')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
            @error('borrower_confirmation_note')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror

            @if($pickupRescheduleAvailable)
                <p class="meta">
                    Next available pickup window under the Operational Calendar:
                    {{ $nextPickupRescheduleAt->format('F j, Y g:i A') }}@if($nextPickupEnd) to {{ $nextPickupEnd->format('g:i A') }}@endif.
                    Verify allocation and preparation before continuing; the items are not released by this step.
                </p>

                <div class="release-form-actions">
                    <form method="post" action="{{ route('custody.reschedule-pickup', $custody) }}" class="release-schedule-review-form">
                        @csrf
                        <label class="release-schedule-attestation">
                            <input type="checkbox" name="borrower_need_confirmed" value="1" required>
                            <span>I contacted the borrower and they confirmed they still need the equipment.</span>
                        </label>
                        <label class="release-schedule-attestation-note">
                            <span>How and when the borrower confirmed</span>
                            <input type="text" name="borrower_confirmation_note" maxlength="200" required
                                value="{{ old('borrower_confirmation_note') }}" placeholder="e.g. Phone call, 2:15 PM">
                        </label>
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
                <p class="meta">No valid pickup window remains on or before the Items Needed From date. This approved release cannot be continued. It must be cancelled; the borrower may then file a new request with new dates.@unless($canCancelBorrowing) Refer this to the SPMU Head/Admin, who can cancel the borrowing.@endunless</p>

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
