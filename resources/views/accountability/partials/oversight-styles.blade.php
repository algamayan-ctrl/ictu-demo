{{--
    Accountability Oversight presentation layer.

    Styling only. Every rule here is scoped to the accountability page's own
    classes so no other module inherits it, and every colour goes through a
    semantic token so the dark theme stays correct.
--}}
<style>

.accountability-page-heading { align-items: flex-start; margin-bottom: 12px; }
.accountability-page-heading h1 { margin: 2px 0 4px; font-size: clamp(20px, 1.6vw, 24px); }
.accountability-page-heading p:not(.eyebrow) { max-width: 680px; font-size: 12px; line-height: 1.5; }

.accountability-section-heading { align-items: flex-start; margin-bottom: 12px; }
.accountability-section-heading > div { min-width: 0; }
.accountability-section-heading h2 { margin: 0 0 3px; font-size: 15.5px; }
.accountability-section-heading p:not(.eyebrow) { max-width: 720px; margin: 0; color: var(--text-muted); font-size: 11.5px; line-height: 1.5; }
.accountability-section-heading .eyebrow { margin-bottom: 3px; }
.accountability-section-heading .status-badge { flex: 0 0 auto; }

.accountability-case-card { padding: 12px 12px; }
.accountability-case-card > .card-header { margin-bottom: 0; gap: 12px; }
.accountability-case-card > .card-header h3 { margin: 2px 0 0; font-size: 14px; }
.accountability-case-card > .card-header strong { color: var(--interactive); font-size: 10.5px; font-weight: 800; letter-spacing: .04em; }

.accountability-case-facts {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1px;
    margin: 12px 0 0;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--border);
}

.accountability-case-facts > div { min-width: 0; padding: 9px 11px; background: var(--surface); }
.accountability-case-facts dt { margin: 0 0 3px; color: var(--text-muted); font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.accountability-case-facts dd { margin: 0; color: var(--heading); font-size: 12.5px; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
.accountability-case-facts dd small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 10px; font-weight: 600; }

.accountability-alert { margin-top: 12px; padding: 9px 12px; font-size: 11.5px; line-height: 1.5; }
.accountability-alert strong { display: block; color: inherit; font-size: 12px; }
.accountability-alert p { margin: 2px 0 0; color: var(--text-secondary); }

.accountability-case-meta { margin: 10px 0 0; color: var(--text-muted); font-size: 10.5px; line-height: 1.5; }

.accountability-case-state {
    display: inline-flex;
    width: max-content;
    max-width: 100%;
    align-items: center;
    gap: 7px;
    margin-top: 12px;
    padding: 5px 11px;
    border: 1px dashed var(--border-strong);
    border-radius: 999px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
}

.accountability-case-state::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--text-soft);
}

.accountability-empty {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 3px 10px;
    min-height: 0;
    padding: 12px 12px;
    line-height: 1.45;
}

.accountability-empty strong { color: var(--heading); font-size: 12.5px; }
.accountability-empty span { color: var(--text-muted); font-size: 11.5px; line-height: 1.5; }

.accountability-list-card { padding: 12px 12px; }
.accountability-list-card > .card-header { margin-bottom: 12px; }
.accountability-list-card .head-case-summary { margin-top: 12px; }
.accountability-list-card .head-case-summary > div { padding: 9px 11px; }
.accountability-list-card .head-case-summary dt { font-size: 9.5px; }
.accountability-list-card .head-case-summary dd { color: var(--heading); font-size: 12.5px; }

.accountability-list-card .billing-lines { margin: 12px 0 0; }
.accountability-list-card .billing-lines p { padding: 7px 0; font-size: 12px; }
.accountability-list-card .table-wrap table { min-width: 620px; }
.accountability-list-card .table-wrap td { padding: 8px 12px; font-size: 12px; }

.accountability-table-card { padding: 12px 12px; }
.accountability-table-card > .card-header { margin-bottom: 12px; }
.accountability-table-card td { padding: 8px 13px; font-size: 12px; }

