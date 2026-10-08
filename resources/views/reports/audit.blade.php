@extends('layouts.app', ['title' => ($auditMode ?? 'technical') === 'operational' ? 'Operational Audit Trail' : 'Technical Audit Trail'])

@section('content')
@php
    // Keep the existing AuditEvent fields, category rules and latest-500 controller scope.
    $actorOptions = $events
        ->map(fn ($event) => $event->actor?->full_name ?: 'System')
        ->unique()
        ->sort()
        ->values();

    $categoryOptions = $categoryOptions ?? [];
    $isOperationalAudit = ($auditMode ?? 'technical') === 'operational';

    $preferredAuditLabels = [
        'employee_no' => 'Employee No.',
        'full_name' => 'Full Name',
        'designation' => 'Designation',
        'employment_type' => 'Employment Type',
        'employment_status' => 'Employment Status',
        'email' => 'Email Address',
        'mobile_no' => 'Mobile No.',
        'credential' => 'Credential',
        'account_status' => 'Account Status',
        'access_classification' => 'Access Classification',
        'portal' => 'Portal',
        'division_code' => 'Division',
        'organizational_unit' => 'Office / Unit',
        'organizational_unit.unit_name' => 'Office / Unit',
        'organizationalUnit' => 'Office / Unit',
        'organizationalUnit.unit_name' => 'Office / Unit',
        'roles' => 'Roles',
        'roles.name' => 'Roles',
        'roles.role_name' => 'Roles',
        'authorizedOrganizationalUnits' => 'Authorized Units',
        'authorizedOrganizationalUnits.unit_name' => 'Authorized Units',
        'authorized_organizational_units' => 'Authorized Units',
        'authorized_organizational_units.unit_name' => 'Authorized Units',
        'reason' => 'Reason',
        'status' => 'Status',
        'name' => 'Name',
        'description' => 'Description',
        'value' => 'Value',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
        'due_at' => 'Due Date',
        'released_at' => 'Released At',
        'returned_at' => 'Returned At',
        'quantity' => 'Quantity',
        'actual_quantity' => 'Actual Quantity',
        'condition' => 'Condition',
        'remarks' => 'Remarks',
    ];

    $technicalSegments = [
        'id', 'pivot', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
        'correlation_id', 'action_code', 'origin_ip', 'record_type', 'record_id', 'actor_user_id',
        'organizational_unit_id', 'profile_picture_path', 'email_verified_at', 'last_login_at',
        'failed_login_attempts', 'two_factor_secret', 'two_factor_recovery_codes', 'notification_preferences',
    ];

    $extractSummaryValue = function (array $value): ?string {
        foreach (['unit_name', 'full_name', 'name', 'role_name', 'label', 'title', 'code', 'value', 'description'] as $candidate) {
            $summary = $value[$candidate] ?? null;
            if (is_scalar($summary) && trim((string) $summary) !== '') {
                return (string) $summary;
            }
        }

        return null;
    };

    $displayAuditValue = function ($value) use (&$displayAuditValue, $extractSummaryValue): string {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value === null) {
            return 'Not set';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return 'Not set';
            }

            if (preg_match('/^[A-Z0-9_]+$/', $trimmed) && ! str_contains($trimmed, '@')) {
                return (string) \Illuminate\Support\Str::of(strtolower($trimmed))->replace('_', ' ')->title();
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
                try {
                    return \Illuminate\Support\Carbon::parse($trimmed)->format('F j, Y');
                } catch (\Throwable $e) {
                    return $trimmed;
                }
            }

            /*
             * Audit snapshots often store timestamps in ISO 8601 format.
             * Present them using the same readable application timezone
             * used by the rest of the Audit Trail.
             */
            if (
                preg_match(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
                    $trimmed
                )
            ) {
                try {
                    $dateTime = \Illuminate\Support\Carbon::parse($trimmed)
                        ->timezone(config('app.timezone', 'Asia/Manila'));

                    return $dateTime->format('F j, Y')
                        .' '."\u{2022}".' '
                        .$dateTime->format('g:i A');
                } catch (\Throwable $e) {
                    return $trimmed;
                }
            }

            return $trimmed;
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'Not set';
            }

            $isSequential = array_keys($value) === range(0, count($value) - 1);
            if ($isSequential) {
                $items = collect($value)
                    ->map(function ($item) use ($displayAuditValue, $extractSummaryValue) {
                        if (is_array($item)) {
                            $summary = $extractSummaryValue($item);
                            return $summary !== null ? $displayAuditValue($summary) : null;
                        }

                        if (is_scalar($item) || $item === null) {
                            return $displayAuditValue($item);
                        }

                        return null;
                    })
                    ->filter(fn ($item) => filled($item) && $item !== 'Not set')
                    ->unique()
                    ->values()
                    ->all();

                return $items !== [] ? implode(', ', $items) : 'Not set';
            }

            $summary = $extractSummaryValue($value);
            if ($summary !== null) {
                return $displayAuditValue($summary);
            }
        }

        return 'Not set';
    };

    $flattenAuditSnapshot = function (array $source, string $prefix = '') use (&$flattenAuditSnapshot, $technicalSegments, $displayAuditValue, $extractSummaryValue): array {
        $rows = [];

        foreach ($source as $key => $value) {
            $key = (string) $key;
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $segments = array_filter(explode('.', $path), fn ($segment) => $segment !== '');

            if (collect($segments)->contains(fn ($segment) => in_array($segment, $technicalSegments, true))) {
                continue;
            }

            if (is_array($value)) {
                if ($value === []) {
                    $rows[$path] = 'Not set';
                    continue;
                }

                $isSequential = array_keys($value) === range(0, count($value) - 1);
                if ($isSequential) {
                    $rows[$path] = $displayAuditValue($value);
                    continue;
                }

                $summary = $extractSummaryValue($value);
                if ($summary !== null) {
                    $rows[$path] = $displayAuditValue($summary);
                    continue;
                }

                $rows = array_merge($rows, $flattenAuditSnapshot($value, $path));
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $rows[$path] = $displayAuditValue($value);
            }
        }

        return $rows;
    };

    $auditFieldLabel = function (string $path) use ($preferredAuditLabels): string {
        if (isset($preferredAuditLabels[$path])) {
            return $preferredAuditLabels[$path];
        }

        $lastSegment = \Illuminate\Support\Str::afterLast($path, '.');

        return (string) \Illuminate\Support\Str::of($lastSegment)
            ->replace('_', ' ')
            ->headline();
    };
