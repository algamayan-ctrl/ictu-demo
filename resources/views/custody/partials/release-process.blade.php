@php
    /*
     * Defensive release-schedule state.
     *
     * Some release routes already provide $hasPickupSchedule and
     * $pickupWindowPassed, while older/current view paths may not provide the
     * newer $hasSystemPickupWindow variable. Derive the missing values here so
     * this partial can render safely without changing the pickup workflow.
     */
    $hasSystemPickupWindow = isset($hasSystemPickupWindow)
        ? (bool) $hasSystemPickupWindow
        : ((bool) $custody->scheduled_release_at && (bool) $custody->pickup_expires_at);

    $hasPickupSchedule = isset($hasPickupSchedule)
        ? (bool) $hasPickupSchedule
        : ($hasSystemPickupWindow
            && (bool) $custody->pickup_scheduled_at
            && ! $custody->pickup_expired_at);

    $pickupWindowPassed = isset($pickupWindowPassed)
        ? (bool) $pickupWindowPassed
        : ($hasSystemPickupWindow
            && $custody->pickup_expires_at
            && now()->gt($custody->pickup_expires_at));

    $releasePreparationIssues = collect($preparationIssues ?? []);
    $openPreparationIssues = $releasePreparationIssues->where('is_resolved', false)->values();
    $hasOpenPreparationIssue = $openPreparationIssues->isNotEmpty();
    $hasPreparationExceptionPendingRelease = $custody->status === 'PREPARING_RELEASE'
        && ! $custody->released_at
        && ! $custody->prepared_at
        && $releasePreparationIssues->isNotEmpty();
    $pickupHeldForPreparationIssue = $hasPreparationExceptionPendingRelease
        && $pickupWindowPassed;

    $pickupMissed = ! $pickupHeldForPreparationIssue
        && ! $custody->released_at
        && (bool) $custody->pickup_scheduled_at
        && ((bool) $custody->pickup_expired_at || $pickupWindowPassed);

    // Only shown when the AO actually needs to act on the schedule - a normal,
    // on-track schedule is already fully represented by the top status card.
    $releaseScheduleAttention = ! $hasPickupSchedule || $pickupWindowPassed || $pickupMissed || $pickupHeldForPreparationIssue;

    $canConfirmPreparation = $hasPickupSchedule && ! $hasOpenPreparationIssue;
@endphp

<x-request-progress-tracker :request="$custody->request" :show-current-status="false" :compact="true" :release-view="true" />

<section class="content-grid two release-overview-grid">
    <article class="card release-context-card">
        <div class="release-card-title"><x-icon name="calendar" size="21" /><h2>Borrowing &amp; Release Details</h2></div>
        <div class="release-details-columns">
            <div class="release-detail-group">
                <h3>Borrowing</h3>
                <dl class="detail-list release-context-list">
                    <dt>Purpose / Event</dt><dd>{{ $version?->purpose_event ?: '—' }}</dd>
                    <dt>Items Needed From</dt><dd>{{ $scheduleDate?->format('d F Y') ?: 'Not available' }}</dd>
                    <dt>Expected Return</dt><dd>{{ $returnDate?->format('d F Y') ?: 'Not available' }}</dd>
                    <dt>Borrower</dt><dd>{{ $custody->borrower?->full_name ?: '—' }}</dd>
                </dl>
            </div>
            <div class="release-detail-group">
                <h3>Requirements</h3>
                <dl class="detail-list release-context-list">
                    <dt>Use Location</dt><dd>{{ $hasOffCampusItem ? 'Off-campus item included' : 'On-campus only' }}</dd>
                    <dt>Gate Pass</dt><dd>{{ $hasOffCampusItem ? 'Required before off-campus exit' : 'Not required' }}</dd>
                    <dt>Laundry Form</dt><dd>{{ $hasLaundryItem ? 'Required for applicable linen' : 'Not required' }}</dd>
                </dl>
            </div>
        </div>
    </article>

    <article class="card release-documents-card" id="release-actions">
        <div class="release-card-title"><x-icon name="requests" size="21" /><h2>Release Documents</h2></div>
        @include('custody.partials.release-documents')
        @if($hasOffCampusItem)
            <p class="release-step-note">Gate Pass: validate the presented copy before off-campus exit.</p>
        @endif
        @if($hasLaundryItem)
            <p class="release-step-note">Laundry Form: <strong>ISSUED BY</strong> must be completed before Physical Release.</p>
        @endif
    </article>