@media (max-width: 900px) {
    .accountability-case-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 620px) {
    .accountability-case-facts { grid-template-columns: minmax(0, 1fr); }
    .accountability-section-heading { flex-direction: column; gap: 8px; }
}

.accountability-asof {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 6px;
    margin: 4px 0 0;
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}

.accountability-asof .ui-icon { flex: 0 0 auto; color: var(--text-soft); }

.kpi-accent-restriction {
    --kpi-accent: #6d4bb4;
    --kpi-icon-bg: #f0ebfb;
    --kpi-icon-border: #d9cdf3;
}

:root[data-theme="dark"] .kpi-accent-restriction,
html[data-theme="dark"] .kpi-accent-restriction {
    --kpi-accent: #b9a3e8;
    --kpi-icon-bg: #211a33;
    --kpi-icon-border: #3b2f57;
}

.accountability-cases-card { padding: 0; overflow: hidden; }

.accountability-cases-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 12px;
    border-bottom: 1px solid var(--border);
}

.accountability-cases-head h2 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    font-size: 15.5px;
}

.accountability-count-chip {
    min-width: 22px;
    padding: 2px 8px;
    border-radius: 999px;
    color: var(--info);
    background: var(--info-bg);
    font-size: 11.5px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    text-align: center;
}

.accountability-case-workspace {
    display: grid;
    gap: 12px;
}

.accountability-browser-toolbar {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 10px;
    padding: 10px 12px 11px;
    border-bottom: 1px solid var(--border);
    background: var(--surface-subtle);
}

.accountability-browser-toolbar label {
    display: grid;
    gap: 5px;
    margin: 0;
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 800;
}

.accountability-browser-search {
    flex: 1 1 280px;
    max-width: 440px;
}

.accountability-browser-filter {
    flex: 0 1 180px;
    min-width: 148px;
}

.accountability-browser-toolbar input,
.accountability-browser-toolbar select {
    width: 100%;
    min-height: 36px;
    margin: 0;
    font-size: 12px;
}

.accountability-browser-toolbar .search-input-shell { width: 100%; }

.accountability-cases-table table { min-width: 800px; }
.accountability-cases-table th { padding-top: 9px; padding-bottom: 9px; white-space: nowrap; }
.accountability-cases-table td { padding-top: 10px; padding-bottom: 10px; line-height: 1.35; }
.accountability-cases-table .is-numeric { text-align: right; font-variant-numeric: tabular-nums; }

.accountability-case-row > td { vertical-align: middle; }
.accountability-case-row > td:first-child { min-width: 188px; }

.accountability-case-ref {
    display: block;
    color: var(--heading);
    font-size: 12.5px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.accountability-case-borrower {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 11px;
}

.accountability-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.accountability-status-pill::before {
    content: '';
    width: 6px;
    height: 6px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: currentColor;
}

.accountability-case-row > td > small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.3;
}

.accountability-next-link {
    text-decoration: none;
}

.accountability-next-link > span:last-child {
    font-size: 13px;
    line-height: 1;
}

.accountability-next-state {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 700;
    line-height: 1.3;
    white-space: nowrap;
}

.accountability-next-state-group {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.accountability-row-none { color: var(--text-soft); }
.accountability-row-note { color: var(--text-muted); font-size: 11px; font-weight: 700; }
.accountability-row-action { min-height: 30px; padding: 0 12px; font-size: 11.5px; }

.accountability-case-detail-row > td { padding: 0 12px 12px; border-bottom: 1px solid var(--row-border); }
.accountability-cases-table tbody tr:last-child > td { border-bottom: 0; }
.accountability-case-row > td { border-bottom: 0; }
.accountability-cases-table tbody tr:hover { background: transparent; }
.accountability-cases-table tbody tr.accountability-case-row:hover { background: var(--row-hover); }

.accountability-detail-stack { display: grid; gap: 10px; }

.accountability-detail-note {
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface-subtle);
}

