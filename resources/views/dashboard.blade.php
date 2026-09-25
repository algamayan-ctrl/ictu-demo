@extends('layouts.app', ['title' => 'Dashboard'])
@section('content')

@if($dashboardMode === 'BORROWER')
    @include('dashboard.partials.borrower-styles')
@endif

<style>
    .dashboard-heading { margin-bottom:16px; }
    .dashboard-heading > div > p:last-child { max-width:860px; }

    /* One universal KPI language for Borrower, AO, Admin/Head, and ICTU.
       Summary cards are informational only: color communicates the metric,
       while navigation stays in explicit buttons, queues, and the sidebar. */
    .dashboard-stat-grid {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        width:100%;
    }
    .dashboard-kpi-card {
        --dashboard-card-accent:var(--info);
        --dashboard-icon-bg:var(--info-bg);
        --dashboard-icon-border:var(--info-border);
        position:relative;
        display:grid;
        grid-template-columns:42px minmax(0,1fr);
        grid-template-rows:auto auto auto;
        column-gap:12px;
        row-gap:2px;
        min-width:0;
        height:126px;
        min-height:126px;
        padding:17px 34px 15px 18px;
        overflow:hidden;
        border:1px solid var(--border);
        border-radius:12px;
        background:var(--surface-elevated);
        box-shadow:var(--shadow-sm);
        color:inherit;
        text-decoration:none;
        cursor:pointer;
        transition:transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
    }
    .dashboard-kpi-card::before {
        content:"";
        position:absolute;
        top:0;
        left:0;
        right:0;
        height:3px;
        background:var(--dashboard-card-accent);
        pointer-events:none;
    }
    /* Every clickable KPI card shares the same hover/focus affordance and
       arrow, regardless of role - the interaction never changes meaning. */
    .dashboard-kpi-card:hover {
        color:inherit;
        border-color:var(--border-strong);
        box-shadow:0 5px 14px rgba(7, 27, 53, .09);
        transform:translateY(-1px);
    }
    .dashboard-kpi-card:focus-visible {
        outline:none;
        box-shadow:var(--focus-ring);
    }
    .dashboard-kpi-card-arrow {
        position:absolute;
        top:16px;
        right:14px;
        color:var(--text-muted);
        opacity:.35;
        transition:opacity 160ms ease;
    }
    .dashboard-kpi-card:hover .dashboard-kpi-card-arrow,
    .dashboard-kpi-card:focus-visible .dashboard-kpi-card-arrow {
        opacity:.85;
    }
    @media (prefers-reduced-motion: reduce) {
        .dashboard-kpi-card { transition:none; }
        .dashboard-kpi-card:hover { transform:none; }
    }

    .dashboard-stat-grid > .dashboard-kpi-card {
        width:100%;
        max-width:none;
        align-self:stretch;
    }
        .dashboard-kpi-card.kpi-accent-info {
        --dashboard-card-accent:var(--info);
        --dashboard-icon-bg:var(--info-bg);
        --dashboard-icon-border:var(--info-border);
    }
    .dashboard-kpi-card.kpi-accent-warning {
        --dashboard-card-accent:var(--warning);
        --dashboard-icon-bg:var(--warning-bg);
        --dashboard-icon-border:var(--warning-border);
    }
    .dashboard-kpi-card.kpi-accent-danger {
        --dashboard-card-accent:var(--danger);
        --dashboard-icon-bg:var(--danger-bg);
        --dashboard-icon-border:var(--danger-border);
    }
    .dashboard-kpi-card.kpi-accent-success {
        --dashboard-card-accent:var(--success);
        --dashboard-icon-bg:var(--success-bg);
        --dashboard-icon-border:var(--success-border);
    }
    .dashboard-kpi-card.kpi-accent-neutral {
        --dashboard-card-accent:var(--text-soft);
        --dashboard-icon-bg:var(--surface-subtle);
        --dashboard-icon-border:var(--border);
    }
    .dashboard-kpi-card .kpi-icon {
        grid-column:1;
        grid-row:1 / span 3;
        display:grid;
        width:36px;
        height:36px;
        place-items:center;
        align-self:center;
        border:1px solid var(--dashboard-icon-border);
        border-radius:50%;
        background:var(--dashboard-icon-bg);
        color:var(--dashboard-card-accent);
    }
    .dashboard-kpi-card .kpi-label {
        grid-column:2;
        grid-row:1;
        align-self:end;
        color:var(--text-secondary);
        font-size:11px;
        font-weight:800;
        line-height:1.3;
        display:-webkit-box;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:2;
        overflow:hidden;
    }
    .dashboard-kpi-card .kpi-value {
        grid-column:2;
        grid-row:2;
        color:var(--heading);
        font-size:24px;
        font-weight:800;
        line-height:1.12;
        font-variant-numeric:tabular-nums;
    }
    .dashboard-kpi-card .kpi-note {
        grid-column:2;
        grid-row:3;
        color:var(--text-muted);
        font-size:10px;
        line-height:1.35;
        display:-webkit-box;
        -webkit-box-orient:vertical;
        -webkit-line-clamp:2;
        overflow:hidden;
    }

    /* Universal dashboard panels and rows. */
    .dashboard-balanced-grid {
        display:grid;
        gap:16px;
        align-items:stretch;
    }
    .dashboard-balanced-grid.dashboard-two-panel {
        grid-template-columns:repeat(2,minmax(0,1fr));
        /* Paired panels stay equal height (inherited align-items:stretch)
           so neither reads as oversized or squeezed next to the other; the
           shorter panel's own content stays packed at the top and any
           extra space is absorbed below it, not by distorting either card. */
    }
    .dashboard-balanced-grid.dashboard-single-panel,
    .dashboard-balanced-grid.borrower-actions-only {
        grid-template-columns:minmax(0,1fr);
    }
    .dashboard-balanced-grid > .queue-card,
    .dashboard-recent-card {
        min-width:0;
        height:auto;
        min-height:0;
        padding:0;
        overflow:hidden;
        border:1px solid var(--border);
        border-radius:12px;
        background:var(--surface-elevated);
        box-shadow:var(--shadow-sm);
    }

    /* Paired dashboard panels are one universal component: equal width and
       equal height. Each card's own list flexes to fill that shared height,
       so a shorter list stays packed at the top instead of stretching its
       rows apart, and a lone empty-state centers within the extra space. */
    .dashboard-balanced-grid.dashboard-two-panel > .queue-card {
        display:flex;
        flex-direction:column;
    }
    .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .queue-list,
    .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .dashboard-watch-list {
        flex:1 1 auto;
    }
    .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .queue-list {
        /* Without this, the grid's lone auto row stretches to fill the
           flexed height, which then centers a single row's content in
           the middle of the card via its own align-items:center. */
        align-content:start;
    }
    .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .queue-list:has(> .empty-state:only-child) {
        display:flex;
    }
    .dashboard-balanced-grid.dashboard-two-panel .empty-state {
        width:100%;
    }
    .dashboard-balanced-grid.dashboard-two-panel .queue-list > .empty-state:only-child {
        display:flex;
        align-items:center;
        justify-content:center;
        min-height:100%;
        text-align:center;
    }

    .dashboard-balanced-grid > .queue-card > .card-header,
    .dashboard-recent-card > .card-header {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        flex-wrap:wrap;
        gap:10px 18px;
        margin:0;
        padding:14px 16px;
        border-bottom:1px solid var(--border);
        background:transparent;
    }
    .dashboard-balanced-grid > .queue-card > .card-header h2,
    .dashboard-recent-card > .card-header h2 {
        margin:0;
        color:var(--heading);
        font-size:15px;
        font-weight:750;
        line-height:1.3;
    }
    .dashboard-balanced-grid > .queue-card > .card-header .eyebrow,
    .dashboard-recent-card > .card-header .eyebrow {
        margin-bottom:3px;
    }
    .dashboard-balanced-grid > .queue-card > .card-header .meta,
    .dashboard-recent-card > .card-header .meta,
    .dashboard-role-section-note {
        max-width:650px;
        margin:3px 0 0;
        color:var(--text-muted);
        font-size:11px;
        line-height:1.45;
    }
    .dashboard-view-all {
        display:inline-flex;
        align-items:center;
        gap:6px;
        flex:0 0 auto;
        min-height:30px;
        color:var(--interactive);
        font-size:12px;
        font-weight:750;
        text-decoration:none;
    }

    .dashboard-watch-list,
    .dashboard-recent-list { display:grid; }
    .dashboard-watch-row,
    .dashboard-recent-row,
    .dashboard-primary-queue-row {
        display:grid !important;
        grid-template-columns:38px minmax(0,1fr) max-content 78px !important;
        gap:12px !important;
        align-items:center !important;
        min-height:64px;
        padding:11px 16px !important;
        border-bottom:1px solid var(--row-border);
    }
    .dashboard-primary-queue-row {
        grid-template-columns:38px minmax(0,1fr) 78px !important;
    }
    .dashboard-watch-row:last-child,
    .dashboard-recent-row:last-child,
    .dashboard-primary-queue-row:last-child { border-bottom:0; }
    .dashboard-activity-icon {
        display:grid;
        width:40px;
        height:40px;
        place-items:center;
        justify-self:center;
        border:1px solid var(--info-border);
        border-radius:10px;
        background:var(--info-bg);
        color:var(--info);
    }
    .dashboard-watch-copy,
    .dashboard-recent-copy,
    .dashboard-primary-queue-row > div { min-width:0; }
    .dashboard-watch-copy strong,
    .dashboard-watch-copy span,
    .dashboard-watch-copy small,
    .dashboard-recent-copy strong,
    .dashboard-recent-copy span,
    .dashboard-recent-copy small,
    .dashboard-primary-queue-row > div strong,
    .dashboard-primary-queue-row > div span,
    .dashboard-primary-queue-row > div small { display:block; }
    .dashboard-watch-copy strong,
    .dashboard-recent-copy strong,
    .dashboard-primary-queue-row > div strong {
        color:var(--heading);
        font-size:12px;
        font-weight:750;
        line-height:1.35;
    }
    .dashboard-watch-copy span,
    .dashboard-recent-copy span,
    .dashboard-primary-queue-row > div span {
        margin-top:2px;
        color:var(--text-secondary);
        font-size:12.5px;
        line-height:1.4;
    }
    .dashboard-watch-copy small,
    .dashboard-recent-copy small,
    .dashboard-primary-queue-row > div small {
        margin-top:3px;
        color:var(--text-muted);
        font-size:11.5px;
        line-height:1.45;
    }
    .dashboard-watch-status,
    .dashboard-recent-status { justify-self:end; min-width:0; }
    .dashboard-watch-status .status-badge,
    .dashboard-recent-status .status-badge { white-space:nowrap; }
    .dashboard-watch-row > a.button,
    .dashboard-recent-row > a.button,
    .dashboard-primary-queue-row > a.button {
        justify-self:end;
        width:78px;
        min-width:78px;
        min-height:32px;
        padding:6px 10px;
        border-radius:7px;
        white-space:nowrap;
    }
    .dashboard-balanced-grid .empty-state {
        min-height:0;
        padding:16px 16px;
        text-align:left;
    }
    .dashboard-balanced-grid .empty-state strong,
    .dashboard-balanced-grid .empty-state span { display:inline; }
    .dashboard-recent-card { margin-top:0; }

    @media (max-width:1300px) {
        .dashboard-stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .dashboard-balanced-grid.dashboard-two-panel {
            grid-template-columns:minmax(0,1fr);
            grid-auto-rows:auto;
        }
        .dashboard-balanced-grid.dashboard-two-panel > .queue-card {
            height:auto;
            align-self:start;
        }
        .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .queue-list,
        .dashboard-balanced-grid.dashboard-two-panel > .queue-card > .dashboard-watch-list {
            flex:0 0 auto;
        }
        .dashboard-balanced-grid.dashboard-two-panel .queue-list > .empty-state:only-child {
            min-height:0;
            justify-content:flex-start;
            text-align:left;
        }
    }
    @media (max-width:680px) {
        .dashboard-stat-grid { grid-template-columns:1fr; }
        .dashboard-watch-row,
        .dashboard-recent-row,
        .dashboard-primary-queue-row {
            grid-template-columns:40px minmax(0,1fr) !important;
            gap:12px !important;
        }
        .dashboard-watch-status,
        .dashboard-recent-status,
        .dashboard-watch-row > a.button,
        .dashboard-recent-row > a.button,
        .dashboard-primary-queue-row > a.button {
            grid-column:2;
            justify-self:start;
        }
    }
    html[data-theme="dark"] .dashboard-kpi-card,
    html[data-theme="dark"] .dashboard-balanced-grid > .card,
    html[data-theme="dark"] .dashboard-recent-card { box-shadow:var(--shadow-sm); }