@endphp

<section class="page-heading ictu-audit-page-heading" aria-labelledby="ictu-audit-page-title">
    <div>
        <p class="eyebrow">{{ $isOperationalAudit ? 'SPMU operational audit' : 'ICTU technical audit' }}</p>
        <h1 id="ictu-audit-page-title">{{ $isOperationalAudit ? 'Operational Audit Trail' : 'Technical Audit Trail' }}</h1>
        <p>{{ $isOperationalAudit ? 'Review significant SPMU operational actions and record changes for accountability and traceability.' : 'Review account, authentication, configuration, security, and other technical system activities.' }}</p>
    </div>
</section>

<section class="content-area ictu-audit-area" aria-label="{{ $isOperationalAudit ? 'SPMU operational audit trail' : 'ICTU technical audit trail' }}">
    <span class="visually-hidden">{{ $isOperationalAudit ? 'SPMU operational oversight' : 'ICTU system administration' }}</span>
    <section class="card ictu-audit-filter-card" aria-label="Audit trail filters">
        <div class="ictu-audit-filter-grid">
            <div class="ictu-audit-filter-field ictu-audit-filter-search">
                <label for="audit-search">Search</label>
                <div class="ictu-audit-search-control">
                    <x-icon name="search" size="20" />
                    <input id="audit-search" type="search"
                           placeholder="Search actor, action, record, reason..."
                           autocomplete="off">
                </div>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-date-filter">Date</label>
                <select id="audit-date-filter">
                    <option value="ALL">All dates</option>
                    <option value="TODAY">Today</option>
                    <option value="YESTERDAY">Yesterday</option>
                    <option value="7_DAYS">Last 7 days</option>
                    <option value="30_DAYS">Last 30 days</option>
                </select>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-category-filter">Category</label>
                <select id="audit-category-filter">
                    <option value="ALL">{{ $isOperationalAudit ? 'All operational events' : 'All technical events' }}</option>
                    @foreach($categoryOptions as $categoryCode => $categoryLabel)
                        <option value="{{ $categoryCode }}">{{ $categoryLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-actor-filter">Actor</label>
                <div class="ictu-audit-actor-actions">
                    <select id="audit-actor-filter">
                        <option value="ALL">All actors</option>
                        @foreach($actorOptions as $actorOption)
                            <option value="{{ $actorOption }}">{{ $actorOption }}</option>
                        @endforeach
                    </select>
                    <button class="button secondary ictu-audit-reset" id="audit-reset" hidden aria-hidden="true" tabindex="-1"
                            type="button" title="Clear all filters">
                        <x-icon name="cycle" size="17" />
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="card ictu-audit-table-card" aria-labelledby="ictu-audit-table-title">
        <header class="ictu-audit-table-toolbar">
            <div class="ictu-audit-table-heading">
                <h1 id="ictu-audit-table-title">{{ $isOperationalAudit ? 'Operational Audit Trail' : 'Technical Audit Trail' }}</h1>
                <span class="ictu-audit-count" id="audit-result-count" aria-live="polite">
                    {{ $events->count() }} total record{{ $events->count() === 1 ? '' : 's' }}
                </span>
            </div>
            <div class="ictu-audit-pagination" aria-label="Audit trail pagination">
                <label class="visually-hidden" for="audit-page-size">Records per page</label>
                <select id="audit-page-size" aria-label="Records per page">
                    <option value="7" selected>7 records per page</option>
                    <option value="14">14 records per page</option>
                    <option value="28">28 records per page</option>
                </select>
                <span class="ictu-audit-page-divider" aria-hidden="true"></span>
                <span id="audit-page-indicator" aria-live="polite">
                    {{ $events->isEmpty() ? '0–0 of 0' : '1–'.min(7, $events->count()).' of '.$events->count() }}
                </span>
                <div class="ictu-audit-page-buttons">
                    <button class="ictu-audit-page-button icon-button" id="audit-prev" type="button"
                            aria-label="Previous page" disabled>
                        <x-icon name="chevron-right" size="17" class="ictu-audit-chevron-left" />
                    </button>
                    <button class="ictu-audit-page-button icon-button" id="audit-next" type="button"
                            aria-label="Next page" @disabled($events->count() <= 7)>
                        <x-icon name="chevron-right" size="17" />
                    </button>
                </div>
            </div>
        </header>

        <div class="ictu-audit-table-scroll" role="region" aria-label="Audit event records" tabindex="0">
            <table class="ictu-audit-table">
                <colgroup>
                    <col class="ictu-audit-col-activity">
                    <col class="ictu-audit-col-type">
                    <col class="ictu-audit-col-account">
                    <col class="ictu-audit-col-time">
                    <col class="ictu-audit-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Activity</th>
                        <th scope="col">Type</th>
                        <th scope="col">Actor / Record</th>
                        <th scope="col">Time</th>
                        <th scope="col" class="ictu-audit-action-heading">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        @php
                            $actionCode = strtolower((string) $event->action_code);
                            $actionLabel = match ($actionCode) {
                                'user.updated', 'user_account_updated' => 'User Account Updated',
                                'user.created', 'user_account_created' => 'User Account Created',
                                'user_email_verified' => 'User Email Verified',
                                'user_email_verification_sent' => 'User Email Verification Sent',
                                'user_email_verification_resent' => 'User Email Verification Resent',
                                'system_setting.updated' => 'System Setting Updated',
                                'user.activated' => 'Account Activated',
                                'user.deactivated' => 'Account Deactivated',
                                'borrower_restriction.created' => 'Borrowing Restriction Opened',
                                'borrower_restriction.lifted' => 'Borrowing Restriction Lifted',
                                default => (string) \Illuminate\Support\Str::of($actionCode)
                                    ->replace(['.', '_'], ' ')
                                    ->title(),
                            };

                            $actorName = $event->actor?->full_name ?: 'System';
                            $recordLabel = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename((string) $event->record_type)) ?: 'Record');
                            $recordName = trim($recordLabel.' #'.$event->record_id);
                            $category = (string) ($event->audit_category ?? 'OTHER');
                            $categoryLabel = $categoryOptions[$category] ?? 'Other';
                            $activitySubtitle = filled($event->reason)
                                ? $event->reason
                                : match ($actionCode) {
                                    'user.updated', 'user_account_updated' => 'Institutional account details updated.',
                                    'user.created', 'user_account_created' => 'New institutional account registered.',
                                    'user.activated' => 'Account status set to Active.',
                                    'user.deactivated' => 'Account status set to Inactive.',
                                    'system_setting.updated' => 'System setting updated.',
                                    'user_email_verified' => 'Institutional email address verified.',
                                    'user_email_verification_sent' => 'Verification message sent.',
                                    'user_email_verification_resent' => 'Verification message sent again.',
                                    'user_email_verification_delivery_failed' => 'Verification email delivery failed.',
                                    'issuance_auto_locked' => 'Issuance was automatically locked.',
                                    default => 'Audit event recorded.',
                                };
                            $icon = match (true) {
                                str_contains($actionCode, 'delet') => 'trash',
                                str_contains($actionCode, 'deactivat') => 'close',
                                str_contains($actionCode, 'activat') => 'shield-check',
                                str_contains($actionCode, 'creat') => 'plus',
                                str_contains($actionCode, 'email'), str_contains($actionCode, 'mail') => 'email',
                                str_contains($actionCode, 'updat'), str_contains($actionCode, 'edit') => 'edit',
                                $category === 'ACCOUNT' => 'users',
                                $category === 'SETTINGS' => 'settings',
                                $category === 'SECURITY_SYSTEM' => 'shield-check',
                                default => 'reports',
                            };
                            $iconTone = match (true) {
                                str_contains($actionCode, 'delet'), str_contains($actionCode, 'deactivat') => 'red',
                                str_contains($actionCode, 'creat'), str_contains($actionCode, 'activat') => 'green',
                                str_contains($actionCode, 'email'), str_contains($actionCode, 'mail') => 'amber',
                                $category === 'SETTINGS' => 'slate',
                                default => 'blue',
                            };
                            $searchText = mb_strtolower(implode(' ', array_filter([
                                $actionLabel, $activitySubtitle,
                                $actorName, $recordName, $event->reason, $categoryLabel,
                            ])));
                        @endphp
                        <tr class="ictu-audit-data-row"
                            data-audit-row
                            data-audit-search="{{ $searchText }}"
                            data-audit-category="{{ $category }}"
                            data-audit-actor="{{ $actorName }}"
                            data-audit-date="{{ $event->occurred_at->format('Y-m-d') }}"
                            @if($loop->index >= 7) hidden @endif>
                            <td>
                                <div class="ictu-audit-activity">
                                    <span class="ictu-audit-row-icon" data-tone="{{ $iconTone }}" aria-hidden="true">
                                        <x-icon :name="$icon" size="19" />
                                    </span>
                                    <div class="ictu-audit-cell-copy">
                                        <strong>{{ $actionLabel }}</strong>
                                        <span class="ictu-audit-subtitle" title="{{ $activitySubtitle }}">{{ $activitySubtitle }}</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="ictu-audit-type-badge" data-type="{{ $category }}">{{ $categoryLabel }}</span></td>
                            <td>
                                <div class="ictu-audit-cell-copy">
                                    <strong>{{ $actorName }}</strong>
                                    <span>{{ $recordName }}</span>
                                </div>
                            </td>
                            <td>
                                <time class="ictu-audit-time" datetime="{{ $event->occurred_at->toIso8601String() }}">
                                    <strong>{{ $event->occurred_at->format('g:i A') }}</strong>
                                    <span>{{ $event->occurred_at->format('M j, Y') }}</span>
                                </time>
                            </td>
                            <td class="ictu-audit-action-cell">
                                <button class="ictu-audit-view-button" type="button"
                                        data-audit-details-toggle
                                        aria-expanded="false"
                                        aria-controls="audit-detail-{{ $event->id }}">
                                    <span>View Details</span>
                                    <x-icon name="chevron-down" size="15" />
                                </button>
                            </td>
                        </tr>
                        <tr class="ictu-audit-expanded-row" id="audit-detail-{{ $event->id }}"
                            data-audit-detail-row hidden>
                            <td colspan="5">
                                @php
                                    $beforeSnapshot = is_array($event->before_json) ? $flattenAuditSnapshot($event->before_json) : [];
                                    $afterSnapshot = is_array($event->after_json) ? $flattenAuditSnapshot($event->after_json) : [];
                                    $changeRows = collect(array_values(array_unique(array_merge(array_keys($beforeSnapshot), array_keys($afterSnapshot)))))
                                        ->map(function (string $path) use ($beforeSnapshot, $afterSnapshot, $auditFieldLabel) {
                                            $beforeValue = $beforeSnapshot[$path] ?? 'Not set';
                                            $afterValue = $afterSnapshot[$path] ?? 'Not set';

                                            if ($beforeValue === $afterValue) {
                                                return null;
                                            }

                                            return [
                                                'label' => $auditFieldLabel($path),
                                                'before' => $beforeValue,
                                                'after' => $afterValue,
                                            ];
                                        })
                                        ->filter()
                                        ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                                        ->values();
                                    $detailReason = filled($event->reason)
                                        ? trim((string) $event->reason)
                                        : null;
                                @endphp
                                <div class="ictu-audit-details-panel">
                                    <dl class="ictu-audit-detail-grid">
                                        <div><dt>Activity</dt><dd>{{ $actionLabel }}</dd></div>
                                        <div><dt>Actor</dt><dd>{{ $actorName }}</dd></div>
                                        <div><dt>Record</dt><dd>{{ $recordName }}</dd></div>
                                        <div><dt>Date &amp; Time</dt><dd>{{ $event->occurred_at->format('F j, Y • g:i:s A') }}</dd></div>
                                        <div><dt>Origin</dt><dd>{{ filled($event->origin_ip) ? $event->origin_ip : 'System / application' }}</dd></div>
                                        <div><dt>Category</dt><dd>{{ $categoryLabel }}</dd></div>
                                    </dl>
                                    @if(filled($detailReason))
                                        <section
                                            class="ictu-audit-info-section"
                                            aria-label="Audit event reason or notes"
                                        >
                                            <h2 class="ictu-audit-section-title">
                                                Reason / Notes
                                            </h2>

                                            <div class="ictu-audit-description-box">
                                                {{ $detailReason }}
                                            </div>
                                        </section>
                                    @endif

                                    <section class="ictu-audit-info-section" aria-label="Audit event changes">
                                        <h2 class="ictu-audit-section-title">Changes</h2>
                                        @if($changeRows->isNotEmpty())
                                            <div class="ictu-audit-change-table-wrap">
                                                <table class="ictu-audit-change-table">
                                                    <colgroup>
                                                        <col class="ictu-audit-change-field-col">
                                                        <col class="ictu-audit-change-before-col">
                                                        <col class="ictu-audit-change-after-col">
                                                    </colgroup>
                                                    <thead>
                                                        <tr>
                                                            <th scope="col">Field</th>
                                                            <th scope="col">Previous Value</th>
                                                            <th scope="col">New Value</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($changeRows as $change)
                                                            <tr>
                                                                <td><strong>{{ $change['label'] }}</strong></td>
                                                                <td>{{ $change['before'] }}</td>
                                                                <td>{{ $change['after'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="ictu-audit-empty-state">
                                                No user-facing field changes are available for this audit entry.
                                            </div>
                                        @endif
                                    </section>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="ictu-audit-empty-directory"><td colspan="5">No attributable administrative actions recorded.</td></tr>
                    @endforelse
                    <tr class="ictu-audit-no-results" id="audit-no-results" hidden>
                        <td colspan="5">No audit events match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('audit-search');
    const dateFilter = document.getElementById('audit-date-filter');
    const categoryFilter = document.getElementById('audit-category-filter');
    const actorFilter = document.getElementById('audit-actor-filter');
    const reset = document.getElementById('audit-reset');
    const count = document.getElementById('audit-result-count');
    const pageSizeControl = document.getElementById('audit-page-size');
    const pageIndicator = document.getElementById('audit-page-indicator');
    const prev = document.getElementById('audit-prev');
    const next = document.getElementById('audit-next');
    const noResults = document.getElementById('audit-no-results');
    const items = Array.from(document.querySelectorAll('[data-audit-row]'));
    let filteredItems = [...items];
    let pageSize = Number(pageSizeControl?.value || 7);
    let currentPage = 1;

    const startOfToday = () => {
        const date = new Date();
        date.setHours(0, 0, 0, 0);
        return date;
    };
    const matchesDate = (value, filter) => {
        if (filter === 'ALL') return true;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        const eventDay = new Date(`${value}T00:00:00`);
        const diffDays = Math.round((startOfToday() - eventDay) / 86400000);
        if (filter === 'TODAY') return diffDays === 0;
        if (filter === 'YESTERDAY') return diffDays === 1;
        if (filter === '7_DAYS') return diffDays >= 0 && diffDays < 7;
        if (filter === '30_DAYS') return diffDays >= 0 && diffDays < 30;
        return true;
    };
    const setExpanded = (row, open) => {
        const detail = row?.nextElementSibling;
        const toggle = row?.querySelector('[data-audit-details-toggle]');
        if (!detail?.matches('[data-audit-detail-row]')) return;
        detail.hidden = !open;
        toggle?.setAttribute('aria-expanded', String(open));
    };
    const renderPage = () => {
        const total = filteredItems.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);
        const start = total ? (currentPage - 1) * pageSize : 0;
        const end = Math.min(start + pageSize, total);
        items.forEach(item => { item.hidden = true; setExpanded(item, false); });
        filteredItems.slice(start, end).forEach(item => { item.hidden = false; });
        if (count) count.textContent = `${total} total record${total === 1 ? '' : 's'}`;
        if (pageIndicator) pageIndicator.textContent = total ? `${start + 1}–${end} of ${total}` : '0–0 of 0';
        if (prev) prev.disabled = currentPage <= 1 || !total;
        if (next) next.disabled = currentPage >= totalPages || !total;
        if (noResults) noResults.hidden = total !== 0 || items.length === 0;
    };
    const applyFilters = () => {
        const query = (search?.value || '').trim().toLocaleLowerCase();
        const selectedDate = dateFilter?.value || 'ALL';
        const selectedCategory = categoryFilter?.value || 'ALL';
        const selectedActor = actorFilter?.value || 'ALL';
        filteredItems = items.filter(item =>
            (!query || (item.dataset.auditSearch || '').includes(query)) &&
            (selectedCategory === 'ALL' || item.dataset.auditCategory === selectedCategory) &&
            (selectedActor === 'ALL' || item.dataset.auditActor === selectedActor) &&
            matchesDate(item.dataset.auditDate || '', selectedDate)
        );
        currentPage = 1;
        renderPage();
    };
    [search, dateFilter, categoryFilter, actorFilter].forEach(control =>
        control?.addEventListener(control === search ? 'input' : 'change', applyFilters)
    );
    reset?.addEventListener('click', () => {
        if (search) search.value = '';
        if (dateFilter) dateFilter.value = 'ALL';
        if (categoryFilter) categoryFilter.value = 'ALL';
        if (actorFilter) actorFilter.value = 'ALL';
        applyFilters();
        search?.focus();
    });
    pageSizeControl?.addEventListener('change', () => {
        pageSize = Number(pageSizeControl.value) || 7;
        currentPage = 1;
        renderPage();
    });
    prev?.addEventListener('click', () => { if (currentPage > 1) { currentPage--; renderPage(); } });
    next?.addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredItems.length / pageSize)) { currentPage++; renderPage(); }
    });
    items.forEach(row => {
        row.querySelector('[data-audit-details-toggle]')?.addEventListener('click', () => {
            const isOpen = row.querySelector('[data-audit-details-toggle]').getAttribute('aria-expanded') === 'true';
            items.forEach(item => setExpanded(item, false));
            setExpanded(row, !isOpen);
        });
    });
    applyFilters();
});
</script>

{{-- AUDIT DETAIL UX ENHANCEMENT START --}}
<script>
document.addEventListener('click', (event) => {
    const selectedToggle = event.target.closest('[data-audit-details-toggle]');
    if (!selectedToggle) return;

    /*
     * Let the Audit Trail's existing disclosure handler update the
     * selected record first. Then synchronize all button labels and
     * close any previously opened detail row.
     */
    window.requestAnimationFrame(() => {
        const toggles = Array.from(
            document.querySelectorAll('[data-audit-details-toggle]')
        );

        toggles.forEach((toggle) => {
            if (toggle !== selectedToggle) {
                toggle.setAttribute('aria-expanded', 'false');

                const controlledId = toggle.getAttribute('aria-controls');
                const detailRow = controlledId
                    ? document.getElementById(controlledId)
                    : null;

                if (detailRow) {
                    detailRow.hidden = true;
                }
            }

            const expanded =
                toggle.getAttribute('aria-expanded') === 'true';

            const label = toggle.querySelector('span');

            if (label) {
                label.textContent = expanded
                    ? 'Hide Details'
                    : 'View Details';
            }
        });
    });
});
</script>
{{-- AUDIT DETAIL UX ENHANCEMENT END --}}
@endsection
