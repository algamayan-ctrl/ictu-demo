@extends('layouts.app', ['title' => ($auditMode ?? 'technical') === 'operational' ? 'Operational Audit Trail' : 'Technical Audit Trail'])

@section('content')
@php
    // The controller scopes, filters and paginates in the database; this view
    // only renders the current page of AuditEvent rows.
    $actorOptions = $actorOptions ?? collect();
    $hasSystemActor = $hasSystemActor ?? false;
    $recordNames = $recordNames ?? [];
    $filters = array_merge([
        'q' => '', 'date' => 'ALL', 'category' => 'ALL', 'actor' => 'ALL',
        'record_type' => null, 'record_id' => null, 'per_page' => 7,
    ], $filters ?? []);
    $hasActiveFilters = $filters['q'] !== '' || $filters['date'] !== 'ALL'
        || $filters['category'] !== 'ALL' || $filters['actor'] !== 'ALL'
        || $filters['record_type'] !== null;

    // Snapshot keys that identify a record by a human-readable name.
    $recordNameKeys = [
        'unique_description', 'request_no', 'custody_no', 'full_name', 'setting_key',
        'incident_no', 'billing_no', 'document_no', 'term_name', 'name', 'title',
    ];

    // Role is shown only when it was stored with the event; never inferred for older events.
    $auditRoleLabel = fn (?string $role): ?string => filled($role)
        ? (\App\Enums\AccessClassification::tryFrom((string) $role)?->label()
            ?? (string) \Illuminate\Support\Str::of(strtolower((string) $role))->replace('_', ' ')->title())
        : null;

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

<section class="page-heading ictu-audit-page-heading{{ $isOperationalAudit ? ' spmu-operational-audit-heading' : '' }}" aria-labelledby="ictu-audit-page-title">
    <div>
        <p class="eyebrow">{{ $isOperationalAudit ? 'SPMU operational audit' : 'ICTU technical audit' }}</p>
        <h1 id="ictu-audit-page-title">{{ $isOperationalAudit ? 'Operational Audit Trail' : 'Technical Audit Trail' }}</h1>
        <p>{{ $isOperationalAudit ? 'Review significant SPMU operational actions and record changes for accountability and traceability.' : 'Review account, authentication, configuration, security, and other technical system activities.' }}</p>
    </div>
</section>