.accountability-detail-note strong {
    display: block;
    color: var(--heading);
    font-size: 11px;
    font-weight: 800;
}

.accountability-detail-note p {
    margin: 3px 0 0;
    color: var(--text-secondary);
    font-size: 10.75px;
    line-height: 1.45;
}

.accountability-detail-note small {
    display: block;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.45;
}

.accountability-detail-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--border);
}

.accountability-detail-grid > div {
    min-width: 0;
    padding: 9px 11px;
    background: var(--surface);
}

.accountability-detail-grid dt {
    margin: 0 0 3px;
    color: var(--text-muted);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.accountability-detail-grid dd {
    margin: 0;
    color: var(--heading);
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.accountability-document-list {
    display: grid;
    gap: 1px;
    overflow: hidden;
    margin-top: 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--border);
}

.accountability-document-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 40px;
    padding: 6px 10px 6px 12px;
    background: var(--surface);
}

.accountability-document-item > span {
    color: var(--heading);
    font-size: 11.25px;
    font-weight: 700;
}

.accountability-document-item .table-action {
    flex: 0 0 auto;
    min-height: 28px;
    padding: 3px 7px;
    font-size: 10.75px;
}

.accountability-detail-forms {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 12px;
    padding: 13px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
}

.accountability-detail-forms .form-grid { margin: 0; }
.accountability-detail-forms textarea { min-height: 66px; }

.accountability-cases-none {
    margin: 0;
    padding: 12px 12px;
    border-top: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 12px;
}

.accountability-scope-hint {
    margin: 12px 0 0;
    color: var(--text-muted);
    font-size: 11.5px;
    line-height: 1.55;
}

@media (max-width: 900px) {
    .accountability-detail-forms { grid-template-columns: minmax(0, 1fr); }
    .accountability-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }

    .accountability-cases-table { container-type: inline-size; }
    .accountability-case-detail-row > td { padding-left: 0; padding-right: 0; }

    .accountability-detail-note,
    .accountability-detail-grid,
    .accountability-action-body,
    .accountability-detail-forms {
        position: sticky;
        left: 14px;
        box-sizing: border-box;
        width: calc(100cqi - 28px);
    }
}

@media (max-width: 760px) {
    .accountability-browser-toolbar { align-items: stretch; }
    .accountability-browser-search,
    .accountability-browser-filter { flex: 1 1 100%; max-width: none; min-width: 0; }
    .accountability-detail-grid { grid-template-columns: minmax(0, 1fr); }
}

.accountability-sanctions-section { margin-top: 12px; }
.accountability-sanctions-table table { min-width: 850px; }
.accountability-sanctions-table th,
.accountability-sanctions-table td { vertical-align: middle; }
.accountability-sanctions-table td { font-size: 11.5px; }
.accountability-sanctions-table th { font-size: 10px; }

.accountability-sanction-row > td:first-child { min-width: 145px; }
.accountability-sanction-row > td:nth-child(3) { max-width: 340px; }

.accountability-sanction-offense,
.accountability-sanction-action {
    color: var(--heading);
    font-size: 11.5px;
    font-weight: 700;
}

.accountability-sanction-note {
    display: -webkit-box !important;
    margin-top: 2px !important;
    overflow: hidden;
    color: var(--text-muted) !important;
    font-size: 10px !important;
    font-weight: 500;
    line-height: 1.35 !important;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.accountability-sanction-row td > small:not(.accountability-sanction-note) {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.3;
}

.accountability-sanction-notice {
    gap: 6px;
    min-height: 30px;
    padding: 4px 8px;
    font-size: 10.75px;
}

@media (max-width: 760px) {
    .accountability-sanctions-table table { min-width: 760px; }
}

.accountability-overview {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}

.accountability-overview-card {
    --tone-from: #2563eb;
    --tone-to: #38bdf8;
    --tone-glow: rgba(37, 99, 235, .28);

    position: relative;
    display: grid;
    gap: 0;
    align-content: start;
    min-width: 0;
    padding: 16px 16px 16px;
    border: 0;
    border-radius: 14px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--tone-from), var(--tone-to));
    box-shadow: 0 10px 24px var(--tone-glow);
    color: #fff;
    text-decoration: none;
}