</style>

@php
    $firstName = str($user->full_name)->before(' ')->value();

    $copy = match($dashboardMode) {
        'BORROWER' => [
            'eyebrow' => 'Borrower Dashboard',
            'title' => 'Welcome, '.$firstName,
            'subtitle' => 'Track your requests, pickup and return schedules, and any pending actions.',
            'taskEyebrow' => 'Required Actions',
            'taskTitle' => 'Actions Requiring Your Attention',
            'taskDescription' => 'Only transactions requiring a direct borrower response are listed in this section.',
        ],
        'SPMU_OFFICER' => [
            'eyebrow' => 'SPMU Action Officer Dashboard',
            'title' => 'Welcome, '.$firstName,
            'subtitle' => 'Process requests pending verification and monitor release, return, and accountability actions.',
            'taskEyebrow' => 'Action Queue',
            'taskTitle' => 'Verification Queue',
            'taskDescription' => 'Requests awaiting SPMU verification.',
        ],
        'SPMU_HEAD' => [
            'eyebrow' => 'SPMU Head / Administrator Dashboard',
            'title' => 'Welcome, '.$firstName,
            'subtitle' => 'Review requests pending final approval and monitor custody, accountability, and inventory activity.',
            'taskEyebrow' => 'Action Queue',
            'taskTitle' => 'Priority Actions',
            'taskDescription' => 'Verified requests awaiting your final approval.',
        ],
        'ICTU' => [
            'eyebrow' => 'ICTU System Administrator Dashboard',
            'title' => 'Welcome, '.$firstName,
            'subtitle' => 'Administer user accounts, system configuration, audit records, delegations, and notification delivery exceptions.',
            'taskEyebrow' => 'System Administration',
            'taskTitle' => 'Recent Accounts',
            'taskDescription' => 'Recently added user accounts.',
        ],
        default => [
            'eyebrow' => 'Overview',
            'title' => 'Welcome, '.$firstName,
            'subtitle' => 'Open the functions assigned to your account.',
            'taskEyebrow' => 'Tasks',
            'taskTitle' => 'Current Activity',
            'taskDescription' => 'Records requiring action under the assigned role are listed in this section.',
        ],
    };

    /*
     * Canonical KPI color semantics (one tone per card, reused everywhere):
     * info/blue = normal active/scheduled workflow, no urgent action;
     * warning/amber = attention or action needed, not yet overdue/failed;
     * danger/red = overdue, failed, or genuinely critical only;
     * success/green = healthy/active-good state; neutral/gray = inactive.
     * Every entry here is a real, currently-emitted $statistics key - see
     * DashboardController for the exact query each one counts.
     */
    $statMeta = match($dashboardMode) {
        'BORROWER' => [
            'Active Borrowings' => ['custody', 'info', route('custody.index', ['kpi' => 'active_borrowings']), 'Released property currently on custody'],
            'Upcoming Pickup' => ['calendar', 'info', route('custody.index', ['kpi' => 'upcoming_pickup']), 'Approved transactions scheduled for pickup'],
            'Returns Due' => ['calendar', 'warning', route('custody.index', ['kpi' => 'returns_due']), 'Borrowings due for return'],
            'Active Obligations' => ['warning', 'warning', route('accountability.index'), 'Unresolved obligations'],
        ],
        'SPMU_OFFICER' => [
            'For Verification' => ['approval', 'info', route('verifications.index'), 'Awaiting verification'],
            'For Release' => ['custody', 'info', route('custody.release.index'), 'Awaiting release processing'],
            'For Return' => ['calendar', 'warning', route('custody.return.index'), 'Outstanding property to return'],
            'Accountability Cases' => ['accountability', 'warning', route('accountability.index'), 'Open accountability matters'],
        ],
        'SPMU_HEAD' => [
            'For Approval' => ['approval', 'info', route('approvals.index'), 'Awaiting final approval'],
            'Active Custodies' => ['custody', 'info', route('custody.index', ['kpi' => 'active_custodies']), 'Ongoing custody transactions'],
            'Open Accountability Cases' => ['accountability', 'warning', route('accountability.index'), 'Unresolved accountability matters'],
            'Active Restrictions' => ['warning', 'warning', route('accountability.index', ['view' => 'restrictions']).'#accountability-cases-section', 'Restrictions currently in effect'],
        ],
        'ICTU' => [
            'Active Accounts' => ['users', 'success', route('administration.users.index', ['status' => 'active']), 'User accounts currently enabled'],
            'Failed Notifications' => ['notifications', 'danger', route('reports.notifications', ['status' => 'failed']), 'Current delivery failures requiring technical review'],
            'Active Delegations' => ['delegation', 'info', route('administration.delegations.index', ['status' => 'active']), 'Temporary delegations currently in effect'],
            'Inactive Accounts' => ['users', 'neutral', route('administration.users.index', ['status' => 'inactive']), 'User accounts currently inactive'],
        ],
        default => [],
    };
