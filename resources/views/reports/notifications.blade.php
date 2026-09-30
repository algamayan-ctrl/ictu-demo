@extends('layouts.app', ['title' => 'Notification Delivery'])

@section('content')
@php
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

    $channelOptions = $deliveries
        ->pluck('channel')
        ->filter()
        ->map(fn ($channel) => strtoupper((string) $channel))
        ->unique()
        ->sort()
        ->values();

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

    $statusOptions = $deliveries
        ->map($categoryFor)
        ->unique()
        ->sort()
        ->values();

    $eventOptions = $deliveries
        ->map(fn ($delivery) => (string) ($delivery->event?->event_code ?? ''))
        ->filter()
        ->unique()
        ->sort()
        ->values();

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
            <span>SMS was removed as a supported notification channel. These {{ $deliveries->count() }} record{{ $deliveries->count() === 1 ? '' : 's' }} are preserved for audit only and are not part of current operational delivery.</span>
        </div>
    @elseif($statusFilter === 'failed')
        <x-filter-context-banner
            label="Failed Notifications"
            :count="$deliveries->count()"
            :clear-url="route('reports.notifications')"
        />
    @endif

    <div class="card notification-delivery-toolbar" aria-label="Notification delivery filters">
        <div class="notification-delivery-search">
            <label for="delivery-search">Search</label>
            <div class="notification-delivery-search-control">
                <x-icon name="search" size="18" />
                <input
                    id="delivery-search"
                    type="search"
                    placeholder="Search recipient, event, channel, status..."
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="delivery-date-filter">Date</label>
            <select id="delivery-date-filter">
                <option value="ALL">All dates</option>
                <option value="TODAY">Today</option>
                <option value="YESTERDAY">Yesterday</option>
                <option value="7_DAYS">Last 7 days</option>
                <option value="30_DAYS">Last 30 days</option>
            </select>
        </div>

        <div>
            <label for="delivery-channel-filter">Channel</label>
            <select id="delivery-channel-filter">
                <option value="ALL">All channels</option>
                @foreach($channelOptions as $channelOption)
                    <option value="{{ $channelOption }}">{{ $channelLabel($channelOption) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="delivery-status-filter">Status</label>
            <select id="delivery-status-filter">
                <option value="ALL">All statuses</option>
                @foreach($statusOptions as $statusOption)
                    <option value="{{ $statusOption }}">{{ $categoryLabel($statusOption) }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="delivery-event-filter">Event</label>
            <select id="delivery-event-filter">
                <option value="ALL">All events</option>
                @foreach($eventOptions as $eventOption)
                    <option value="{{ $eventOption }}">{{ $eventLabel($eventOption) }}</option>
                @endforeach
            </select>
        </div>

        <button class="button secondary notification-delivery-reset" type="button" id="delivery-reset">
            Reset
        </button>
    </div>

    @if($deliveries->isEmpty())
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
                        {{ $deliveries->count() }} total record{{ $deliveries->count() === 1 ? '' : 's' }}
                    </span>
                </div>
                <div class="notification-delivery-pagination" aria-label="Delivery records pagination">
                    <label class="visually-hidden" for="delivery-page-size">Records per page</label>
                    <select id="delivery-page-size" aria-label="Records per page">
                        <option value="7" selected>7 records per page</option>
                        <option value="14">14 records per page</option>
                        <option value="28">28 records per page</option>
                    </select>
                    <span class="notification-delivery-page-divider" aria-hidden="true"></span>
                    <span id="delivery-page-indicator" aria-live="polite">
                        {{ $deliveries->isEmpty() ? '0–0 of 0' : '1–'.min(7, $deliveries->count()).' of '.$deliveries->count() }}
                    </span>
                    <div class="notification-delivery-page-buttons">
                        <button class="notification-delivery-page-button icon-button" id="delivery-prev" type="button" aria-label="Previous page" disabled>
                            <x-icon name="chevron-right" size="16" class="notification-delivery-chevron-left" />
                        </button>
                        <button class="notification-delivery-page-button icon-button" id="delivery-next" type="button" aria-label="Next page" @disabled($deliveries->count() <= 7)>
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
                            $providerResponse = trim((string) $delivery->provider_response);

                            $category = $categoryFor($delivery);
                            $isCurrentFailure = $category === 'CURRENT_FAILURE';
                            $isHistoricalFailure = $category === 'HISTORICAL_FAILURE';
                            $isSmsNotConfigured = $category === 'SMS_NOT_CONFIGURED';
                            $canResolveThisRow = ($canResolveFailures ?? false) && $isCurrentFailure;

                            $shortResponse = match (true) {
                                $isSmsNotConfigured => 'SMS had no configured provider when this was attempted; no delivery was ever contacted.',
                                $isHistoricalFailure => 'Resolved by ICTU. Select View Details for the original provider error and resolution note.',
                                $statusCode === 'SENT' && $channelCode === 'SYSTEM' => 'Stored in the in-system notification record.',
                                $statusCode === 'SENT' && $channelCode === 'EMAIL' => 'Accepted by the configured mail transport.',
                                $statusCode === 'SENT' && $channelCode === 'SMS' => 'Accepted by the configured SMS provider.',
                                $isCurrentFailure => 'Delivery attempt failed. Select View Details to review the provider response.',
                                $providerResponse !== '' => \Illuminate\Support\Str::limit($providerResponse, 72),
                                default => 'No provider response recorded.',
                            };

                            $searchText = strtolower(implode(' ', array_filter([
                                $eventDisplay,
                                $eventCode,
                                $recipientName,
                                $destination,
                                $channelDisplay,
                                $channelCode,
                                $statusCode,
                                $category,
                                $providerResponse,
                            ])));
                            $deliveryDetailsId = 'delivery-details-'.$delivery->id;
                        @endphp

                        <tr
                            data-delivery-row
                            data-delivery-search="{{ $searchText }}"
                            data-delivery-date="{{ optional($attemptedAt)->format('Y-m-d') }}"
                            data-delivery-channel="{{ $channelCode }}"
                            data-delivery-status="{{ $category }}"
                            data-delivery-event="{{ $eventCode }}"
                            @if($loop->index >= 7) hidden @endif
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
                                                <div><dt>Requested At</dt><dd>{{ $attemptedAt ? $attemptedAt->format('M j, Y • g:i A') : 'Not recorded' }}</dd></div>
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
                                                        @else
                                                            <x-status-badge :status="$delivery->delivery_status" />
                                                        @endif
                                                    </dd>
                                                </div>
                                                <div><dt>Provider</dt><dd>{{ filled($delivery->provider) ? $delivery->provider : 'Not recorded' }}</dd></div>
                                                <div><dt>Attempt</dt><dd>#{{ $delivery->attempt_no ?: 1 }}</dd></div>
                                                <div class="notification-delivery-detail-response"><dt>Provider Response</dt><dd>{{ $providerResponse !== '' ? $providerResponse : 'No provider response recorded.' }}</dd></div>
                                            </dl>
                                        </section>

                                        <section class="notification-delivery-detail-group" aria-label="Source details">
                                            <h3>Source Details</h3>
                                            <dl>
                                                <div>
                                                    <dt>Source Record</dt>
                                                    <dd>
                                                        @if($delivery->event)
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
                    <tr class="notification-delivery-no-results" id="delivery-no-results" hidden>
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
    const search = document.getElementById('delivery-search');
    const dateFilter = document.getElementById('delivery-date-filter');
    const channelFilter = document.getElementById('delivery-channel-filter');
    const statusFilter = document.getElementById('delivery-status-filter');
    const eventFilter = document.getElementById('delivery-event-filter');
    const reset = document.getElementById('delivery-reset');
    const resultCount = document.getElementById('delivery-result-count');
    const noResults = document.getElementById('delivery-no-results');
    const pageSizeControl = document.getElementById('delivery-page-size');
    const pageIndicator = document.getElementById('delivery-page-indicator');
    const prev = document.getElementById('delivery-prev');
    const next = document.getElementById('delivery-next');

    const rows = Array.from(document.querySelectorAll('[data-delivery-row]'));
    const detailRows = Array.from(document.querySelectorAll('[data-delivery-details-row]'));
    const detailToggles = Array.from(document.querySelectorAll('[data-delivery-details-toggle]'));
    let filteredRows = [...rows];
    let pageSize = Number(pageSizeControl?.value || 7);
    let currentPage = 1;

    const setExpanded = (row, open) => {
        const detail = row?.nextElementSibling;
        const toggle = row?.querySelector('[data-delivery-details-toggle]');
        if (!detail?.matches('[data-delivery-details-row]')) return;
        detail.hidden = !open;
        toggle?.setAttribute('aria-expanded', String(open));
    };

    detailToggles.forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-delivery-row]');
            const detail = row?.nextElementSibling;
            if (!row || !detail?.matches('[data-delivery-details-row]')) return;

            const willOpen = detail.hidden;
            rows.forEach((item) => setExpanded(item, false));
            if (willOpen && !row.hidden) setExpanded(row, true);
        });
    });

    const startOfToday = () => {
        const value = new Date();
        value.setHours(0, 0, 0, 0);
        return value;
    };

    const dateMatches = (value, selected) => {
        if (selected === 'ALL') return true;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const rowDate = new Date(`${value}T00:00:00`);
        const diffDays = Math.round((startOfToday() - rowDate) / 86400000);
        if (selected === 'TODAY') return diffDays === 0;
        if (selected === 'YESTERDAY') return diffDays === 1;
        if (selected === '7_DAYS') return diffDays >= 0 && diffDays < 7;
        if (selected === '30_DAYS') return diffDays >= 0 && diffDays < 30;
        return true;
    };

    const renderPage = () => {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);
        const start = total ? (currentPage - 1) * pageSize : 0;
        const end = Math.min(start + pageSize, total);

        rows.forEach((row) => {
            row.hidden = true;
            setExpanded(row, false);
        });
        filteredRows.slice(start, end).forEach((row) => { row.hidden = false; });

        if (resultCount) resultCount.textContent = `${total} total record${total === 1 ? '' : 's'}`;
        if (pageIndicator) pageIndicator.textContent = total ? `${start + 1}–${end} of ${total}` : '0–0 of 0';
        if (prev) prev.disabled = currentPage <= 1 || !total;
        if (next) next.disabled = currentPage >= totalPages || !total;
        if (noResults) noResults.hidden = total !== 0 || rows.length === 0;
    };

    const applyFilters = () => {
        const query = (search?.value || '').trim().toLowerCase();
        const selectedDate = dateFilter?.value || 'ALL';
        const selectedChannel = channelFilter?.value || 'ALL';
        const selectedStatus = statusFilter?.value || 'ALL';
        const selectedEvent = eventFilter?.value || 'ALL';

        filteredRows = rows.filter((row) =>
            (!query || (row.dataset.deliverySearch || '').includes(query)) &&
            (selectedChannel === 'ALL' || row.dataset.deliveryChannel === selectedChannel) &&
            (selectedStatus === 'ALL' || row.dataset.deliveryStatus === selectedStatus) &&
            (selectedEvent === 'ALL' || row.dataset.deliveryEvent === selectedEvent) &&
            dateMatches(row.dataset.deliveryDate || '', selectedDate)
        );
        currentPage = 1;
        renderPage();
    };

    [search, dateFilter, channelFilter, statusFilter, eventFilter].forEach((control) => {
        control?.addEventListener(control === search ? 'input' : 'change', applyFilters);
    });

    reset?.addEventListener('click', () => {
        if (search) search.value = '';
        if (dateFilter) dateFilter.value = 'ALL';
        if (channelFilter) channelFilter.value = 'ALL';
        if (statusFilter) statusFilter.value = 'ALL';
        if (eventFilter) eventFilter.value = 'ALL';
        applyFilters();
        search?.focus();
    });

    pageSizeControl?.addEventListener('change', () => {
        pageSize = Math.max(1, Number(pageSizeControl.value || 7));
        currentPage = 1;
        renderPage();
    });
    prev?.addEventListener('click', () => {
        if (currentPage <= 1) return;
        currentPage -= 1;
        renderPage();
    });
    next?.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        if (currentPage >= totalPages) return;
        currentPage += 1;
        renderPage();
    });

    applyFilters();
});
</script>

@endsection