.accountability-overview-card::before {
    content: "";
    position: absolute;
    inset: auto -10% -28% 34%;
    height: 72%;
    border-radius: 999px;
    background: rgba(255, 255, 255, .08);
    transform: rotate(-8deg);
}

.accountability-overview-card::after {
    content: "";
    position: absolute;
    inset: -18% auto auto -12%;
    width: 42%;
    aspect-ratio: 1;
    border-radius: 50%;
    background: rgba(255, 255, 255, .08);
}

.accountability-overview-card > * {
    position: relative;
    z-index: 1;
}

.accountability-overview-card:hover,
.accountability-overview-card:focus-visible {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px var(--tone-glow);
}
.accountability-overview-arrow { display: none; }

.accountability-overview-icon {
    display: inline-grid;
    width: 52px;
    height: 52px;
    place-items: center;
    margin-bottom: 12px;
    border-radius: 50%;
    color: #fff;
    background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .28);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12);
}

.accountability-overview-label {
    color: rgba(255, 255, 255, .95);
    font-size: 14.5px;
    font-weight: 700;
    line-height: 1.3;
}

.accountability-overview-value {
    margin-top: 6px;
    color: #fff;
    font-size: 31px;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.accountability-overview-note {
    margin-top: 8px;
    color: rgba(255, 255, 255, .86);
    font-size: 12px;
    line-height: 1.4;
}

.accountability-overview-card.tone-open {
    --tone-from: #1d4ed8;
    --tone-to: #38bdf8;
    --tone-glow: rgba(29, 78, 216, .30);
}

.accountability-overview-card.tone-overdue {
    --tone-from: #dc2626;
    --tone-to: #fb923c;
    --tone-glow: rgba(220, 38, 38, .28);
}

.accountability-overview-card.tone-balance {
    --tone-from: #d97706;
    --tone-to: #f59e0b;
    --tone-glow: rgba(217, 119, 6, .30);
}

.accountability-overview-card.tone-resolved {
    --tone-from: #0f766e;
    --tone-to: #10b981;
    --tone-glow: rgba(15, 118, 110, .28);
}

html[data-theme="dark"] .accountability-overview-card.tone-open { --tone-from: #1e40af; --tone-to: #0284c7; --tone-glow: rgba(0, 0, 0, .42); }
html[data-theme="dark"] .accountability-overview-card.tone-overdue { --tone-from: #991b1b; --tone-to: #ea580c; --tone-glow: rgba(0, 0, 0, .42); }
html[data-theme="dark"] .accountability-overview-card.tone-balance { --tone-from: #b45309; --tone-to: #d97706; --tone-glow: rgba(0, 0, 0, .42); }
html[data-theme="dark"] .accountability-overview-card.tone-resolved { --tone-from: #115e59; --tone-to: #059669; --tone-glow: rgba(0, 0, 0, .42); }

.accountability-overview-card.tone-balance .accountability-overview-value { font-size: 27px; }

@media (max-width: 1180px) {
    .accountability-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 620px) {
    .accountability-overview { grid-template-columns: minmax(0, 1fr); }
    .accountability-overview-card { padding: 12px 16px 16px; }
    .accountability-overview-icon { width: 46px; height: 46px; margin-bottom: 12px; }
    .accountability-overview-value { font-size: 27px; }
}

.accountability-case-workspace,
.accountability-cases-card,
.accountability-cases-table,
.accountability-cases-table table {
    width: 100%;
    max-width: none;
}
.accountability-cases-table th { font-size: 10.5px; }
.accountability-cases-table td { font-size: 12.5px; }
.accountability-case-ref { font-size: 12.5px; }
.accountability-case-borrower { font-size: 11.5px; }

</style>