@endphp

<div class="{{ $dashboardMode === 'BORROWER' ? 'is-borrower-dashboard' : '' }}">

<section class="page-heading dashboard-heading">
    <div>
        <p class="eyebrow">{{ $copy['eyebrow'] }}</p>
        <h1>{{ $copy['title'] }}</h1>
        <p>{{ $copy['subtitle'] }}</p>
    </div>

    @if($dashboardMode === 'BORROWER')
        <a class="button primary ui-pressable" href="{{ route('requests.create') }}">
            <x-icon name="plus" size="16" />
            Create Borrowing Request
        </a>
    @elseif($dashboardMode === 'SPMU_OFFICER')
        <a class="button primary ui-pressable" href="{{ route('verifications.index') }}"><span>Open Verification Queue</span><x-icon name="arrow-right" size="15" /></a>
    @elseif($dashboardMode === 'SPMU_HEAD')
        <a class="button primary ui-pressable" href="{{ route('approvals.index') }}"><span>Open Approval Queue</span><x-icon name="arrow-right" size="15" /></a>
    @elseif($dashboardMode === 'ICTU')
        <a class="button primary ui-pressable" href="{{ route('administration.users.index') }}"><span>Manage User Accounts</span><x-icon name="arrow-right" size="15" /></a>
    @endif