</section>

@if($releaseScheduleAttention)
    {{-- pickup-schedule-form.blade.php already carries id="pickup-schedule" internally. --}}
    <section class="content-area">
        <article class="card release-schedule-exception-card" role="alert">
            <div class="release-card-title"><x-icon name="warning" size="21" /><h2>Pickup Schedule Needs Attention</h2></div>
            @if($pickupHeldForPreparationIssue)
                <div class="callout warning">
                    <strong>Release is on hold because of an SPMU-side inventory discrepancy.</strong>
                    <span>The approved pickup schedule will not be changed automatically. Head/Admin must resolve the reported discrepancy, or close the unreleased approved request as Unable to Fulfill.</span>
                </div>
            @else
                @include('custody.partials.pickup-schedule-form')
            @endif
        </article>
    </section>
@endif

<section class="content-area" id="item-preparation">
    <article class="card release-approved-card">
        <div class="release-card-title"><x-icon name="inventory" size="21" /><h2>Approved Items</h2></div>
        <div class="table-wrap">
            <table class="release-approved-table">
                <thead><tr><th scope="col">Item</th><th scope="col">Approved Quantity</th><th scope="col">Preparation</th></tr></thead>
                <tbody>
                    @forelse($custody->lines as $line)
                        @php
                            $openLineIssue = $openPreparationIssues->firstWhere('custody_line_id', $line->id);
                            $latestLineIssue = $releasePreparationIssues->firstWhere('custody_line_id', $line->id);
                            $lineStatus = $preparationComplete
                                ? 'Prepared'
                                : ($openLineIssue
                                    ? 'Inventory Review Required'
                                    : ($latestLineIssue && $latestLineIssue['is_resolved']
                                        ? 'Pending Recheck'
                                        : 'Pending'));
                            $lineTone = $preparationComplete
                                ? 'is-complete'
                                : ($openLineIssue
                                    ? 'is-issue'
                                    : ($latestLineIssue && $latestLineIssue['is_resolved']
                                        ? 'is-recheck'
                                        : 'is-pending'));
                        @endphp
                        <tr>
                            <td><strong>{{ $line->requestItem->description_snapshot }}</strong><small>{{ $line->requestItem->unit_snapshot }}</small></td>
                            <td>{{ $line->approved_quantity + 0 }}</td>
                            <td><span class="release-preparation-status {{ $lineTone }}">{{ $lineStatus }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No approved items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @unless($preparationComplete)
            <div class="release-approved-actions">
                <form method="post" action="{{ route('custody.prepare', $custody) }}">
                    @csrf
                    <button class="button primary ui-pressable release-primary" type="submit" @disabled(! $canConfirmPreparation)>
                        <span>Confirm Items Prepared</span>
                    </button>
                </form>
            </div>
        @endunless
    </article>
</section>

<section class="content-area">
    <article class="card release-physical-card">
        <div class="release-card-title"><x-icon name="custody" size="21" /><h2 id="physical-release-title">Physical Release</h2></div>
        @if($preparationComplete && $hasPickupSchedule)
            @if($pickupWindowUpcoming)
                <div class="callout info release-window-notice" id="physical-release-availability">
                    <strong>Physical release is not available yet.</strong>
                    <p>Available from <strong>{{ optional($pickupWindowStartsAt)->format('d F Y, g:i A') }}</strong> to <strong>{{ optional($pickupWindowEndsAt)->format('d F Y, g:i A') }}</strong>.</p>
                </div>
            @elseif($pickupWindowPassed)
                <div class="callout warning release-window-notice" id="physical-release-availability">
                    <strong>Do not record a late physical release.</strong>
                    <p>The claim window ended on <strong>{{ optional($pickupWindowEndsAt)->format('d F Y, g:i A') }}</strong>. Set a new pickup schedule before handing over the items.</p>
                </div>
            @endif
        @elseif($hasOpenPreparationIssue)
            <p class="release-step-note" id="physical-release-availability">Physical Release is unavailable while the reported inventory discrepancy is under review. After review, the Action Officer must check the affected item again.</p>
        @elseif($pickupMissed)
            <p class="release-step-note" id="physical-release-availability">Waiting for a new pickup schedule before physical release.</p>
        @endif
        @include('custody.partials.physical-release-form')
    </article>
</section>