<section class="content-area ictu-audit-area{{ $isOperationalAudit ? ' spmu-operational-audit-area' : '' }}" aria-label="{{ $isOperationalAudit ? 'SPMU operational audit trail' : 'ICTU technical audit trail' }}">
    <span class="visually-hidden">{{ $isOperationalAudit ? 'SPMU operational oversight' : 'ICTU system administration' }}</span>
    <section class="card ictu-audit-filter-card" aria-label="Audit trail filters">
        <form id="audit-filter-form" method="GET" action="{{ route('reports.audit') }}">
            @if($filters['record_type'] !== null)
                <input type="hidden" name="record_type" value="{{ $filters['record_type'] }}">
                <input type="hidden" name="record_id" value="{{ $filters['record_id'] }}">
            @endif
        </form>
        <div class="ictu-audit-filter-grid">
            <div class="ictu-audit-filter-field ictu-audit-filter-search">
                <label for="audit-search">Search</label>
                <div class="ictu-audit-search-control">
                    <x-icon name="search" size="20" />
                    <input id="audit-search" type="search" name="q" form="audit-filter-form"
                           value="{{ $filters['q'] }}" maxlength="120"
                           placeholder="Search actor, action, record, reason..."
                           autocomplete="off">
                </div>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-date-filter">Date</label>
                <select id="audit-date-filter" name="date" form="audit-filter-form">
                    <option value="ALL" @selected($filters['date'] === 'ALL')>All dates</option>
                    <option value="TODAY" @selected($filters['date'] === 'TODAY')>Today</option>
                    <option value="YESTERDAY" @selected($filters['date'] === 'YESTERDAY')>Yesterday</option>
                    <option value="7_DAYS" @selected($filters['date'] === '7_DAYS')>Last 7 days</option>
                    <option value="30_DAYS" @selected($filters['date'] === '30_DAYS')>Last 30 days</option>
                </select>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-category-filter">Category</label>
                <select id="audit-category-filter" name="category" form="audit-filter-form">
                    <option value="ALL">{{ $isOperationalAudit ? 'All operational events' : 'All technical events' }}</option>
                    @foreach($categoryOptions as $categoryCode => $categoryLabel)
                        <option value="{{ $categoryCode }}" @selected($filters['category'] === $categoryCode)>{{ $categoryLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ictu-audit-filter-field">
                <label for="audit-actor-filter">Actor</label>
                <div class="ictu-audit-actor-actions">
                    <select id="audit-actor-filter" name="actor" form="audit-filter-form">
                        <option value="ALL">All actors</option>
                        @foreach($actorOptions as $actorOptionId => $actorOption)
                            <option value="{{ $actorOptionId }}" @selected($filters['actor'] === (string) $actorOptionId)>{{ $actorOption }}</option>
                        @endforeach
                        @if($hasSystemActor)
                            <option value="SYSTEM" @selected($filters['actor'] === 'SYSTEM')>System</option>
                        @endif
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
                    {{ number_format($events->total()) }} {{ $hasActiveFilters ? 'matching' : 'total' }} record{{ $events->total() === 1 ? '' : 's' }}
                    @if($hasActiveFilters)
                        · <a href="{{ route('reports.audit', ['per_page' => $filters['per_page']]) }}">Clear filters</a>
                    @endif
                </span>
                @if(($totalEvents ?? $events->count()) > $events->count())
                    <span class="ictu-audit-count" role="note">
                        Showing latest {{ number_format($events->count()) }} of {{ number_format($totalEvents) }} total records.
                    </span>
                @endif
            </div>
            <div class="ictu-audit-pagination" aria-label="Audit trail pagination">
                <label class="visually-hidden" for="audit-page-size">Records per page</label>
                <select id="audit-page-size" name="per_page" form="audit-filter-form" aria-label="Records per page">
                    <option value="7" @selected($filters['per_page'] === 7)>7 records per page</option>
                    <option value="14" @selected($filters['per_page'] === 14)>14 records per page</option>
                    <option value="28" @selected($filters['per_page'] === 28)>28 records per page</option>
                </select>
                <span class="ictu-audit-page-divider" aria-hidden="true"></span>
                <span id="audit-page-indicator" aria-live="polite">
                    {{ $events->total() === 0 ? '0–0 of 0' : $events->firstItem().'–'.$events->lastItem().' of '.number_format($events->total()) }}
                </span>
                <div class="ictu-audit-page-buttons">
                    <button class="ictu-audit-page-button icon-button" id="audit-prev" type="submit"
                            form="audit-filter-form" name="page" value="{{ max(1, $events->currentPage() - 1) }}"
                            aria-label="Previous page" @disabled($events->onFirstPage())>
                        <x-icon name="chevron-right" size="17" class="ictu-audit-chevron-left" />
                    </button>
                    <button class="ictu-audit-page-button icon-button" id="audit-next" type="submit"
                            form="audit-filter-form" name="page" value="{{ $events->currentPage() + 1 }}"
                            aria-label="Next page" @disabled(! $events->hasMorePages())>
                        <x-icon name="chevron-right" size="17" />
                    </button>
                </div>
            </div>
        </header>

        @if($filters['record_type'] !== null)
            <div class="ictu-audit-empty-state" role="status" style="margin: 12px 16px 0;">
                Full history of
                <strong>{{ trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $filters['record_type'])) }} #{{ $filters['record_id'] }}</strong>,
                oldest first, so repeated changes can be followed in order.
                <a href="{{ route('reports.audit', ['per_page' => $filters['per_page']]) }}">Back to all events</a>
            </div>
        @endif

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

                            // Name and role as stored with the event; older events fall back to the current profile name.
                            $actorName = filled($event->actor_name)
                                ? $event->actor_name
                                : ($event->actor?->full_name ?: 'System');
                            $actorRoleAtTime = $auditRoleLabel($event->actor_role);

                            $recordTypeKey = (string) $event->record_type;
                            $recordBase = class_basename($recordTypeKey);
                            $recordLabel = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $recordBase) ?: 'Record');

                            $liveRecordName = $recordNames[$recordTypeKey][$event->record_id] ?? null;
                            $snapshotRecordName = null;
                            foreach ([$event->after_json, $event->before_json] as $nameSource) {
                                if (! is_array($nameSource)) {
                                    continue;
                                }
                                foreach ($recordNameKeys as $nameKey) {
                                    $candidate = $nameSource[$nameKey] ?? null;
                                    if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                                        $snapshotRecordName = trim((string) $candidate);
                                        break 2;
                                    }
                                }
                            }
                            $recordDisplayName = $liveRecordName ?? $snapshotRecordName;

                            $isDeletionEvent = str_contains($actionCode, 'delet')
                                || (is_array($event->after_json) && ($event->after_json['deleted'] ?? false) === true);
                            // The record type is one whose rows are looked up, and this row is gone.
                            $recordNoLongerExists = $event->record_id !== null
                                && array_key_exists($recordTypeKey, $recordNames)
                                && $liveRecordName === null;
                            $recordIsDeleted = $isDeletionEvent || $recordNoLongerExists;

                            $recordName = trim(
                                $recordLabel
                                .(filled($recordDisplayName) ? ' · '.$recordDisplayName : '')
                                .($event->record_id !== null ? ' #'.$event->record_id : '')
                            );
                            $canViewRecordHistory = $event->record_id !== null
                                && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,79}$/', $recordBase) === 1;
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
                                    'request_supporting_document_uploaded' => 'Supporting document uploaded.',
                                    'request_supporting_document_replaced' => 'Supporting document replaced with a new version.',
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
                                $category === 'SETTINGS', $category === 'OPERATIONAL_SETTINGS' => 'settings',
                                $category === 'SECURITY_SYSTEM' => 'shield-check',
                                default => 'reports',
                            };
                            $iconTone = match (true) {
                                str_contains($actionCode, 'delet'), str_contains($actionCode, 'deactivat') => 'red',
                                str_contains($actionCode, 'creat'), str_contains($actionCode, 'activat') => 'green',
                                str_contains($actionCode, 'email'), str_contains($actionCode, 'mail') => 'amber',
                                $category === 'SETTINGS', $category === 'OPERATIONAL_SETTINGS' => 'slate',
                                default => 'blue',
                            };
                        @endphp
                        <tr class="ictu-audit-data-row"
                            data-audit-row>
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
                                    <span title="{{ $recordName }}">{{ $recordName }}</span>
                                    @if($recordIsDeleted)
                                        <span><span class="status-badge status-danger">Deleted record</span></span>
                                    @endif
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
                                        ->map(function (string $path) use ($beforeSnapshot, $afterSnapshot, $auditFieldLabel, $isDeletionEvent) {
                                            // A deletion's snapshot is the record's last state; the marker flag itself is not a field.
                                            if ($isDeletionEvent && $path === 'deleted') {
                                                return null;
                                            }

                                            $beforeValue = $beforeSnapshot[$path] ?? 'Not set';
                                            $afterValue = $afterSnapshot[$path] ?? ($isDeletionEvent ? 'Deleted' : 'Not set');

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
                                        <div><dt>Actor</dt><dd>{{ $actorName }}@if($actorRoleAtTime) <small>({{ $actorRoleAtTime }} at the time)</small>@endif</dd></div>
                                        <div>
                                            <dt>Record</dt>
                                            <dd>
                                                {{ $recordName }}
                                                @if($recordIsDeleted)
                                                    <span class="status-badge status-danger">Deleted record</span>
                                                @endif
                                                @if($canViewRecordHistory && ! ($filters['record_type'] === $recordBase && $filters['record_id'] === (int) $event->record_id))
                                                    <br>
                                                    <a href="{{ route('reports.audit', ['record_type' => $recordBase, 'record_id' => $event->record_id, 'per_page' => $filters['per_page']]) }}"
                                                       data-audit-record-history>View Full History</a>
                                                @endif
                                            </dd>
                                        </div>
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
                                        <h2 class="ictu-audit-section-title">{{ $isDeletionEvent ? 'Preserved snapshot of the deleted record' : 'Changes' }}</h2>
                                        @if($isDeletionEvent)
                                            <div class="ictu-audit-empty-state" style="margin-bottom: 10px;">
                                                This record was permanently deleted. The values below are the snapshot kept at the moment of deletion; the record itself cannot be restored from this page.
                                            </div>
                                        @endif
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

                                    @php
                                        $recovery = $event->getAttributes()['recovery'] ?? null;
                                    @endphp
                                    @if(is_array($recovery))
                                        <section class="ictu-audit-info-section" aria-label="Recovery" data-audit-recovery>
                                            <h2 class="ictu-audit-section-title">Recovery</h2>
                                            @if($recovery['reverted_by'])
                                                <div class="ictu-audit-empty-state">
                                                    This change was reverted on
                                                    {{ $recovery['reverted_by']->occurred_at->format('F j, Y • g:i A') }}
                                                    (audit event #{{ $recovery['reverted_by']->id }}). Both events remain in the Audit Trail.
                                                </div>
                                            @elseif($recovery['allowed'])
                                                <details data-audit-revert>
                                                    <summary class="button secondary">Revert This Change</summary>
                                                    <form method="POST" action="{{ route('reports.audit.revert', ['event' => $event->id]) }}" style="margin-top: 10px;">
                                                        @csrf
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
                                                                        <th scope="col">Current Value</th>
                                                                        <th scope="col">Will Be Restored To</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($recovery['fields'] as $recoveryField)
                                                                        <tr>
                                                                            <td><strong>{{ $recoveryField['label'] }}</strong></td>
                                                                            <td>{{ $recoveryField['current'] }}</td>
                                                                            <td>{{ $recoveryField['restore'] }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        @if($recovery['skipped'] !== [])
                                                            <p class="field-help" style="margin: 8px 0 0;">
                                                                Not restored by this action: {{ implode(', ', $recovery['skipped']) }}.
                                                                These values are corrected through their own screen or workflow.
                                                            </p>
                                                        @endif
                                                        <label for="audit-revert-reason-{{ $event->id }}" style="display: block; margin-top: 10px;">
                                                            Reason for reverting (required)
                                                        </label>
                                                        <textarea id="audit-revert-reason-{{ $event->id }}" name="revert_reason" rows="2"
                                                                  required minlength="10" maxlength="1000" style="width: 100%;"></textarea>
                                                        <label style="display: block; margin: 8px 0;">
                                                            <input type="checkbox" name="confirm_revert" value="1" required>
                                                            I confirm that only the values listed above will be restored. The original audit event stays unchanged.
                                                        </label>
                                                        <button class="button" type="submit">Confirm Revert</button>
                                                    </form>
                                                </details>
                                            @else
                                                <div class="ictu-audit-empty-state">{{ $recovery['blocker'] }}</div>
                                            @endif
                                        </section>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="ictu-audit-empty-directory"><td colspan="5">{{ $hasActiveFilters ? 'No audit events match the selected filters.' : 'No attributable administrative actions recorded.' }}</td></tr>
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
    /*
     * Search, date, category, actor, page size and paging are applied by the
     * server across every retained audit event. Changing a filter reloads the
     * list from its first page; the Previous/Next buttons submit the same
     * form with a page number, so filters are kept while paging.
     */
    const form = document.getElementById('audit-filter-form');
    const search = document.getElementById('audit-search');
    const items = Array.from(document.querySelectorAll('[data-audit-row]'));
    let submitted = false;

    const submitFilters = () => {
        if (!form || submitted) return;
        submitted = true;
        form.submit();
    };

    ['audit-date-filter', 'audit-category-filter', 'audit-actor-filter', 'audit-page-size'].forEach(id =>
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
        const toggle = row?.querySelector('[data-audit-details-toggle]');
        if (!detail?.matches('[data-audit-detail-row]')) return;
        detail.hidden = !open;
        toggle?.setAttribute('aria-expanded', String(open));
    };
    items.forEach(row => {
        row.querySelector('[data-audit-details-toggle]')?.addEventListener('click', () => {
            const isOpen = row.querySelector('[data-audit-details-toggle]').getAttribute('aria-expanded') === 'true';
            items.forEach(item => setExpanded(item, false));
            setExpanded(row, !isOpen);
        });
    });
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