</section>

<section class="stat-grid dashboard-stat-grid" aria-label="Current totals">
    @foreach($statistics as $label => $value)
        @php
            [$icon, $tone, $link, $note] = $statMeta[$label] ?? [
                'dashboard',
                'neutral',
                route('dashboard'),
                'Current system total',
            ];
        @endphp
        {{--
            Each KPI card is a drill-down: its destination always shows
            exactly the record subset the card counted (see $statMeta's
            routes/query params and the matching controller filters), not an
            unfiltered list. Every role shares the same clickable treatment.
        --}}
        <a
            class="card stat-card kpi-card dashboard-kpi-card kpi-accent-{{ $tone }} ui-pressable"
            href="{{ $link }}"
            aria-label="{{ $label }}: {{ number_format($value) }}. {{ $note }}."
        >
            <span class="kpi-icon" aria-hidden="true"><x-icon :name="$icon" size="18" /></span>
            <span class="kpi-label">{{ $label }}</span>
            <strong class="kpi-value">{{ number_format($value) }}</strong>
            <span class="kpi-note">{{ $note }}</span>
            <x-icon name="arrow-right" size="14" class="dashboard-kpi-card-arrow" />
        </a>
    @endforeach
</section>

@php
    // No empty second column: when there is nothing to watch, the grid
    // stays single-panel rather than showing a contentless card.
    $showOperationalWatch = in_array($dashboardMode, ['SPMU_OFFICER', 'SPMU_HEAD'], true)
        && $nextCustodies->isNotEmpty();
