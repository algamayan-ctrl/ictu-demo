@extends('layouts.app', ['title' => 'Notification Delivery'])

@section('content')
@php
    // The controller scopes, filters and paginates in the database; this view
    // only renders the current page of delivery records.
    $filters = array_merge([
        'q' => '', 'date' => 'ALL', 'channel' => 'ALL', 'status' => 'ALL', 'event' => 'ALL',
        'per_page' => 7, 'from_dashboard' => false,
    ], $filters ?? []);
    $hasActiveFilters = $filters['q'] !== '' || $filters['date'] !== 'ALL' || $filters['channel'] !== 'ALL'
        || $filters['status'] !== 'ALL' || $filters['event'] !== 'ALL';
    $totalRecords = $totalRecords ?? $deliveries->total();
    $baseQuery = array_filter(['legacy' => $legacyView ? 1 : null, 'per_page' => $filters['per_page'] !== 7 ? $filters['per_page'] : null]);
    $channelOptions = collect($channelOptions ?? []);
    $statusOptions = collect($statusOptions ?? []);
    $eventOptions = collect($eventOptions ?? []);
    // Mailers that never hand a message to a mail server.
    $nonSendingMailers = ['log', 'array'];

    $recipientIds = $deliveries
        ->pluck('recipient_user_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    $recipientNames = $recipientIds->isEmpty()
        ? collect()
        : \App\Models\User::query()
            ->whereIn('id', $recipientIds)
            ->pluck('full_name', 'id');

    // A FAILED row's delivery_status never changes (kept FAILED for audit),
    // so filtering/display uses this derived category instead: it is the
    // one place that combines channel + resolved_at into the three-way
    // failure distinction the dashboard KPI also relies on
    // (NotificationDelivery::scopeActionableFailures()).
    // SMS was carried through three different legacy representations for
    // "no provider was ever configured" before SMS support was removed
    // entirely (an inline FAILED message, then a dedicated NOT_CONFIGURED
    // status, then briefly SKIPPED). All three mean the same thing to a
    // reader today and are shown as one presentational category - this
    // never rewrites the stored delivery_status, only how it is grouped/
    // displayed here.
    $legacySmsNotConfiguredStatuses = ['FAILED', 'NOT_CONFIGURED', 'SKIPPED'];

    $categoryFor = function ($delivery) use ($legacySmsNotConfiguredStatuses): string {
        $channel = strtoupper((string) $delivery->channel);
        $status = strtoupper((string) $delivery->delivery_status);

        return match (true) {
            $channel === 'SMS' && in_array($status, $legacySmsNotConfiguredStatuses, true) => 'SMS_NOT_CONFIGURED',
            $status === 'FAILED' && $delivery->resolved_at !== null => 'HISTORICAL_FAILURE',
            $status === 'FAILED' => 'CURRENT_FAILURE',
            default => $status,
        };
    };

    // "Failed"/"Historical Failure" are renamed here to "Delivery Failed"/
    // "Historical Delivery Failed" for THIS table only - this is delivery-
    // specific wording so a row next to an event like "Pickup Window
    // Expired" reads as "the notification about it failed to deliver," not
    // "the workflow event failed." Event names/statuses elsewhere are
    // untouched.
    $categoryLabel = function (string $category): string {
        return match ($category) {
            'CURRENT_FAILURE' => 'Current Delivery Failures',
            'HISTORICAL_FAILURE' => 'Historical Delivery Failures',
            'SMS_NOT_CONFIGURED' => 'SMS Not Configured',
            'SENT' => 'Sent',
            default => ucwords(strtolower(str_replace('_', ' ', $category))),
        };
    };

    $eventLabel = function (?string $code): string {
        $code = trim((string) $code);

        return $code === ''
            ? 'Unknown Event'
            : ucwords(strtolower(str_replace(['_', '.'], ' ', $code)));
    };

    $channelLabel = function (?string $channel): string {
        return match (strtoupper(trim((string) $channel))) {
            'SYSTEM' => 'In-system',
            'EMAIL' => 'Email',
            'SMS' => 'SMS',
            default => ucwords(strtolower(trim((string) $channel))),
        };
    };

    $deliveryColumnCount = 7;
@endphp


<section class="page-heading notification-delivery-heading" aria-labelledby="notification-delivery-page-title">
    <div>
        <p class="eyebrow">ICTU notification administration</p>
        <h1 id="notification-delivery-page-title">{{ $legacyView ? 'Notification Delivery — Legacy SMS Records' : 'Notification Delivery' }}</h1>
        <p>{{ $legacyView ? 'Historical SMS delivery records retained for audit only.' : 'Review system and email delivery attempts and provider responses.' }}</p>
    </div>
    @if($legacyView)
        <a class="button secondary ui-pressable" href="{{ route('reports.notifications') }}">Back to current records</a>
    @endif
</section>

<section class="content-area notification-delivery-area">
    @if($legacyView)
        <div class="filter-context-banner">
            <span>SMS was removed as a supported notification channel. These {{ number_format($totalRecords) }} record{{ $totalRecords === 1 ? '' : 's' }} are preserved for audit only and are not part of current operational delivery.</span>
        </div>
    @elseif($statusFilter === 'failed')
        <x-filter-context-banner
            label="Failed Notifications"
            :count="$deliveries->total()"
            :clear-url="route('reports.notifications')"
        />
    @endif

    <form id="delivery-filter-form" method="GET" action="{{ route('reports.notifications') }}">
        @if($legacyView)
            <input type="hidden" name="legacy" value="1">
        @endif
    </form>
    <div class="card notification-delivery-toolbar" aria-label="Notification delivery filters">
        <div class="notification-delivery-search">
            <label for="delivery-search">Search</label>
            <div class="notification-delivery-search-control">
                <x-icon name="search" size="18" />
                <input
                    id="delivery-search"
                    type="search"
                    name="q"
                    form="delivery-filter-form"
                    value="{{ $filters['q'] }}"
                    maxlength="120"
                    placeholder="Search recipient, event, channel, status..."
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="delivery-date-filter">Date</label>
            <select id="delivery-date-filter" name="date" form="delivery-filter-form">
                <option value="ALL" @selected($filters['date'] === 'ALL')>All dates</option>
                <option value="TODAY" @selected($filters['date'] === 'TODAY')>Today</option>
                <option value="YESTERDAY" @selected($filters['date'] === 'YESTERDAY')>Yesterday</option>
                <option value="7_DAYS" @selected($filters['date'] === '7_DAYS')>Last 7 days</option>
                <option value="30_DAYS" @selected($filters['date'] === '30_DAYS')>Last 30 days</option>
            </select>
        </div>

        <div>
            <label for="delivery-channel-filter">Channel</label>
            <select id="delivery-channel-filter" name="channel" form="delivery-filter-form">
                <option value="ALL">All channels</option>
                @foreach($channelOptions as $channelOption)
                    <option value="{{ $channelOption }}"{!! $filters['channel'] === $channelOption ? ' selected' : '' !!}>{{ $channelLabel($channelOption) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="delivery-status-filter">Status</label>
            <select id="delivery-status-filter" name="status" form="delivery-filter-form">
                <option value="ALL">All statuses</option>
                @foreach($statusOptions as $statusOption)
                    <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $categoryLabel($statusOption) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="delivery-event-filter">Event</label>
            <select id="delivery-event-filter" name="event" form="delivery-filter-form">
                <option value="ALL">All events</option>
                @foreach($eventOptions as $eventOption)
                    <option value="{{ $eventOption }}" @selected($filters['event'] === strtoupper($eventOption))>{{ $eventLabel($eventOption) }}</option>
                @endforeach
            </select>
        </div>

        <a class="button secondary notification-delivery-reset" id="delivery-reset"
           href="{{ route('reports.notifications', $baseQuery) }}">
            Reset
        </a>
    </div>

    @if($totalRecords === 0)
        <div class="card">
            <x-empty-state
                icon="notifications"
                :title="$legacyView ? 'No legacy SMS records' : 'No notification delivery attempts'"
                :message="$legacyView ? 'No historical SMS delivery records are on file.' : 'Delivery attempts will appear here as notifications are sent.'"
            />
        </div>
    @else
        <section class="card notification-delivery-table-card" aria-labelledby="notification-delivery-table-title">
            <header class="notification-delivery-table-toolbar">
                <div class="notification-delivery-table-heading">
                    <h2 id="notification-delivery-table-title">Delivery Records</h2>
                    <span class="notification-delivery-count" id="delivery-result-count" aria-live="polite">
                        @if($hasActiveFilters)
                            {{ number_format($deliveries->total()) }} matching of {{ number_format($totalRecords) }} total record{{ $totalRecords === 1 ? '' : 's' }}
                        @else
                            {{ number_format($totalRecords) }} total record{{ $totalRecords === 1 ? '' : 's' }}
                        @endif
                    </span>
                </div>
                <div class="notification-delivery-pagination" aria-label="Delivery records pagination">
                    <label class="visually-hidden" for="delivery-page-size">Records per page</label>
                    <select id="delivery-page-size" name="per_page" form="delivery-filter-form" aria-label="Records per page">
                        <option value="7" @selected($filters['per_page'] === 7)>7 records per page</option>
                        <option value="14" @selected($filters['per_page'] === 14)>14 records per page</option>
                        <option value="28" @selected($filters['per_page'] === 28)>28 records per page</option>
                    </select>
                    <span class="notification-delivery-page-divider" aria-hidden="true"></span>
                    <span id="delivery-page-indicator" aria-live="polite">
                        {{ $deliveries->total() === 0 ? '0–0 of 0' : $deliveries->firstItem().'–'.$deliveries->lastItem().' of '.number_format($deliveries->total()) }}
                    </span>
                    <div class="notification-delivery-page-buttons">
                        <button class="notification-delivery-page-button icon-button" id="delivery-prev" type="submit"
                                form="delivery-filter-form" name="page" value="{{ max(1, $deliveries->currentPage() - 1) }}"
                                aria-label="Previous page" @disabled($deliveries->onFirstPage())>
                            <x-icon name="chevron-right" size="16" class="notification-delivery-chevron-left" />
                        </button>
                        <button class="notification-delivery-page-button icon-button" id="delivery-next" type="submit"
                                form="delivery-filter-form" name="page" value="{{ $deliveries->currentPage() + 1 }}"
                                aria-label="Next page" @disabled(! $deliveries->hasMorePages())>
                            <x-icon name="chevron-right" size="16" />
                        </button>
                    </div>
                </div>
            </header>
            <div class="notification-delivery-table-scroll" role="region" aria-label="Notification delivery records" tabindex="0">
                <table class="notification-delivery-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Event</th>
                            <th>Recipient</th>
                            <th>Channel</th>
                            <th>Status</th>
                            <th>Delivery Details</th>
                            <th class="notification-delivery-action-heading">Action</th>
                        </tr>
                    </thead>
                <tbody>
                    @foreach($deliveries as $delivery)
                        @php
                            $eventCode = (string) ($delivery->event?->event_code ?? '');
                            $eventDisplay = $eventLabel($eventCode);
                            $channelCode = strtoupper((string) $delivery->channel);
                            $channelDisplay = $channelLabel($delivery->channel);
                            $statusCode = strtoupper((string) $delivery->delivery_status);
                            $recipientName = $delivery->recipient_user_id
                                ? ($recipientNames->get((int) $delivery->recipient_user_id) ?: 'User #'.$delivery->recipient_user_id)
                                : 'External recipient';

                            $destination = trim((string) $delivery->address_snapshot);
                            $attemptedAt = $delivery->attempted_at ?? $delivery->created_at;
                            // Shown sanitized; the stored record is never rewritten.
                            $providerResponse = \App\Models\NotificationDelivery::sanitizeDiagnostic($delivery->provider_response);
                            $providerCode = strtolower(trim((string) $delivery->provider));

                            $category = $categoryFor($delivery);
                            $isCurrentFailure = $category === 'CURRENT_FAILURE';
                            $isHistoricalFailure = $category === 'HISTORICAL_FAILURE';
                            $isSmsNotConfigured = $category === 'SMS_NOT_CONFIGURED';
                            $canResolveThisRow = ($canResolveFailures ?? false) && $isCurrentFailure;

                            $shortResponse = match (true) {
                                $isSmsNotConfigured => 'SMS had no configured provider when this was attempted; no delivery was ever contacted.',
                                $isHistoricalFailure => 'Resolved by ICTU. Select View Details for the original provider error and resolution note.',
                                $statusCode === 'SENT' && $channelCode === 'SYSTEM' => 'Stored in the in-system notification record.',
                                $statusCode === 'SENT' && $channelCode === 'EMAIL' && in_array($providerCode, $nonSendingMailers, true) => 'Written by the "'.$providerCode.'" mailer only. No email left the server.',
                                $statusCode === 'SENT' && $channelCode === 'EMAIL' => 'Accepted by the configured mail transport.',
                                $statusCode === 'SENT' && $channelCode === 'SMS' => 'Accepted by the configured SMS provider.',
                                $isCurrentFailure => 'Delivery attempt failed. Select View Details to review the provider response.',
                                $providerResponse !== '' => \Illuminate\Support\Str::limit($providerResponse, 72),
                                default => 'No provider response recorded.',
                            };

                            // What the recorded status proves, and what it does not.
                            $statusMeaning = match (true) {
                                $isSmsNotConfigured => 'No SMS provider was configured, so nothing was sent.',
                                $statusCode === 'SENT' && $channelCode === 'SYSTEM' => 'The notification was saved to the recipient\'s in-system notification list. Nothing was transmitted outside the system.',
                                $statusCode === 'SENT' && $channelCode === 'EMAIL' && in_array($providerCode, $nonSendingMailers, true) => 'The message was written by the "'.$providerCode.'" mailer, which does not send email. It did not reach a mail server or the recipient.',
                                $statusCode === 'SENT' && $channelCode === 'EMAIL' => 'The mail transport accepted the message for sending. This does not confirm that it reached the recipient\'s inbox; the system receives no delivery confirmation.',
                                $statusCode === 'FAILED' && in_array($eventCode, \App\Models\NotificationEvent::SCHEDULED_ONCE_ONLY, true) => 'The attempt ended with an error and the message was not accepted. This scheduled reminder is issued once per transaction, so the email was not attempted again. The system does not retry automatically.',
                                $statusCode === 'FAILED' => 'The attempt ended with an error and the message was not accepted. The system does not retry automatically.',
                                default => 'Recorded status: '.ucwords(strtolower(str_replace('_', ' ', $statusCode))).'.',
                            };
                            $inSystemLabel = $statusCode === 'SENT' && $channelCode === 'SYSTEM' ? 'Posted In-System' : null;
                            $deliveryDetailsId = 'delivery-details-'.$delivery->id;
                        @endphp

                        <tr
                            data-delivery-row
                            data-delivery-date="{{ optional($attemptedAt)->format('Y-m-d') }}"
                            data-delivery-channel="{{ $channelCode }}"
                            data-delivery-status="{{ $category }}"
                            data-delivery-event="{{ $eventCode }}"
                        >
                            <td class="notification-delivery-time">
                                @if($attemptedAt)
                                    <strong>{{ $attemptedAt->format('g:i A') }}</strong>
                                    <span>{{ $attemptedAt->format('M j, Y') }}</span>
                                @else
                                    <span>Not recorded</span>
                                @endif
                            </td>

                            <td>
                                <strong class="notification-delivery-event">{{ $eventDisplay }}</strong>
                            </td>

                            <td class="notification-delivery-recipient">
                                <strong>{{ $recipientName }}</strong>

                                @if($channelCode === 'SYSTEM')
                                    <span>In-system notification</span>
                                @elseif($destination !== '')
                                    <span>{{ $destination }}</span>
                                @else
                                    <span>No destination recorded</span>
                                @endif
                            </td>

                            <td>
                                <span class="notification-delivery-channel">{{ $channelDisplay }}</span>
                            </td>

                            <td>
                                @if($isSmsNotConfigured)
                                    <x-status-badge status="NOT_CONFIGURED" label="SMS Not Configured" />
                                @elseif($isHistoricalFailure)
                                    <x-status-badge status="HISTORICAL_FAILURE" label="Historical Delivery Failed" />
                                @elseif($isCurrentFailure)
                                    <x-status-badge :status="$delivery->delivery_status" label="Delivery Failed" />
                                @elseif($inSystemLabel)
                                    <x-status-badge :status="$delivery->delivery_status" :label="$inSystemLabel" />
                                @else
                                    <x-status-badge :status="$delivery->delivery_status" />
                                @endif
                            </td>

                            <td class="notification-delivery-details-cell">
                                <p>{{ $shortResponse }}</p>
                            </td>
                            <td class="notification-delivery-action-cell">
                                <button
                                    type="button"
                                    class="notification-delivery-view-button button secondary small ui-pressable disclosure-trigger"
                                    data-delivery-details-toggle
                                    aria-expanded="false"
                                    aria-controls="{{ $deliveryDetailsId }}"
                                >
                                    <span>View Details</span>
                                    <x-icon name="chevron-down" size="14" class="disclosure-trigger__chevron" />
                                </button>
                            </td>
                        </tr>

                        <tr
                            id="{{ $deliveryDetailsId }}"
                            class="notification-delivery-detail-row"
                            data-delivery-details-row
                            hidden
                        >
                            <td colspan="{{ $deliveryColumnCount }}">
                                <div class="notification-delivery-details-panel">
                                    <div class="notification-delivery-detail-compact-grid">
                                        <section class="notification-delivery-detail-group" aria-label="Delivery information">
                                            <h3>Delivery Information</h3>
                                            <dl>
                                                <div><dt>Event</dt><dd>{{ $eventDisplay }}</dd></div>
                                                <div><dt>Recipient</dt><dd>{{ $recipientName }}</dd></div>
                                                <div>
                                                    <dt>Destination</dt>
                                                    <dd>
                                                        @if($channelCode === 'SYSTEM')
                                                            In-system user #{{ $delivery->recipient_user_id ?: 'Not recorded' }}
                                                        @else
                                                            {{ $destination !== '' ? $destination : 'Not recorded' }}
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div><dt>Channel</dt><dd>{{ $channelDisplay }}</dd></div>
                                                <div><dt>Attempted At</dt><dd>{{ $attemptedAt ? $attemptedAt->format('M j, Y • g:i:s A') : 'Not recorded' }}</dd></div>
                                                <div><dt>Delivery Record</dt><dd>#{{ $delivery->id }}</dd></div>
                                            </dl>
                                        </section>

                                        <section class="notification-delivery-detail-group" aria-label="Delivery status">
                                            <h3>Delivery Status</h3>
                                            <dl>
                                                <div>
                                                    <dt>Status</dt>
                                                    <dd>
                                                        @if($isSmsNotConfigured)
                                                            <x-status-badge status="NOT_CONFIGURED" label="SMS Not Configured" />
                                                        @elseif($isHistoricalFailure)
                                                            <x-status-badge status="HISTORICAL_FAILURE" label="Historical Delivery Failed" />
                                                        @elseif($isCurrentFailure)
                                                            <x-status-badge :status="$delivery->delivery_status" label="Delivery Failed" />
                                                        @elseif($inSystemLabel)
                                                            <x-status-badge :status="$delivery->delivery_status" :label="$inSystemLabel" />
                                                        @else
                                                            <x-status-badge :status="$delivery->delivery_status" />
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div class="notification-delivery-detail-response"><dt>Status Meaning</dt><dd>{{ $statusMeaning }}</dd></div>
                                                <div><dt>{{ $channelCode === 'EMAIL' ? 'Mail Transport' : 'Provider' }}</dt><dd>{{ filled($delivery->provider) ? $delivery->provider : 'Not recorded' }}</dd></div>
                                                <div><dt>Attempt</dt><dd>#{{ $delivery->attempt_no ?: 1 }}</dd></div>
                                                @if($channelCode === 'SYSTEM')
                                                    <div><dt>Opened By Recipient</dt><dd>{{ $delivery->read_at ? $delivery->read_at->format('M j, Y • g:i A') : 'Not yet opened' }}</dd></div>
                                                @endif
                                                @if($statusCode === 'FAILED' && ! $isSmsNotConfigured)
                                                    @php
                                                        // Summary and technical text both come from the sanitized diagnostic only.
                                                        $failureSummary = \App\Models\NotificationDelivery::failureSummary($delivery->provider_response);
                                                    @endphp
                                                    <div class="notification-delivery-detail-response">
                                                        <dt>Failure Reason</dt>
                                                        <dd class="notification-delivery-failure" data-delivery-failure>
                                                            <strong class="notification-delivery-failure-title">{{ $failureSummary['title'] }}</strong>
                                                            <span class="notification-delivery-failure-explanation">{{ $failureSummary['explanation'] }}</span>
                                                            @if($providerResponse !== '')
                                                                <details class="notification-delivery-technical" data-delivery-technical>
                                                                    <summary>
                                                                        <span class="notification-delivery-technical-show">Show Technical Details</span>
                                                                        <span class="notification-delivery-technical-hide">Hide Technical Details</span>
                                                                    </summary>
                                                                    <div class="notification-delivery-technical-text">{{ $providerResponse }}</div>
                                                                </details>
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @else
                                                    <div class="notification-delivery-detail-response"><dt>{{ $statusCode === 'FAILED' ? 'Failure Reason' : ($channelCode === 'EMAIL' ? 'Transport Result' : 'Provider Response') }}</dt><dd>{{ $providerResponse !== '' ? $providerResponse : 'No provider response recorded.' }}</dd></div>
                                                @endif
                                            </dl>
                                        </section>

                                        <section class="notification-delivery-detail-group" aria-label="Source details">
                                            <h3>Source Details</h3>
                                            <dl>
                                                <div>
                                                    <dt>Source Record</dt>
                                                    <dd>
                                                        @if($delivery->event && filled($delivery->event->source_type))
                                                            {{ class_basename((string) $delivery->event->source_type) }} #{{ $delivery->event->source_id }}
                                                        @else
                                                            Not recorded
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div><dt>Event Recorded</dt><dd>{{ optional($delivery->event?->occurred_at)->format('M j, Y • g:i A') ?: 'Not recorded' }}</dd></div>
                                                <div><dt>Event</dt><dd>{{ $eventDisplay }}</dd></div>
                                                @if($isHistoricalFailure)
                                                    <div><dt>Resolved By</dt><dd>{{ $delivery->resolvedBy?->full_name ?? 'Not recorded' }}</dd></div>
                                                    <div><dt>Resolved On</dt><dd>{{ $delivery->resolved_at?->format('M j, Y • g:i A') ?? 'Not recorded' }}</dd></div>
                                                    <div class="notification-delivery-detail-response"><dt>Resolution Note</dt><dd>{{ $delivery->resolution_note }}</dd></div>
                                                @endif
                                            </dl>
                                        </section>
                                    </div>

                                    @if($canResolveThisRow)
                                        <form
                                            method="post"
                                            action="{{ route('reports.notifications.resolve', $delivery) }}"
                                            class="notification-delivery-resolve-form"
                                        >
                                            @csrf
                                            <label for="resolution-note-{{ $delivery->id }}">Resolution note (required)</label>
                                            <textarea
                                                id="resolution-note-{{ $delivery->id }}"
                                                name="resolution_note"
                                                required
                                                minlength="5"
                                                maxlength="1000"
                                                placeholder="Briefly describe how this delivery failure was investigated/resolved..."
                                            ></textarea>
                                            <button type="submit" class="button primary">Mark as Resolved</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr class="notification-delivery-no-results" id="delivery-no-results" @if($deliveries->total() > 0) hidden @endif>
                        <td colspan="{{ $deliveryColumnCount }}">No delivery attempts match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </section>
    @endif
</section>


<script>
document.addEventListener('DOMContentLoaded', () => {
    /*
     * Search, date, channel, status, event, page size and paging are applied
     * by the server across every retained delivery record. Changing a filter
     * reloads the list from its first page; Previous/Next submit the same
     * form with a page number, so filters are kept while paging.
     */
    const form = document.getElementById('delivery-filter-form');
    const search = document.getElementById('delivery-search');
    const rows = Array.from(document.querySelectorAll('[data-delivery-row]'));
    let submitted = false;

    const submitFilters = () => {
        if (!form || submitted) return;
        submitted = true;
        form.submit();
    };

    ['delivery-date-filter', 'delivery-channel-filter', 'delivery-status-filter', 'delivery-event-filter', 'delivery-page-size'].forEach((id) =>
        document.getElementById(id)?.addEventListener('change', submitFilters)
    );
    search?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        submitFilters();
    });
    // Fired when the field is cleared with its built-in clear button.
    search?.addEventListener('search', submitFilters);
    search?.addEventListener('change', submitFilters);
    window.addEventListener('pageshow', () => { submitted = false; });

    const setExpanded = (row, open) => {
        const detail = row?.nextElementSibling;
        const toggle = row?.querySelector('[data-delivery-details-toggle]');
        if (!detail?.matches('[data-delivery-details-row]')) return;
        detail.hidden = !open;
        toggle?.setAttribute('aria-expanded', String(open));
    };

    rows.forEach((row) => {
        row.querySelector('[data-delivery-details-toggle]')?.addEventListener('click', () => {
            const detail = row.nextElementSibling;
            if (!detail?.matches('[data-delivery-details-row]')) return;

            const willOpen = detail.hidden;
            rows.forEach((item) => setExpanded(item, false));
            if (willOpen) setExpanded(row, true);
        });
    });
});
</script>

@endsection
