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

    // Only a prefill suggestion for the bulk-resolve note - never auto-
    // applied, never auto-selected, never used to resolve anything by
    // itself. The textarea stays fully editable.
    $knownDnsErrorSignature = 'getaddrinfo for smtp.gmail.com failed';
    $knownDnsErrorSuggestedNote = 'SMTP/DNS connectivity was verified operational. These delivery failures occurred during the same temporary DNS resolution issue and are retained for audit history.';
@endphp

<section class="page-heading notification-delivery-heading">
    <div>
        <p class="eyebrow">ICTU notification administration</p>
        <h1>{{ $legacyView ? 'Notification Delivery — Legacy SMS Records' : 'Notification Delivery' }}</h1>
        <p>
            @if($legacyView)
                Historical SMS delivery records, retained for audit only. SMS is not a supported channel.
            @else
                Review system and email delivery attempts and provider responses.
            @endif
        </p>
    </div>
    @if($legacyView)
        <a class="button secondary ui-pressable" href="{{ route('reports.notifications') }}">Back to current records</a>
    @else
        <a class="button secondary ui-pressable" href="{{ route('reports.notifications', ['legacy' => 1]) }}">Show legacy SMS records</a>
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
            <input
                id="delivery-search"
                type="search"
                placeholder="Search recipient, event, channel, status..."
                autocomplete="off"
            >
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

    <div class="notification-delivery-summary">
        <span id="delivery-result-count">
            {{ $deliveries->count() }} delivery attempt{{ $deliveries->count() === 1 ? '' : 's' }}
        </span>
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
        @if($canResolveFailures ?? false)
            {{--
                Explicit bulk resolution only: ICTU checks the specific
                Current Failure rows it wants (never pre-selected, never
                automatic), types one required note, and submits. Checkboxes
                live inside the table body but are associated with this form
                via the `form` attribute since they aren't its descendants.
            --}}
            <form
                id="bulk-resolve-form"
                method="post"
                action="{{ route('reports.notifications.resolveBulk') }}"
                class="notification-delivery-bulk-bar"
                hidden
            >
                @csrf
                <span id="bulk-selected-count">0 selected</span>
                <label for="bulk-resolution-note">Resolution note for selected failures</label>
                <textarea
                    id="bulk-resolution-note"
                    name="resolution_note"
                    required
                    minlength="5"
                    maxlength="1000"
                    placeholder="One resolution note applied to every selected failure..."
                ></textarea>
                <button type="submit" class="button primary">Resolve Selected</button>
            </form>
        @endif

        <div class="table-wrap notification-delivery-table-wrap">
            <table class="notification-delivery-table">
                <thead>
                    <tr>
                        @if($canResolveFailures ?? false)
                            <th class="notification-delivery-select-col">Select</th>
                        @endif
                        <th>Time</th>
                        <th>Event</th>
                        <th>Recipient</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Delivery Details</th>
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

                            $isKnownDnsError = str_contains(strtolower($providerResponse), $knownDnsErrorSignature);

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
                        >
                            @if($canResolveFailures ?? false)
                                <td class="notification-delivery-select-col">
                                    @if($canResolveThisRow)
                                        <input
                                            type="checkbox"
                                            name="delivery_ids[]"
                                            value="{{ $delivery->id }}"
                                            form="bulk-resolve-form"
                                            class="notification-delivery-select-checkbox"
                                            data-dns-error="{{ $isKnownDnsError ? '1' : '0' }}"
                                            aria-label="Select this failure for bulk resolution"
                                        >
                                    @endif
                                </td>
                            @endif
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
                            <td colspan="{{ ($canResolveFailures ?? false) ? 7 : 6 }}">
                                <div class="notification-delivery-details-panel">
                                    <div class="notification-delivery-details-header">
                                        <div>
                                            <p class="eyebrow">Delivery details</p>
                                            <h3>{{ $eventDisplay }}</h3>
                                        </div>
                                    </div>

                                    <dl class="notification-delivery-detail-grid">
                                        <div>
                                            <dt>Recipient</dt>
                                            <dd>{{ $recipientName }}</dd>
                                        </div>

                                        <div>
                                            <dt>Channel</dt>
                                            <dd>{{ $channelDisplay }}</dd>
                                        </div>

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

                                        <div>
                                            <dt>Status</dt>
                                            <dd>
                                                @if($isSmsNotConfigured) SMS Not Configured
                                                @elseif($isHistoricalFailure) Historical Delivery Failed
                                                @elseif($isCurrentFailure) Delivery Failed
                                                @else {{ ucwords(strtolower(str_replace('_', ' ', $statusCode))) }}
                                                @endif
                                            </dd>
                                        </div>

                                        @if($isSmsNotConfigured)
                                            <div>
                                                <dt>Raw Status</dt>
                                                <dd>{{ ucwords(strtolower(str_replace('_', ' ', $statusCode))) }}</dd>
                                            </div>
                                        @endif

                                        <div>
                                            <dt>Date &amp; Time</dt>
                                            <dd>{{ $attemptedAt ? $attemptedAt->format('F j, Y • g:i:s A') : 'Not recorded' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Attempt</dt>
                                            <dd>#{{ $delivery->attempt_no ?: 1 }}</dd>
                                        </div>

                                        <div>
                                            <dt>Provider</dt>
                                            <dd>{{ filled($delivery->provider) ? $delivery->provider : 'Not recorded' }}</dd>
                                        </div>

                                        <div>
                                            <dt>Event Code</dt>
                                            <dd><code>{{ $eventCode !== '' ? $eventCode : 'Not recorded' }}</code></dd>
                                        </div>

                                        <div class="notification-delivery-detail-wide">
                                            <dt>Provider Response</dt>
                                            <dd>{{ $providerResponse !== '' ? $providerResponse : 'No provider response recorded.' }}</dd>
                                        </div>

                                        @if($delivery->event)
                                            <div>
                                                <dt>Source Record</dt>
                                                <dd>
                                                    {{ class_basename((string) $delivery->event->source_type) }}
                                                    #{{ $delivery->event->source_id }}
                                                </dd>
                                            </div>

                                            <div>
                                                <dt>Event Recorded</dt>
                                                <dd>{{ optional($delivery->event->occurred_at)->format('F j, Y • g:i:s A') ?: 'Not recorded' }}</dd>
                                            </div>
                                        @endif

                                        @if($isHistoricalFailure)
                                            <div>
                                                <dt>Resolved by</dt>
                                                <dd>{{ $delivery->resolvedBy?->full_name ?? 'Not recorded' }}</dd>
                                            </div>

                                            <div>
                                                <dt>Resolved on</dt>
                                                <dd>{{ $delivery->resolved_at?->format('F j, Y • g:i:s A') ?? 'Not recorded' }}</dd>
                                            </div>

                                            <div class="notification-delivery-detail-wide">
                                                <dt>Resolution note</dt>
                                                <dd>{{ $delivery->resolution_note }}</dd>
                                            </div>
                                        @endif
                                    </dl>

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
                </tbody>
            </table>
        </div>

        <div class="card empty-state notification-delivery-no-results" id="delivery-no-results" hidden>
            No delivery attempts match the selected filters.
        </div>
    @endif
</section>

<style>
.notification-delivery-heading {
    padding-bottom: 10px;
}

.notification-delivery-area {
    display: grid;
    gap: 16px;
}

.notification-delivery-toolbar {
    display: grid;
    grid-template-columns: minmax(280px, 1.6fr) repeat(4, minmax(135px, .7fr)) auto;
    gap: 12px;
    align-items: end;
    padding: 16px;
}

.notification-delivery-toolbar label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-muted, #5c7088);
    font-size: .78rem;
    font-weight: 800;
}

.notification-delivery-toolbar input,
.notification-delivery-toolbar select {
    width: 100%;
    min-height: 44px;
}

.notification-delivery-reset {
    min-height: 44px;
    white-space: nowrap;
}

.notification-delivery-summary {
    color: var(--text-muted, #60738a);
    font-size: .86rem;
}

.notification-delivery-table {
    /* Lowered from 1040px so this fits within the usable content width on
       common desktop viewports (~1280px+) without forcing horizontal
       scroll; still scrolls normally on genuinely narrow screens. */
    min-width: 960px;
}

.notification-delivery-table th {
    white-space: nowrap;
}

.notification-delivery-table td {
    vertical-align: top;
}

.notification-delivery-time {
    width: 120px;
    white-space: nowrap;
}

.notification-delivery-time strong,
.notification-delivery-time span,
.notification-delivery-recipient strong,
.notification-delivery-recipient span {
    display: block;
}

.notification-delivery-time span,
.notification-delivery-recipient span {
    margin-top: 3px;
    color: var(--text-muted, #60738a);
    font-size: .8rem;
}

.notification-delivery-event {
    display: block;
    min-width: 150px;
    text-transform: none;
}

.notification-delivery-channel {
    white-space: nowrap;
}

.notification-delivery-details-cell {
    min-width: 260px;
}

.notification-delivery-details-cell > p {
    margin: 0;
    color: var(--text-muted, #5f738b);
    line-height: 1.45;
}

.notification-delivery-view-button {
    margin-top: 7px;
}

.notification-delivery-detail-row > td {
    padding: 0 !important;
    border-top: 0 !important;
    background: var(--surface-subtle, #f7f9fc);
}

.notification-delivery-details-panel {
    width: 100%;
    box-sizing: border-box;
    padding: 18px 22px 20px;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    background: var(--surface-subtle, #f7f9fc);
}

.notification-delivery-details-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.notification-delivery-details-header h3 {
    margin: 2px 0 0;
    font-size: 1rem;
}

.notification-delivery-detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px 22px;
    margin: 0;
}

.notification-delivery-detail-grid > div {
    min-width: 0;
}

.notification-delivery-detail-grid dt {
    margin-bottom: 4px;
    color: var(--text-muted, #60738a);
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .035em;
    text-transform: uppercase;
}

.notification-delivery-detail-grid dd {
    margin: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.notification-delivery-detail-grid code {
    white-space: normal;
    overflow-wrap: anywhere;
    font-size: .8rem;
}

.notification-delivery-detail-wide {
    grid-column: 1 / -1;
}

.notification-delivery-resolve-form {
    display: grid;
    gap: 8px;
    max-width: 480px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.notification-delivery-resolve-form label {
    color: var(--text-muted, #5c7088);
    font-size: .78rem;
    font-weight: 800;
}

.notification-delivery-resolve-form textarea {
    min-height: 64px;
    resize: vertical;
}

.notification-delivery-resolve-form button {
    justify-self: start;
}

.notification-delivery-bulk-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 12px 16px;
    background: var(--info-bg);
    border: 1px solid var(--info-border);
    border-radius: 8px;
}

.notification-delivery-bulk-bar[hidden] {
    display: none;
}

.notification-delivery-bulk-bar #bulk-selected-count {
    font-weight: 800;
    font-size: .84rem;
    white-space: nowrap;
}

.notification-delivery-bulk-bar label {
    margin: 0;
    color: var(--text-muted, #5c7088);
    font-size: .78rem;
    font-weight: 800;
    white-space: nowrap;
}

.notification-delivery-bulk-bar textarea {
    flex: 1 1 320px;
    min-height: 40px;
    resize: vertical;
}

.notification-delivery-select-col {
    width: 36px;
    text-align: center;
}

@media (max-width: 1050px) {
    .notification-delivery-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .notification-delivery-details-panel {
        padding: 16px;
    }

    .notification-delivery-detail-grid {
        grid-template-columns: 1fr;
    }

    .notification-delivery-detail-wide {
        grid-column: auto;
    }
}

.notification-delivery-no-results {
    text-align: center;
}

@media (max-width: 1200px) {
    .notification-delivery-toolbar {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .notification-delivery-search {
        grid-column: 1 / -1;
    }

    .notification-delivery-reset {
        width: 100%;
    }
}

@media (max-width: 700px) {
    .notification-delivery-toolbar {
        grid-template-columns: 1fr;
    }

    .notification-delivery-search {
        grid-column: auto;
    }

    .notification-delivery-detail-grid {
        grid-template-columns: 1fr;
    }

    .notification-delivery-detail-wide {
        grid-column: auto;
    }
}
</style>

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

    const rows = Array.from(document.querySelectorAll('[data-delivery-row]'));
    const detailRows = Array.from(document.querySelectorAll('[data-delivery-details-row]'));
    const detailToggles = Array.from(document.querySelectorAll('[data-delivery-details-toggle]'));

    detailToggles.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('aria-controls');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) return;

            const willOpen = target.hidden;

            detailRows.forEach((row) => {
                row.hidden = true;
            });

            detailToggles.forEach((toggle) => {
                toggle.setAttribute('aria-expanded', 'false');
            });

            if (willOpen) {
                target.hidden = false;
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });

    const today = () => {
        const value = new Date();
        value.setHours(0, 0, 0, 0);
        return value;
    };

    const dateMatches = (value, selected) => {
        if (selected === 'ALL') return true;
        if (!value) return false;

        const rowDate = new Date(`${value}T00:00:00`);
        const current = today();
        const diffDays = Math.floor((current - rowDate) / 86400000);

        if (selected === 'TODAY') return diffDays === 0;
        if (selected === 'YESTERDAY') return diffDays === 1;
        if (selected === '7_DAYS') return diffDays >= 0 && diffDays <= 6;
        if (selected === '30_DAYS') return diffDays >= 0 && diffDays <= 29;

        return true;
    };

    const applyFilters = () => {
        const query = (search?.value || '').trim().toLowerCase();
        const selectedDate = dateFilter?.value || 'ALL';
        const selectedChannel = channelFilter?.value || 'ALL';
        const selectedStatus = statusFilter?.value || 'ALL';
        const selectedEvent = eventFilter?.value || 'ALL';

        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch =
                !query || (row.dataset.deliverySearch || '').includes(query);

            const matchesChannel =
                selectedChannel === 'ALL'
                || row.dataset.deliveryChannel === selectedChannel;

            const matchesStatus =
                selectedStatus === 'ALL'
                || row.dataset.deliveryStatus === selectedStatus;

            const matchesEvent =
                selectedEvent === 'ALL'
                || row.dataset.deliveryEvent === selectedEvent;

            const matchesDate = dateMatches(
                row.dataset.deliveryDate || '',
                selectedDate
            );

            const show =
                matchesSearch
                && matchesChannel
                && matchesStatus
                && matchesEvent
                && matchesDate;

            row.hidden = !show;

            if (!show) {
                const nextRow = row.nextElementSibling;
                if (nextRow?.matches('[data-delivery-details-row]')) {
                    nextRow.hidden = true;
                }

                const toggle = row.querySelector('[data-delivery-details-toggle]');
                toggle?.setAttribute('aria-expanded', 'false');
            }

            if (show) visible += 1;
        });

        if (resultCount) {
            resultCount.textContent =
                `${visible} delivery attempt${visible === 1 ? '' : 's'}`;
        }

        if (noResults) {
            noResults.hidden = visible !== 0;
        }
    };

    [search, dateFilter, channelFilter, statusFilter, eventFilter].forEach((control) => {
        control?.addEventListener(
            control === search ? 'input' : 'change',
            applyFilters
        );
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

    // Bulk resolution: purely a selection + prefill convenience. Nothing
    // here resolves anything automatically - the form only submits when
    // ICTU explicitly clicks "Resolve Selected" with a note filled in.
    const bulkForm = document.getElementById('bulk-resolve-form');
    const bulkCount = document.getElementById('bulk-selected-count');
    const bulkNote = document.getElementById('bulk-resolution-note');
    const selectCheckboxes = Array.from(document.querySelectorAll('.notification-delivery-select-checkbox'));

    const updateBulkBar = () => {
        if (!bulkForm) return;

        const checked = selectCheckboxes.filter((checkbox) => checkbox.checked);

        bulkForm.hidden = checked.length === 0;

        if (bulkCount) {
            bulkCount.textContent = `${checked.length} selected`;
        }

        const allKnownDnsError = checked.length > 0
            && checked.every((checkbox) => checkbox.dataset.dnsError === '1');

        if (allKnownDnsError && bulkNote && bulkNote.value.trim() === '') {
            bulkNote.value = {{ \Illuminate\Support\Js::from($knownDnsErrorSuggestedNote) }};
        }
    };

    selectCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkBar);
    });

    updateBulkBar();

    applyFilters();
});
</script>

@endsection