@endphp
<section id="{{ $dashboardMode === 'BORROWER' ? 'borrower-actions' : 'dashboard-actions' }}" class="dashboard-grid dashboard-balanced-grid {{ $showOperationalWatch ? 'dashboard-two-panel' : 'dashboard-single-panel' }} {{ $dashboardMode === 'BORROWER' ? 'borrower-actions-only' : '' }}">
    <article class="card queue-card dashboard-panel-equal {{ $dashboardMode === 'BORROWER' ? 'borrower-dash-card' : '' }}">
        <div class="card-header">
            <div>
                @if($dashboardMode === 'BORROWER')
                    <h2>{{ $copy['taskTitle'] }}</h2>
                    <p class="meta">{{ $copy['taskDescription'] }}</p>
                @else
                    <p class="eyebrow">{{ $copy['taskEyebrow'] }}</p>
                    <h2>{{ $copy['taskTitle'] }}</h2>
                    <p class="meta dashboard-role-section-note">{{ $copy['taskDescription'] }}</p>
                @endif
            </div>

            @if($dashboardMode === 'BORROWER' && (($statistics['Active Obligations'] ?? 0) > 0))
                <a class="dashboard-view-all" href="{{ route('accountability.index') }}">My Obligations <x-icon name="arrow-right" size="16" /></a>
            @elseif($dashboardMode === 'SPMU_OFFICER')
                <a class="dashboard-view-all" href="{{ route('verifications.index') }}">View all <x-icon name="arrow-right" size="16" /></a>
            @elseif($dashboardMode === 'SPMU_HEAD')
                <a class="dashboard-view-all" href="{{ route('approvals.index') }}">View all <x-icon name="arrow-right" size="16" /></a>
            @endif
        </div>

        <div class="queue-list {{ $dashboardMode === 'BORROWER' ? 'borrower-next-list' : '' }}">
            @forelse($queue as $record)
                @if($dashboardMode === 'BORROWER')
                    @php
                        $custody = $record->custody;
                        $laundry = $custody?->laundryJob;
                        $pickupMissed = $custody?->released_at === null
                            && (
                                $custody?->pickup_expired_at !== null
                                || ($custody?->pickup_expires_at !== null && now()->gt($custody->pickup_expires_at))
                            );
                        $hasOutstandingProperty = $custody?->hasOutstandingProperty() ?? false;
                        $outstandingLines = $custody?->lines?->filter(
                            fn ($line) => (float) $line->returned_quantity < (float) $line->actual_released_quantity
                        ) ?? collect();
                        $hasOutstandingLinen = $outstandingLines->contains(
                            fn ($line) => (bool) $line->requestItem?->inventoryItem?->laundry_required
                        );
                        $hasOutstandingNonLinen = $outstandingLines->contains(
                            fn ($line) => ! (bool) $line->requestItem?->inventoryItem?->laundry_required
                        );
                        $hasAccountabilityMatter = $custody?->activeAccountabilityIndicator() !== null;

                        $nextAction = match(true) {
                            $record->status === App\Enums\RequestStatus::Draft => 'Complete the draft and submit the required signed documents to SPMU.',
                            $record->status === App\Enums\RequestStatus::ReturnedForRevision => 'Review the SPMU remarks, correct the request, and resubmit.',
                            $record->status === App\Enums\RequestStatus::UnderSpmu => 'No borrower action is required while the request remains under SPMU review.',
                            $custody?->status === 'CLOSED' && $laundry?->status === 'TURNED_OVER_TO_LAUNDRY' => 'No further borrower action is required. Linen turnover has been recorded and subsequent laundry processing is handled internally.',
                            $custody?->status === 'CLOSED' => 'Completed. No further borrower action is required.',
                            $pickupMissed => 'The pickup window was missed. Open the request to ask for a valid reschedule, when available, or cancel the unreleased request.',
                            $custody?->status === 'OVERDUE' && $hasOutstandingLinen && $hasOutstandingNonLinen => 'Return all outstanding non-linen property to SPMU and the overdue linen to the Laundry Area immediately. Any late-return assessment will continue in My Obligations after the physical returns are recorded.',
                            $custody?->status === 'OVERDUE' && $hasOutstandingLinen => 'Return the overdue linen and the same printed Laundry Form to the Laundry Area immediately. Laundry RECEIVED BY is the physical return date used for timeliness.',
                            $custody?->status === 'OVERDUE' && $hasOutstandingProperty => 'Return all outstanding property to SPMU immediately. Any late-return assessment will continue in My Obligations after the physical return is recorded.',
                            $laundry?->status === 'FOR_LAUNDRY' && $laundry->hasVerifiedAccomplishedForm() => 'No further borrower action is required for the linen. SPMU is verifying the accomplished Laundry Form received from the Laundry Area.',
                            $laundry?->status === 'FOR_LAUNDRY' => 'Return the linen and the same printed Laundry Form to the Laundry Area. Laundry Personnel will record the receipt and later deliver the accomplished form directly to SPMU; you are not responsible for that document handoff.',
                            $laundry?->status === 'TURNED_OVER_TO_LAUNDRY' => 'Linen turnover has been completed. Any remaining non-linen accountability matter continues under the applicable SPMU process.',
                            in_array($laundry?->status, ['IN_PROCESS', 'READY_FOR_SPMU_RETURN', 'AWAITING_FINAL_FORM_UPLOAD', 'FORM_REPLACEMENT_REQUIRED'], true) => 'No borrower action is required while SPMU completes the applicable laundry record processing.',
                            $hasAccountabilityMatter && $hasOutstandingProperty => 'Return any still-outstanding property and review My Obligations for the linked accountability requirement.',
                            $hasAccountabilityMatter => 'Review My Obligations and complete the required accountability, payment, repair, replacement, or compliance step shown there.',
                            $custody?->scheduled_release_at && ! $custody?->released_at => 'Claim the approved items within the confirmed pickup schedule.',
                            $custody?->released_at && $custody?->status !== 'CLOSED' => 'Return the issued property to SPMU on or before the recorded due date.',
                            default => 'Await the next official SPMU status update.',
                        };

                        $actionLabel = match (true) {
                            in_array($record->status, [App\Enums\RequestStatus::Draft, App\Enums\RequestStatus::ReturnedForRevision], true) => 'Continue',
                            $hasAccountabilityMatter => 'View Obligations',
                            $custody?->status === 'OVERDUE' => 'View Return',
                            default => 'View',
                        };
                        $actionHref = $hasAccountabilityMatter
                            ? route('accountability.index')
                            : ($custody?->status === 'OVERDUE'
                                ? route('custody.show', $custody)
                                : route('requests.show', $record));

                        /* Presentation only: an icon and a date drawn from the
                           same record state that already produced $nextAction. */
                        [$actionIcon, $actionTone] = match (true) {
                            $record->status === App\Enums\RequestStatus::Draft => ['edit', 'warning'],
                            $record->status === App\Enums\RequestStatus::ReturnedForRevision => ['warning', 'danger'],
                            $record->status === App\Enums\RequestStatus::UnderSpmu => ['clock', 'info'],
                            $custody?->status === 'CLOSED' => ['check-circle', 'success'],
                            $pickupMissed => ['clock', 'warning'],
                            $laundry !== null => ['cycle', 'info'],
                            $custody?->scheduled_release_at && ! $custody?->released_at => ['calendar', 'info'],
                            $custody?->released_at => ['custody', 'warning'],
                            default => ['information', 'info'],
                        };

                        $actionWhen = match (true) {
                            $custody?->scheduled_release_at !== null && $custody?->released_at === null
                                => $custody->scheduled_release_at->format('d M Y, g:i A'),
                            $custody?->released_at !== null && $custody?->due_at !== null
                                => $custody->due_at->format('d M Y'),
                            $record->currentVersion?->return_date !== null
                                => $record->currentVersion->return_date->format('d M Y'),
                            default => null,
                        };
                    @endphp
                    <article class="borrower-next-row">
                        <span class="borrower-next-icon tone-{{ $actionTone }}" aria-hidden="true">
                            <x-icon :name="$actionIcon" size="17" />
                        </span>

                        <div class="borrower-next-copy">
                            <strong>{{ $record->request_no }}</strong>
                            <span>{{ $record->currentVersion?->purpose_event ?: 'Borrowing request' }}</span>
                            <small>{{ $nextAction }}</small>
                        </div>

                        @if($actionWhen)
                            <span class="borrower-next-when">{{ $actionWhen }}</span>
                        @else
                            <span></span>
                        @endif

                        {{--
                            View Obligations must reuse the exact same
                            action pattern as View Request/View Record
                            elsewhere - the bespoke borrower-active-action
                            skin is skipped for it so nothing here can
                            diverge from that universal look, and the
                            button sizes to its own label instead of a
                            fixed width that a longer label can overflow.
                        --}}
                        <a class="button secondary small ui-pressable {{ $hasAccountabilityMatter ? '' : 'borrower-active-action' }}" href="{{ $actionHref }}"><span>{{ $actionLabel }}</span><x-icon name="arrow-right" size="14" /></a>
                    </article>
                @elseif($dashboardMode === 'SPMU_OFFICER')
                    <article class="dashboard-primary-queue-row">
                        <span class="dashboard-activity-icon" aria-hidden="true"><x-icon name="approval" size="17" /></span>
                        <div>
                            <strong>{{ $record->request_no }}</strong>
                            <span>{{ $record->borrower?->full_name }}</span>
                            <small>Verify the request and required documents, or return it for revision.</small>
                        </div>
                        <a class="button primary small ui-pressable" href="{{ route('requests.show', $record) }}"><span>Verify</span><x-icon name="arrow-right" size="14" /></a>
                    </article>
                @elseif($dashboardMode === 'SPMU_HEAD')
                    <article class="dashboard-primary-queue-row">
                        <span class="dashboard-activity-icon" aria-hidden="true"><x-icon name="approval" size="17" /></span>
                        <div>
                            <strong>{{ $record->request_no }}</strong>
                            <span>{{ $record->borrower?->full_name }}</span>
                            <small>Review the verified request, approved quantities, borrowing period, and availability before the final decision.</small>
                        </div>
                        <a class="button primary small ui-pressable" href="{{ route('requests.show', $record) }}"><span>Review</span><x-icon name="arrow-right" size="14" /></a>
                    </article>
                @elseif($dashboardMode === 'ICTU')
                    <article class="dashboard-primary-queue-row">
                        <span class="dashboard-activity-icon" aria-hidden="true"><x-icon name="users" size="17" /></span>
                        <div>
                            <strong>{{ $record->full_name }}</strong>
                            <span>{{ $record->access_classification?->label() ?: 'User account' }}</span>
                            <small>{{ $record->email }}{{ $record->organizationalUnit ? ' · '.$record->organizationalUnit->unit_name : '' }}</small>
                        </div>
                        <a class="button secondary small ui-pressable" href="{{ route('administration.users.edit', $record) }}"><span>Manage</span><x-icon name="arrow-right" size="14" /></a>
                    </article>
                @endif
            @empty
                @if($dashboardMode === 'BORROWER')
                    @if($borrowerRestrictionActions->isEmpty())
                        @if(($statistics['Active Obligations'] ?? 0) > 0)
                            <x-empty-state
                                icon="check-circle"
                                title="No action is required from you right now."
                                message="Your active obligation is currently being processed. Open My Obligations to monitor its status."
                            />
                        @else
                            <x-empty-state
                                icon="check-circle"
                                title="No current action is required."
                                message="No borrowing transaction or obligation currently requires your response."
                            />
                        @endif
                    @endif
                @elseif($dashboardMode === 'SPMU_OFFICER')
                    <x-empty-state icon="check-circle" title="No requests awaiting verification." message="You are all caught up." />
                @elseif($dashboardMode === 'SPMU_HEAD')
                    <x-empty-state icon="check-circle" title="No requests awaiting approval." message="You are all caught up." />
                @elseif($dashboardMode === 'ICTU')
                    <x-empty-state icon="users" title="No recent account activity." />
                @else
                    <x-empty-state
                        icon="box"
                        title="No records currently require action."
                        message="Records requiring action under your assigned role will appear here automatically."
                    />
                @endif
            @endforelse

            @if($dashboardMode === 'BORROWER')
                @foreach($borrowerRestrictionActions as $obligationRow)
                    <article class="borrower-next-row">
                        <span class="borrower-next-icon tone-{{ $obligationRow['tone'] ?? 'warning' }}" aria-hidden="true">
                            <x-icon :name="$obligationRow['icon'] ?? 'warning'" size="17" />
                        </span>

                        <div class="borrower-next-copy">
                            <strong>{{ $obligationRow['type'] }}</strong>
                            <span>{{ $obligationRow['summary'] }}</span>
                            <small>{{ $obligationRow['next_action'] }}</small>
                        </div>

                        <span class="borrower-next-when">{{ $obligationRow['badge'] }}</span>

                        <a class="button secondary small ui-pressable" href="{{ route('accountability.index') }}"><span>View Obligation</span><x-icon name="arrow-right" size="14" /></a>
                    </article>
                @endforeach
            @endif
        </div>
    </article>

    @if($showOperationalWatch)
        <article class="card queue-card dashboard-panel-equal dashboard-watch-card">
            <div class="card-header">
                <div>
                    <p class="eyebrow">{{ $dashboardMode === 'SPMU_OFFICER' ? 'Operations' : 'Custody Oversight' }}</p>
                    <h2>{{ $dashboardMode === 'SPMU_OFFICER' ? 'Upcoming Operations' : 'Custody Watch' }}</h2>
                    <p class="meta dashboard-role-section-note">
                        {{ $dashboardMode === 'SPMU_OFFICER'
                            ? 'Next pickup and return deadlines.'
                            : 'Nearest due/overdue active custody transactions.' }}
                    </p>
                </div>
                <a class="dashboard-view-all" href="{{ route('custody.index') }}">View all <x-icon name="arrow-right" size="16" /></a>
            </div>

            <div class="dashboard-watch-list">
                @foreach($nextCustodies as $watchCustody)
                    @php
                        $watchWorkflow = $watchCustody->workflowStatus();
                        $watchDateLabel = null;
                        $watchDateValue = null;

                        if ($watchCustody->released_at && $watchCustody->due_at) {
                            $watchDateLabel = 'Return due';
                            $watchDateValue = $watchCustody->due_at->format('d M Y');
                        } elseif ($watchCustody->scheduled_release_at && ! $watchCustody->released_at) {
                            $watchDateLabel = 'Pickup';
                            $watchDateValue = $watchCustody->scheduled_release_at->format('d M Y, g:i A');
                        }
                    @endphp
                    <div class="dashboard-watch-row">
                        <span class="dashboard-activity-icon" aria-hidden="true"><x-icon name="custody" size="17" /></span>
                        <div class="dashboard-watch-copy">
                            <strong>{{ $watchCustody->custody_no }}</strong>
                            <span>{{ $watchCustody->borrower?->full_name ?: 'Borrower' }}</span>
                            <small>{{ $watchDateLabel && $watchDateValue ? $watchDateLabel.': '.$watchDateValue : 'Active transaction' }}</small>
                        </div>
                        <div class="dashboard-watch-status">
                            @if($watchWorkflow)
                                <x-status-badge :status="$watchWorkflow['key']" :label="$watchWorkflow['label']" />
                            @endif
                        </div>
                        <a class="button secondary small ui-pressable" href="{{ route('custody.show', $watchCustody) }}"><span>View</span><x-icon name="arrow-right" size="14" /></a>
                    </div>
                @endforeach
            </div>
        </article>
    @endif
