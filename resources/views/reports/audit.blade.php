@extends('layouts.app', ['title' => 'Audit Trail'])
@section('content')
@php
    // Keep the existing AuditEvent fields, category rules and latest-500 controller scope.
    $actorOptions = $events
        ->map(fn ($event) => $event->actor?->full_name ?: 'System')
        ->unique()
        ->sort()
        ->values();

    $categoryOptions = [
        'ACCOUNT' => 'Account',
        'BORROWING' => 'Borrowing',
        'APPROVAL' => 'Approval',
        'RELEASE_RETURN' => 'Release / Return',
        'LAUNDRY_GATEPASS' => 'Laundry / Gate Pass',
        'ACCOUNTABILITY' => 'Accountability',
        'SETTINGS' => 'Settings',
        'SECURITY_SYSTEM' => 'Security / System',
        'OTHER' => 'Other',
    ];
@endphp

<section class="content-area ictu-audit-area" aria-label="ICTU audit trail">
    <span class="visually-hidden">System administration</span>
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
                    <option value="ALL">All categories</option>
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
                    <button class="button secondary ictu-audit-reset" id="audit-reset"
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
                <h1 id="ictu-audit-table-title">Audit Trail</h1>
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
                    <button class="ictu-audit-page-button" id="audit-prev" type="button"
                            aria-label="Previous page" disabled>
                        <x-icon name="chevron-right" size="17" class="ictu-audit-chevron-left" />
                    </button>
                    <button class="ictu-audit-page-button" id="audit-next" type="button"
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
                                'system_setting.updated' => 'System Setting Updated',
                                'user.activated' => 'Account Activated',
                                'user.deactivated' => 'Account Deactivated',
                                'borrower_restriction.created' => 'Borrowing Restriction Opened',
                                'borrower_restriction.lifted' => 'Borrowing Restriction Lifted',
                                default => ucwords(str_replace(['.', '_'], [' ', ' '], (string) $event->action_code)),
                            };

                            $actorName = $event->actor?->full_name ?: 'System';
                            $recordName = trim(class_basename((string) $event->record_type).' #'.$event->record_id);
                            $category = match (true) {
                                str_contains($actionCode, 'approval'),
                                str_contains($actionCode, 'approve'),
                                str_contains($actionCode, 'reject'),
                                str_contains($actionCode, 'verify') => 'APPROVAL',

                                str_contains($actionCode, 'laundry'),
                                str_contains($actionCode, 'gate_pass'),
                                str_contains($actionCode, 'gatepass') => 'LAUNDRY_GATEPASS',

                                str_contains($actionCode, 'billing'),
                                str_contains($actionCode, 'payment'),
                                str_contains($actionCode, 'restriction'),
                                str_contains($actionCode, 'incident'),
                                str_contains($actionCode, 'offense'),
                                str_contains($actionCode, 'sanction'),
                                str_contains($actionCode, 'penalty'),
                                str_contains($actionCode, 'accountability') => 'ACCOUNTABILITY',

                                str_contains($actionCode, 'issuance'),
                                str_contains($actionCode, 'release'),
                                str_contains($actionCode, 'return'),
                                str_contains($actionCode, 'custody') => 'RELEASE_RETURN',

                                str_contains($actionCode, 'request'),
                                str_contains($actionCode, 'borrow') => 'BORROWING',

                                str_contains($actionCode, 'user'),
                                str_contains($actionCode, 'account'),
                                str_contains($actionCode, 'profile'),
                                str_contains($actionCode, 'role'),
                                str_contains($actionCode, 'delegation') => 'ACCOUNT',

                                str_contains($actionCode, 'setting'),
                                str_contains($actionCode, 'config'),
                                str_contains($actionCode, 'policy') => 'SETTINGS',

                                str_contains($actionCode, 'login'),
                                str_contains($actionCode, 'auth'),
                                str_contains($actionCode, 'security'),
                                str_contains($actionCode, 'notification'),
                                $event->actor_user_id === null => 'SECURITY_SYSTEM',

                                default => 'OTHER',
                            };
                            $categoryLabel = $categoryOptions[$category] ?? 'Other';
                            $activitySubtitle = filled($event->reason)
                                ? $event->reason
                                : match ($actionCode) {
                                    'user.updated', 'user_account_updated' => 'Institutional account details updated.',
                                    'user.created', 'user_account_created' => 'New institutional account registered.',
                                    'user.activated' => 'Account status set to Active.',
                                    'user.deactivated' => 'Account status set to Inactive.',
                                    'system_setting.updated' => 'System setting updated.',
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
                                $actionLabel, $activitySubtitle, $event->action_code,
                                $actorName, $recordName, $event->reason, $categoryLabel,
                            ])));
                        @endphp
                        <tr class="ictu-audit-data-row"
                            data-audit-row
                            data-audit-search="{{ $searchText }}"
                            data-audit-category="{{ $category }}"
                            data-audit-actor="{{ $actorName }}"
                            data-audit-date="{{ $event->occurred_at->format('Y-m-d') }}">
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
                                <div class="ictu-audit-details-panel">
                                    <dl class="ictu-audit-detail-grid">
                                        <div><dt>Activity</dt><dd>{{ $actionLabel }}</dd></div>
                                        <div><dt>Action Code</dt><dd><code>{{ $event->action_code }}</code></dd></div>
                                        <div><dt>Actor</dt><dd>{{ $actorName }}</dd></div>
                                        <div><dt>Record</dt><dd>{{ $recordName }}</dd></div>
                                        <div><dt>Date &amp; Time</dt><dd>{{ $event->occurred_at->format('F j, Y • g:i:s A') }}</dd></div>
                                        <div><dt>Origin</dt><dd>{{ filled($event->origin_ip) ? $event->origin_ip : 'System / application' }}</dd></div>
                                        @if(filled($event->reason))
                                            <div class="ictu-audit-detail-wide"><dt>Reason</dt><dd>{{ $event->reason }}</dd></div>
                                        @endif
                                        @if(filled($event->correlation_id))
                                            <div class="ictu-audit-detail-wide"><dt>Technical Reference</dt><dd><code>{{ $event->correlation_id }}</code></dd></div>
                                        @endif
                                    </dl>
                                    @if(!empty($event->before_json) || !empty($event->after_json))
                                        <div class="ictu-audit-change-grid">
                                            @if(!empty($event->before_json))
                                                <section><h2>Before</h2><pre>{{ json_encode($event->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section>
                                            @endif
                                            @if(!empty($event->after_json))
                                                <section><h2>After</h2><pre>{{ json_encode($event->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section>
                                            @endif
                                        </div>
                                    @endif
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

    @if($totalEventCount > $events->count())
        <p class="ictu-audit-limit-note">
            Showing latest {{ number_format($events->count()) }} of {{ number_format($totalEventCount) }} total records.
            Filters and page navigation apply to these loaded records.
        </p>
    @endif
</section>

<style>
/* Audit Trail matches the ICTU User Accounts layout without changing other modules. */
.ictu-audit-shell { --sidebar-width: 290px; --topbar-height: 92px; }
html:not([data-theme="dark"]) .ictu-audit-shell .app-stage { background: #f5faff; }
html:not([data-theme="dark"]) .ictu-audit-shell .app-sidebar { background: #eaf6ff !important; }
.ictu-audit-shell .sidebar-brand-row { min-height: 91px; padding: 9px 18px 10px; border-bottom: 0; }
.ictu-audit-shell .sidebar-brand.sidebar-brand-logo { min-height: 61px; padding: 3px 8px; justify-content: flex-start; }
.ictu-audit-shell .sidebar-brand-logo-image { width: 100%; max-width: 225px; max-height: 65px; object-position: left center; }
.ictu-audit-shell .sidebar-label { margin: 13px 22px 16px !important; font-size: 12.5px !important; letter-spacing: .16em !important; }
.ictu-audit-shell .sidebar-nav { gap: 8px; padding: 0 13px 14px; }
.ictu-audit-shell .sidebar-nav a { min-height: 56px; padding: 0 16px; gap: 15px; border-radius: 15px; font-size: 15px; font-weight: 680; }
.ictu-audit-shell .sidebar-nav a .nav-icon { width: 27px; min-width: 27px; }
.ictu-audit-shell .sidebar-nav a .nav-icon svg { width: 23px; height: 23px; }
.ictu-audit-shell .sidebar-nav a.active,
.ictu-audit-shell .sidebar-nav a[aria-current="page"] { background: #1869e7 !important; border-color: #1869e7 !important; box-shadow: 0 9px 19px rgba(23,104,230,.14); }
.ictu-audit-shell .app-topbar { min-height: 92px; padding: 11px 24px; }
.ictu-audit-shell .topbar-title { display: grid; min-width: 0; gap: 3px; }
.ictu-audit-shell .topbar-title strong { font-size: 24px; line-height: 1.17; font-weight: 760; letter-spacing: -.025em; }
.ictu-audit-shell .ictu-topbar-subtitle { display: block; font-size: 15px; line-height: 1.35; font-weight: 400; color: var(--text-muted); }
.ictu-audit-shell .account-nav { gap: 15px; }
.ictu-audit-shell .account-menu-name { max-width: 210px; font-size: 14px; font-weight: 680; }
.ictu-audit-shell .account-menu-toggle { gap: 10px; }
.ictu-audit-shell .account-menu-toggle .user-avatar { width: 44px; height: 44px; flex-basis: 44px; background: #193e68; font-size: 12px; }
.app-main.ictu-audit-main { width: 100%; max-width: none; margin: 0; padding: 16px 18px 42px; }
.ictu-audit-area { display: grid; width: 100%; min-width: 0; gap: 18px; margin: 0; }
.ictu-audit-area :is(input, select, button) { font-family: inherit; }
.ictu-audit-area :is(input, select, button):focus-visible { outline: 2px solid var(--interactive); outline-offset: 2px; }
.ictu-audit-filter-card, .ictu-audit-table-card {
    width: 100%; min-width: 0; margin: 0; padding: 0; overflow: hidden;
    background: var(--surface-elevated); border: 1px solid var(--border); border-radius: 11px;
    box-shadow: 0 4px 16px rgba(21,64,117,.035);
}
.ictu-audit-filter-grid { display: grid; grid-template-columns: minmax(0,2.08fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1.69fr); align-items: end; gap: 12px; padding: 18px; }
.ictu-audit-filter-field { display: grid; min-width: 0; gap: 8px; }
.ictu-audit-filter-field label { display: block; min-width: 0; margin: 0; color: var(--heading); font-size: 13.5px; font-weight: 700; }
.ictu-audit-filter-field :is(input, select), .ictu-audit-filter-field button {
    display: block; width: 100%; min-width: 0; height: 50px; min-height: 50px; margin: 0;
    padding: 0 14px; border: 1px solid var(--border-strong); border-radius: 9px;
    background-color: var(--input-bg); color: var(--text); font-size: 13.5px; font-weight: 400;
}
.ictu-audit-search-control { position: relative; min-width: 0; }
.ictu-audit-search-control > .ui-icon { position: absolute; top: 50%; left: 16px; z-index: 1; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.ictu-audit-search-control input { padding-left: 49px; }
.ictu-audit-actor-actions { display: grid; grid-template-columns: minmax(0,1fr) 102px; gap: 10px; min-width: 0; }
.ictu-audit-actor-actions .ictu-audit-reset {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    padding: 0 12px; font-size: 13px; font-weight: 700; white-space: nowrap;
    color: var(--text-secondary); background: var(--surface-elevated);
}
.ictu-audit-reset:hover { color: var(--interactive); border-color: var(--interactive); }
.ictu-audit-table-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 20px; min-height: 64px; padding: 11px 19px; border-bottom: 1px solid var(--border); }
.ictu-audit-table-heading { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 12px; min-width: 0; }
.ictu-audit-table-heading h1 { margin: 0; font-size: 19px; line-height: 1.3; font-weight: 760; color: var(--heading); }
.ictu-audit-count { display: inline-flex; justify-content: center; align-items: center; padding: 4px 10px; border: 1px solid var(--border); border-radius: 99px; background: var(--surface-muted); color: var(--text-secondary); white-space: nowrap; font-size: 12.5px; font-weight: 650; }
.ictu-audit-pagination { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 12px; color: var(--text-secondary); font-size: 13px; }
.ictu-audit-pagination > select { width: auto; min-width: 150px; height: 36px; min-height: 36px; padding: 6px 30px 6px 8px; border: 1px solid transparent; background-color: transparent; border-radius: 8px; font-size: 13px; color: var(--text-secondary); }
.ictu-audit-page-divider { height: 23px; width: 1px; background: var(--border); }
.ictu-audit-page-buttons { display: inline-flex; gap: 7px; }
.ictu-audit-page-button { display: inline-grid; place-items: center; width: 39px; height: 39px; padding: 0; border: 1px solid var(--border); border-radius: 9px; background: var(--surface-muted); color: var(--text-secondary); cursor: pointer; }
.ictu-audit-page-button:hover:not(:disabled) { color: var(--interactive); border-color: var(--interactive); }
.ictu-audit-page-button:disabled { opacity: .42; cursor: not-allowed; }
.ictu-audit-chevron-left { transform: rotate(180deg); }
.ictu-audit-table-scroll { width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-x: contain; }
.ictu-audit-table { width: 100%; min-width: 880px; table-layout: fixed; border-collapse: collapse; border-spacing: 0; }
.ictu-audit-col-activity { width: 32%; }.ictu-audit-col-type { width: 14%; }.ictu-audit-col-account { width: 24%; }.ictu-audit-col-time { width: 14%; }.ictu-audit-col-action { width: 16%; }
.ictu-audit-table th { padding: 12px 16px; background: var(--table-heading-bg); border-bottom: 1px solid var(--border); color: var(--text-secondary); font-size: 11px; line-height: 1.25; font-weight: 780; letter-spacing: .045em; text-transform: uppercase; text-align: left; }
.ictu-audit-table td { height: 72px; padding: 8px 16px; vertical-align: middle; border-bottom: 1px solid var(--row-border); color: var(--text-secondary); font-size: 12.5px; line-height: 1.36; overflow-wrap: anywhere; }
.ictu-audit-table tbody tr:last-child td { border-bottom: 0; }
.ictu-audit-data-row:not([hidden]):hover { background: var(--row-hover); }
.ictu-audit-table tr[hidden] { display: none !important; }
.ictu-audit-activity { display: grid; grid-template-columns: 42px minmax(0,1fr); align-items: center; gap: 12px; min-width: 0; }
.ictu-audit-row-icon { display: inline-grid; place-items: center; width: 42px; height: 42px; border-radius: 50%; background: #e1edff; color: #1263d6; }
.ictu-audit-row-icon[data-tone="green"] { background: #def6eb; color: #008a61; }
.ictu-audit-row-icon[data-tone="amber"] { background: #fff0da; color: #b96a0b; }
.ictu-audit-row-icon[data-tone="red"] { background: #fde6e7; color: #bb454b; }
.ictu-audit-row-icon[data-tone="slate"] { background: #eaf0f7; color: #2e496d; }
.ictu-audit-cell-copy, .ictu-audit-time { display: grid; min-width: 0; gap: 3px; }
.ictu-audit-cell-copy > strong, .ictu-audit-time > strong { color: var(--heading); font-size: 12.5px; font-weight: 700; line-height: 1.3; }
.ictu-audit-cell-copy > span, .ictu-audit-time > span { display: block; min-width: 0; color: var(--text-muted); font-size: 11.75px; line-height: 1.3; }
.ictu-audit-subtitle { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.ictu-audit-type-badge { display: inline-flex; align-items: center; min-height: 23px; padding: 3px 10px; border-radius: 99px; background: #e0edff; color: #1b5695; font-size: 11.5px; font-weight: 700; white-space: normal; }
.ictu-audit-type-badge[data-type="APPROVAL"], .ictu-audit-type-badge[data-type="RELEASE_RETURN"] { background: #def6eb; color: #11684b; }
.ictu-audit-type-badge[data-type="BORROWING"], .ictu-audit-type-badge[data-type="LAUNDRY_GATEPASS"] { background: #fff0da; color: #996008; }
.ictu-audit-type-badge[data-type="ACCOUNTABILITY"] { background: #fde5e5; color: #a4373c; }
.ictu-audit-type-badge[data-type="SETTINGS"], .ictu-audit-type-badge[data-type="SECURITY_SYSTEM"] { background: #eee5ff; color: #6742a3; }
.ictu-audit-type-badge[data-type="OTHER"] { background: #e9eef5; color: #52657f; }
.ictu-audit-action-heading, .ictu-audit-action-cell { text-align: center !important; }
.ictu-audit-view-button { display: inline-flex; align-items: center; justify-content: center; gap: 9px; width: auto; min-width: 123px; min-height: 38px; padding: 7px 10px; border: 1px solid var(--interactive); border-radius: 8px; background: var(--surface-elevated); color: var(--interactive); font-size: 12.5px; font-weight: 700; white-space: nowrap; cursor: pointer; }
.ictu-audit-view-button:hover { background: var(--surface-hover); }
.ictu-audit-view-button[aria-expanded="true"] > .ui-icon { transform: rotate(180deg); }
.ictu-audit-expanded-row td { height: auto; padding: 14px 18px !important; background: var(--surface-subtle); }
.ictu-audit-details-panel { padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-elevated); }
.ictu-audit-detail-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px 20px; margin: 0; }
.ictu-audit-detail-grid > div { min-width: 0; }
.ictu-audit-detail-grid dt { margin-bottom: 3px; color: var(--text-muted); font-size: 10.5px; font-weight: 750; text-transform: uppercase; letter-spacing: .045em; }
.ictu-audit-detail-grid dd { margin: 0; font-size: 12.5px; overflow-wrap: anywhere; }
.ictu-audit-detail-grid code, .ictu-audit-details-panel pre { color: var(--text-secondary); }
.ictu-audit-detail-wide { grid-column: 1 / -1; }
.ictu-audit-change-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; margin-top: 14px; }
.ictu-audit-change-grid section { min-width: 0; }
.ictu-audit-change-grid h2 { margin: 0 0 7px; font-size: 11px; font-weight: 750; text-transform: uppercase; color: var(--text-secondary); }
.ictu-audit-change-grid pre { max-height: 280px; margin: 0; padding: 11px 13px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--surface-subtle); white-space: pre-wrap; word-break: break-word; font-size: 11.5px; line-height: 1.4; }
.ictu-audit-empty-directory td, .ictu-audit-no-results td { height: auto; padding: 30px !important; text-align: center; color: var(--text-muted); font-size: 13px; }
.ictu-audit-limit-note { margin: -4px 1px 0; color: var(--text-muted); font-size: 12px; }
html[data-theme="dark"] .ictu-audit-table th { background: var(--table-heading-bg); }
html[data-theme="dark"] .ictu-audit-row-icon { background: var(--surface-muted); color: var(--interactive); }
html[data-theme="dark"] .ictu-audit-type-badge { background: var(--surface-muted); color: var(--text-secondary); }
@media (min-width:1050px) and (max-width:1499px) {
    .ictu-audit-shell { --sidebar-width: 220px; }
    .ictu-audit-shell .sidebar-brand-row { padding-inline: 11px; }
    .ictu-audit-shell .sidebar-brand-logo-image { max-width: 194px; }
    .ictu-audit-shell .sidebar-nav { padding-inline: 9px; }
    .ictu-audit-shell .sidebar-nav a { padding-inline: 10px; gap: 10px; font-size: 13px; }
    .ictu-audit-shell .sidebar-nav a .nav-icon { width: 22px; min-width: 22px; }
    .ictu-audit-shell .sidebar-nav a .nav-icon svg { width: 20px; height: 20px; }
}
@media (max-width:1149px) {
    .ictu-audit-filter-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
@media (max-width:760px) {
    .ictu-audit-shell { --sidebar-width: 290px; --topbar-height: 66px; }
    .ictu-audit-shell .app-topbar { min-height: 66px; padding: 8px 14px; }
    .ictu-audit-shell .topbar-title strong { font-size: 17px; }
    .ictu-audit-shell .ictu-topbar-subtitle { font-size: 11px; }
    .app-main.ictu-audit-main { padding: 14px 12px 30px; }
    .ictu-audit-filter-grid { padding: 15px; gap: 13px; }
    .ictu-audit-table-toolbar { padding: 13px 15px; }
    .ictu-audit-pagination { width: 100%; justify-content: space-between; }
    .ictu-audit-detail-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
@media (max-width:540px) {
    .ictu-audit-filter-grid { grid-template-columns: minmax(0,1fr); }
    .ictu-audit-actor-actions { grid-template-columns: minmax(0,1fr) 102px; }
    .ictu-audit-shell .ictu-topbar-subtitle { display: none; }
    .ictu-audit-pagination { gap: 7px; }
    .ictu-audit-pagination > select { min-width: 126px; font-size: 12px; }
    .ictu-audit-page-divider { display: none; }
    .ictu-audit-detail-grid, .ictu-audit-change-grid { grid-template-columns: minmax(0,1fr); }
    .ictu-audit-detail-wide { grid-column: auto; }
}
@media (prefers-reduced-motion: reduce) {
    .ictu-audit-area * { transition: none !important; scroll-behavior: auto !important; }
}
</style>
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
@endsection
