@php
    // Shared by the borrower form and the borrower/SPMU dashboards.
    $operationalAdvisory = app(\App\Services\OperationalAdvisoryService::class)->snapshot();
    $operationalAdvisoryCompact = $compact ?? false;
@endphp

@once
<style>
    .spmu-advisory {
        --advisory-accent: var(--info, #1976d2);
        --advisory-bg: var(--surface-elevated, #fff);
        display: flex;
        align-items: flex-start;
        gap: 11px;
        min-width: 0;
        padding: 13px 16px;
        border: 1px solid var(--border, #d9e2ec);
        border-left: 3px solid var(--advisory-accent);
        border-radius: 10px;
        background: var(--advisory-bg);
        box-shadow: var(--shadow-sm, 0 2px 6px rgba(16,42,67,.04));
    }
    .spmu-advisory[data-tone="closed"] { --advisory-accent: var(--warning, #c48a1e); }
    .spmu-advisory[data-tone="special"] { --advisory-accent: var(--success, #228052); }
    .spmu-advisory__icon {
        flex: 0 0 31px;
        display: grid;
        place-items: center;
        height: 31px;
        border-radius: 8px;
        background: var(--surface-subtle, #f4f7fa);
        color: var(--advisory-accent);
    }
    .spmu-advisory__body { min-width: 0; flex: 1 1 auto; }
    .spmu-advisory__heading { display:flex; flex-wrap:wrap; gap:6px 10px; align-items:center; }
    .spmu-advisory__heading strong { color:var(--heading, #163047); font-size:13px; line-height:1.4; }
    .spmu-advisory__badge {
        border-radius: 999px;
        border: 1px solid var(--border, #dce5ee);
        padding: 2px 8px;
        font-size: 10px;
        font-weight: 700;
        line-height: 1.4;
        color: var(--advisory-accent);
    }
    .spmu-advisory__main { margin:4px 0 0; color:var(--text, #24394c); font-size:12px; line-height:1.5; }
    .spmu-advisory__meta { display:flex; gap:4px 14px; flex-wrap:wrap; margin-top:5px; font-size:11px; line-height:1.45; color:var(--text-muted, #68798a); }
    .spmu-advisory__meta [hidden] { display:none !important; }
    .spmu-advisory__meta span { min-width:0; overflow-wrap:anywhere; }
    .spmu-advisory__link { align-self:center; flex:0 0 auto; font-size:11px; font-weight:700; color:var(--info, #1976d2); text-decoration:none; white-space:nowrap; }
    .spmu-advisory__link:hover,.spmu-advisory__link:focus-visible { text-decoration:underline; }
    .dashboard-page-shell > .spmu-advisory { margin-bottom: 12px; }
    .spmu-advisory--compact { padding:11px 14px; }
    @media (max-width:620px) {
        .spmu-advisory { flex-wrap:wrap; }
        .spmu-advisory__link { margin-left:42px; }
    }
</style>
@endonce

<section
    class="spmu-advisory {{ $operationalAdvisoryCompact ? 'spmu-advisory--compact' : '' }}"
    data-spmu-operational-advisory
    data-tone="{{ $operationalAdvisory['tone'] }}"
    data-refresh-url="{{ route('operational-advisory.status') }}"
    aria-label="SPMU Transaction Advisory"
>
    <span class="spmu-advisory__icon" aria-hidden="true"><x-icon name="calendar-clock" size="17" /></span>
    <div class="spmu-advisory__body">
        <div class="spmu-advisory__heading">
            <strong>SPMU Transaction Advisory</strong>
            <span class="spmu-advisory__badge" data-advisory-badge>{{ $operationalAdvisory['badge'] }}</span>
        </div>
        <p class="spmu-advisory__main" data-advisory-message>{{ $operationalAdvisory['message'] }}</p>
        {{-- Each line appears only when the Operational Calendar has something to say. --}}
        <div class="spmu-advisory__meta">
            <span data-advisory-next @if(!$operationalAdvisory['next_available']) hidden @endif>Next available: {{ $operationalAdvisory['next_available'] }}</span>
            <span data-advisory-hours @if(!$operationalAdvisory['hours']) hidden @endif>Hours: {{ $operationalAdvisory['hours'] }}</span>
            <span data-advisory-reason @if(!$operationalAdvisory['reason']) hidden @endif>{{ $operationalAdvisory['reason'] }}</span>
            <span data-advisory-upcoming @if(!$operationalAdvisory['upcoming']) hidden @endif>Upcoming: {{ $operationalAdvisory['upcoming'] }}</span>
        </div>
    </div>
    {{-- Every page that shows the advisory belongs to a workspace that can open the Borrowing Calendar. --}}
    @if(in_array(strtoupper((string) session('active_workspace')), ['BORROWER', 'SPMU'], true))
        <a class="spmu-advisory__link" href="{{ route('calendar.index') }}">View Calendar →</a>
    @endif
</section>

@once
<script>
(function () {
    'use strict';
    const refreshIntervalMs = 120000;
    const cards = document.querySelectorAll('[data-spmu-operational-advisory]');
    if (!cards.length) return;

    let busy = false;
    const change = (root, selector, value, prefix = '') => {
        const el = root.querySelector(selector);
        if (!el) return;
        const present = typeof value === 'string' && value.trim() !== '';
        el.hidden = !present;
        el.textContent = present ? prefix + value : '';
    };

    const refresh = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch(cards[0].dataset.refreshUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) return;
            const data = await response.json();
            if (!['normal', 'closed', 'special'].includes(data.tone)) return;
            cards.forEach(card => {
                card.dataset.tone = data.tone;
                change(card, '[data-advisory-badge]', data.badge);
                change(card, '[data-advisory-message]', data.message);
                change(card, '[data-advisory-next]', data.next_available, 'Next available: ');
                change(card, '[data-advisory-hours]', data.hours, 'Hours: ');
                change(card, '[data-advisory-reason]', data.reason);
                change(card, '[data-advisory-upcoming]', data.upcoming, 'Upcoming: ');
            });
        } catch (_) {
            // Keep the last server-rendered advisory if connectivity is lost.
        } finally {
            busy = false;
        }
    };
    setInterval(refresh, refreshIntervalMs);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
</script>
@endonce