</section>

@if($dashboardMode === 'BORROWER')
    <article class="borrower-dash-card" aria-labelledby="active-requests-title">
        <div class="card-header">
            <div>
                <h2 id="active-requests-title">Current Borrowing Requests</h2>
                <p class="meta">Active requests that do not need your response right now are shown here. Open My Requests for the full status and history.</p>
            </div>
            <a class="dashboard-view-all" href="{{ route('requests.index') }}">View all <x-icon name="arrow-right" size="16" /></a>
        </div>

        @if($activeRequestBars->isEmpty())
            <x-empty-state icon="requests" title="No current borrowing requests" message="Active requests will appear here once submitted." />
        @else
            <div class="borrower-active-scroll">
                <table class="borrower-active-table">
                    <thead>
                        <tr>
                            <th scope="col">Request ID</th>
                            <th scope="col">Event / Charter</th>
                            <th scope="col">Status</th>
                            <th scope="col">Schedule / Due Date</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                @foreach($activeRequestBars as $activeRequest)
                    @php
                        $activeCustody = $activeRequest->custody;
                        $activeCustodyStatus = strtoupper((string) ($activeCustody?->status ?? ''));
                        $activeAccountability = $activeCustody?->activeAccountabilityIndicator();
                        $activeWorkflow = $activeCustody?->workflowStatus();
                        $activeGatePassOnlyPending = $activeCustody?->isGatePassOnlyPending() ?? false;

                        /*
                         * Same key/label resolution as My Requests
                         * (requests/partials/my-requests-row.blade.php), so a
                         * request in a given state never reads differently on
                         * the two pages. SPMU still recording the accomplished
                         * Gate Pass is internal SPMU processing, not a
                         * borrower obligation - never "Accountability Pending".
                         */
                        if ($activeGatePassOnlyPending) {
                            [$activeStateKey, $activeStateLabel] = ['GATE_PASS_PROCESSING', 'Gate Pass Processing'];
                        } elseif ($activeWorkflow) {
                            [$activeStateKey, $activeStateLabel] = match ($activeWorkflow['key']) {
                                'BORROWED' => ['BORROWED', 'Released / On Custody'],
                                'RETURN_PROCESSING' => ['RETURN_PROCESSING', 'Return Processing'],
                                'OVERDUE' => ['OVERDUE', 'Overdue'],
                                'INCIDENT_OPEN' => ['ACCOUNTABILITY_PENDING', 'Accountability Pending'],
                                'OBLIGATION_OPEN' => ['OBLIGATION_OPEN', 'Accountability Pending'],
                                'COMPLETED' => ['COMPLETED', 'Completed'],
                                'BORROWER_CLEARED' => ['BORROWER_CLEARED', 'Borrower Cleared'],
                                'CANCELLED' => ['CANCELLED', 'Cancelled'],
                                'READY_FOR_RELEASE' => ['READY_FOR_RELEASE', 'Ready for Release'],
                                'PICKUP_SCHEDULED' => ['PICKUP_SCHEDULED', 'Pickup Scheduled'],
                                'ITEM_PREPARATION' => ['ITEM_PREPARATION', 'For Item Preparation'],
                                'PICKUP_SCHEDULING' => ['PICKUP_SCHEDULING', 'For Pickup Scheduling'],
                                'PICKUP_EXPIRED' => ['PICKUP_EXPIRED', 'Pickup Missed'],
                                default => [$activeWorkflow['key'], $activeWorkflow['label']],
                            };
                        } else {
                            [$activeStateKey, $activeStateLabel] = match ($activeRequest->status) {
                                App\Enums\RequestStatus::Draft => ['DRAFT', 'Draft'],
                                App\Enums\RequestStatus::ReturnedForRevision => ['RETURNED_FOR_REVISION', 'Returned for Revision'],
                                App\Enums\RequestStatus::UnderSpmu,
                                App\Enums\RequestStatus::UnderGsu,
                                App\Enums\RequestStatus::UnderVpaf => ['UNDER_SPMU', 'Under SPMU Review'],
                                App\Enums\RequestStatus::ApprovedReadyForRelease => ['APPROVED_READY_FOR_RELEASE', 'Ready for Release'],
                                App\Enums\RequestStatus::FinalApprovedAwaitingDownload => ['FINAL_APPROVED_AWAITING_DOWNLOAD', 'Approved'],
                                App\Enums\RequestStatus::Rejected => ['REJECTED', 'Rejected'],
                                App\Enums\RequestStatus::Cancelled => ['CANCELLED', 'Cancelled'],
                                App\Enums\RequestStatus::Expired => ['INACTIVE', 'Inactive'],
                                default => ['SUBMITTED', 'In Progress'],
                            };
                        }

                        $activeOriginalReturnDate = $activeCustody?->original_due_at
                            ?: $activeRequest->currentVersion?->return_date;
                        $activeEffectiveReturnDate = $activeCustody?->due_at ?: $activeOriginalReturnDate;
                        $activeReturnAdjusted = $activeOriginalReturnDate && $activeEffectiveReturnDate
                            && ! $activeOriginalReturnDate->isSameDay($activeEffectiveReturnDate);
                        $activeDateNote = null;

                        [$activeDateLabel, $activeDateValue] = match (true) {
                            $activeCustody?->released_at !== null && $activeEffectiveReturnDate !== null => [
                                $activeReturnAdjusted ? 'Effective Return' : 'Return due',
                                $activeEffectiveReturnDate->format('d M Y'),
                            ],
                            $activeCustody?->scheduled_release_at !== null && $activeCustody?->released_at === null => ['Pickup', $activeCustody->scheduled_release_at->format('d M Y, g:i A')],
                            $activeRequest->currentVersion?->return_date !== null => ['Expected return', $activeRequest->currentVersion->return_date->format('d M Y')],
                            $activeRequest->currentVersion?->needed_from !== null => ['Needed from', $activeRequest->currentVersion->needed_from->format('d M Y')],
                            default => ['Updated', $activeRequest->updated_at->format('d M Y')],
                        };

                        if ($activeReturnAdjusted && $activeOriginalReturnDate) {
                            $activeDateNote = 'Adjusted from '.$activeOriginalReturnDate->format('d M Y');
                        }

                        $activeActionLabel = in_array($activeRequest->status, [App\Enums\RequestStatus::Draft, App\Enums\RequestStatus::ReturnedForRevision], true)
                            ? 'Continue'
                            : 'View';
                    @endphp
                    <tr>
                        <td class="borrower-active-cell-id">
                            <span class="borrower-active-id">{{ $activeRequest->request_no }}</span>
                        </td>

                        <td data-label="Event / Charter">
                            <span class="borrower-active-purpose">{{ $activeRequest->currentVersion?->purpose_event ?: 'Borrowing request' }}</span>
                        </td>

                        <td data-label="Status">
                            <x-status-badge :status="$activeStateKey" :label="$activeStateLabel" />
                            @if($activeAccountability)
                                <small class="borrower-active-obligation">{{ $activeAccountability['label'] }}</small>
                            @endif
                        </td>

                        <td class="borrower-active-schedule" data-label="Schedule / Due Date">
                            <strong>{{ $activeDateValue }}</strong>
                            <small>{{ $activeDateLabel }}</small>
                            @if($activeDateNote)
                                <small class="borrower-active-adjusted-date">{{ $activeDateNote }}</small>
                            @endif
                        </td>

                        <td data-label="Action">
                            <a
                                class="button secondary small ui-pressable borrower-active-action"
                                href="{{ route('requests.show', $activeRequest) }}"
                            >
                                <span>{{ $activeActionLabel }}</span><x-icon name="arrow-right" size="14" />
                            </a>
                        </td>
                    </tr>
                @endforeach
                    </tbody>
                </table>
            </div>

            @if($activeRequestTotal > $activeRequestBars->count())
                <p class="borrower-active-overflow">
                    {{ $activeRequestTotal - $activeRequestBars->count() }} more ongoing {{ ($activeRequestTotal - $activeRequestBars->count()) === 1 ? 'request is' : 'requests are' }} available in My Requests.
                </p>
            @endif
        @endif
    </article>
@endif

@if($dashboardMode === 'BORROWER')
    <article class="borrower-dash-card dashboard-recent-card" aria-labelledby="recent-borrowing-title">
        <div class="card-header">
            <div>
                <h2 id="recent-borrowing-title">Recent Borrowing Activity</h2>
                <p class="meta">Your latest completed or closed borrowing activity. Open My Requests for the full history.</p>
            </div>
            <a class="dashboard-view-all" href="{{ route('requests.index') }}">View all <x-icon name="arrow-right" size="16" /></a>
        </div>

        @if($recentBorrowerActivity->isEmpty())
            <x-empty-state icon="clock" title="No recent borrowing activity" message="Completed or closed borrowing activity will appear here." />
        @else
            <div class="dashboard-recent-list">
                @foreach($recentBorrowerActivity as $recentRequest)
                    @php
                        $recentWorkflow = $recentRequest->custody?->workflowStatus();
                        $recentGatePassOnlyPending = $recentRequest->custody?->isGatePassOnlyPending() ?? false;
                        $recentStatusKey = $recentGatePassOnlyPending ? 'GATE_PASS_PROCESSING' : ($recentWorkflow['key'] ?? $recentRequest->status->value);
                        $recentStatusLabel = $recentGatePassOnlyPending ? 'Gate Pass Processing' : ($recentWorkflow['label'] ?? $recentRequest->status->label());
                    @endphp
                    <div class="dashboard-recent-row">
                        <span class="dashboard-activity-icon" aria-hidden="true"><x-icon name="requests" size="17" /></span>
                        <div class="dashboard-recent-copy">
                            <strong>{{ $recentRequest->request_no }}</strong>
                            <span>{{ $recentRequest->currentVersion?->purpose_event ?: 'Borrowing request' }}</span>
                            <small>Updated {{ $recentRequest->updated_at->format('d M Y, g:i A') }}</small>
                        </div>
                        <div class="dashboard-recent-status">
                            <x-status-badge :status="$recentStatusKey" :label="$recentStatusLabel" />
                        </div>
                        <a class="button secondary small ui-pressable borrower-active-action" href="{{ route('requests.show', $recentRequest) }}"><span>View</span><x-icon name="arrow-right" size="14" /></a>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
@endif

</div>
@endsection
