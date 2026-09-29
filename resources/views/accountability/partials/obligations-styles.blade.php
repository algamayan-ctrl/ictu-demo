<style>
/*
|--------------------------------------------------------------------------
| Borrower - My Obligations (Grouped)
|--------------------------------------------------------------------------
|
| Borrower view only. Related technical records are presented as one
| obligation card so Incident + Billing + Restriction do not look like three
| different problems.
|
*/

.ob-workspace {
    display: grid;
    gap: 18px;
}

.ob-section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 16px;
}

.ob-section-heading span {
    display: block;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.ob-section-heading p {
    margin: 3px 0 0;
    color: var(--text-muted);
    font-size: 12px;
}

/* Record summary --------------------------------------------------------- */

.ob-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

/* Universal KPI card: matches Admin/AO/Borrower dashboard and the SPMU
   accountability summary cards in size, spacing, border and hover rhythm. */
.ob-summary-card {
    --ob-accent: var(--info);
    --ob-hover: rgba(23, 105, 224, .045);
    --ob-tint: #edf5fd;
    --ob-ink: #1d6fb8;

    position: relative;
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    grid-template-rows: auto auto auto;
    column-gap: 12px;
    row-gap: 2px;
    min-width: 0;
    min-height: 122px;
    padding: 16px 18px;
    border: 1px solid var(--border);
    border-top: 3px solid var(--ob-accent);
    border-radius: 12px;
    background: var(--surface);
    box-shadow: 0 1px 2px rgba(7, 27, 53, .05);
    transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease, background .16s ease;
}

.ob-summary-card.is-danger  { --ob-accent:#dc3545; --ob-hover:rgba(220,53,69,.04); --ob-tint:#fff1ef; --ob-ink:#c4493d; }
.ob-summary-card.is-warning { --ob-accent:#d99b16; --ob-hover:rgba(217,155,22,.05); --ob-tint:#fff8e9; --ob-ink:#a66a06; }
.ob-summary-card.is-info    { --ob-accent:#1769e0; --ob-hover:rgba(23,105,224,.045); --ob-tint:#edf5fd; --ob-ink:#1d6fb8; }
.ob-summary-card.is-orange  { --ob-accent:#ef7a29; --ob-hover:rgba(239,122,41,.05); --ob-tint:#fff3e9; --ob-ink:#bd5815; }
.ob-summary-card.is-success { --ob-accent:#159447; --ob-hover:rgba(21,148,71,.070); --ob-tint:#eefaf3; --ob-ink:#159447; }

.ob-summary-card:hover {
    transform: translateY(-1px);
    background: linear-gradient(var(--ob-hover), var(--ob-hover)), var(--surface);
    border-color: var(--border-strong);
    border-top-color: var(--ob-accent);
    box-shadow: 0 10px 24px rgba(7, 27, 53, .08);
}

.ob-summary-icon {
    grid-column: 1;
    grid-row: 1 / span 3;
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    align-self: center;
    color: var(--ob-ink);
    background: var(--ob-tint);
    border: 1px solid color-mix(in srgb, var(--ob-accent) 28%, var(--border));
    border-radius: 50%;
}

.ob-summary-label {
    grid-column: 2;
    grid-row: 1;
    align-self: end;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 800;
    line-height: 1.25;
}

.ob-summary-value {
    grid-column: 2;
    grid-row: 2;
    display: block;
    color: var(--heading);
    font-size: 25px;
    font-weight: 800;
    line-height: 1.15;
}

.ob-summary-note {
    grid-column: 2;
    grid-row: 3;
    display: block;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.35;
}

.ob-summary-card.is-empty { opacity: 1; }

html[data-theme="dark"] .ob-summary-card:hover { background: var(--surface-hover); }
html[data-theme="dark"] .ob-summary-card.is-danger,
html[data-theme="dark"] .ob-summary-card.is-warning,
html[data-theme="dark"] .ob-summary-card.is-info,
html[data-theme="dark"] .ob-summary-card.is-orange,
html[data-theme="dark"] .ob-summary-card.is-success {
    --ob-tint: var(--surface-subtle);
}

/* Current obligations heading ------------------------------------------- */

.ob-current-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 18px;
    padding-top: 2px;
}

.ob-current-header h2 {
    display: flex;
    align-items: center;
    gap: 9px;
    margin: 0;
    color: var(--heading);
    font-size: 15.5px;
}

.ob-search {
    display: grid;
    gap: 6px;
    width: min(100%, 330px);
    margin: 0;
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 800;
}

.ob-search .search-input-shell { width: 100%; }

.ob-search input {
    width: 100%;
    min-height: 38px;
    margin: 0;
}

/* Obligation cards ------------------------------------------------------- */

.ob-case-list {
    display: grid;
    gap: 12px;
}

.ob-case-card {
    padding: 16px 18px;
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.ob-case-card[hidden] { display: none; }

.ob-case-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.ob-case-identity {
    display: flex;
    align-items: flex-start;
    gap: 13px;
    min-width: 0;
}

.ob-case-icon {
    --ob-tint: var(--surface-subtle);
    --ob-ink: var(--text-muted);

    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 42px;
    height: 42px;
    color: var(--ob-ink);
    background: var(--ob-tint);
    border-radius: 11px;
}

.ob-case-icon.is-danger  { --ob-tint:#fdecee; --ob-ink:#b82938; }
.ob-case-icon.is-warning { --ob-tint:#fdf3dd; --ob-ink:#926307; }
.ob-case-icon.is-info    { --ob-tint:#e8f1fd; --ob-ink:#1157bd; }
.ob-case-icon.is-orange  { --ob-tint:#fdeee2; --ob-ink:#bd5815; }

html[data-theme="dark"] .ob-case-icon.is-danger  { --ob-tint:#351b1d; --ob-ink:#ff9b93; }
html[data-theme="dark"] .ob-case-icon.is-warning { --ob-tint:#332711; --ob-ink:#f3c56a; }
html[data-theme="dark"] .ob-case-icon.is-info    { --ob-tint:#14293d; --ob-ink:#86c6fb; }
html[data-theme="dark"] .ob-case-icon.is-orange  { --ob-tint:#33210f; --ob-ink:#f7ad6f; }

.ob-case-type {
    display: block;
    margin-bottom: 2px;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.ob-case-identity h3 {
    margin: 0;
    color: var(--heading);
    font-size: 16px;
    line-height: 1.3;
}

.ob-case-identity small {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 11px;
}

.ob-case-state {
    display: grid;
    justify-items: end;
    gap: 5px;
    flex: 0 0 auto;
}

.ob-case-state strong {
    color: var(--heading);
    font-size: 13px;
}

.ob-badge {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 10px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.ob-badge.is-danger  { color:var(--danger); background:var(--danger-bg); border-color:var(--danger-border); }
.ob-badge.is-warning { color:var(--warning); background:var(--warning-bg); border-color:var(--warning-border); }
.ob-badge.is-info    { color:var(--info); background:var(--info-bg); border-color:var(--info-border); }
.ob-badge.is-success { color:var(--success); background:var(--success-bg); border-color:var(--success-border); }
.ob-badge.is-neutral { color:var(--neutral); background:var(--neutral-bg); border-color:var(--neutral-border); }

.ob-case-summary {
    margin: 10px 0 0 55px;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.45;
}

.ob-linked-restriction {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 10px 0 0 55px;
    color: var(--warning);
    font-size: 11.5px;
    font-weight: 650;
}

.ob-next-action {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 12px 0 0 55px;
    padding: 9px 11px;
    border-left: 3px solid var(--border);
    background: var(--surface-subtle);
    border-radius: 7px;
}

.ob-next-action > span {
    flex: 0 0 auto;
    color: var(--text-muted);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.ob-next-action > strong {
    color: var(--heading);
    font-size: 11.5px;
    line-height: 1.4;
}

.ob-next-action.is-warning { border-left-color: var(--warning); }
.ob-next-action.is-danger  { border-left-color: var(--danger); }
.ob-next-action.is-info    { border-left-color: var(--info); }

.ob-case-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 12px 0 0 55px;
}

.ob-details {
    position: relative;
}

/*
 * One obligation = one obvious primary action. This is the sole visible
 * control on the collapsed card, so it carries the exact same look as the
 * universal primary button used everywhere else in the system (View
 * Request, View Record, and so on) - not a secondary/outline treatment.
 */
.ob-details > summary:not(.disclosure-trigger) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 34px;
    padding: 6px 14px;
    color: #fff;
    background: var(--primary-action);
    border: 1px solid var(--primary-action);
    border-radius: 8px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 750;
    list-style: none;
}

.ob-details > summary:not(.disclosure-trigger):hover,
.ob-details > summary:not(.disclosure-trigger):focus-visible {
    background: var(--primary-action-hover);
    border-color: var(--primary-action-hover);
}

.ob-details > summary:not(.disclosure-trigger)::-webkit-details-marker { display: none; }

.ob-details[open] {
    width: 100%;
}

.ob-details[open] > summary {
    margin-bottom: 12px;
}

.ob-disclosure-chevron {
    flex: 0 0 auto;
    transition: transform .16s ease;
}

.ob-details[open] > summary .ob-disclosure-chevron:not(.disclosure-trigger__chevron),
.ob-resolved-history-disclosure[open] > summary .ob-disclosure-chevron {
    transform: rotate(180deg);
}

/* Expanded obligation: Documents / Borrowing Reference / Restriction /
   History, each its own labeled group instead of one undifferentiated
   button row - and never repeating what the collapsed card already shows. */
.ob-detail-body {
    display: grid;
    gap: 14px;
    width: 100%;
}

.ob-detail-section {
    display: grid;
    gap: 8px;
}

.ob-detail-section-heading {
    margin: 0;
    color: var(--text-muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.ob-detail-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.ob-document-list {
    display: grid;
    gap: 1px;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--border);
}

.ob-document-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 42px;
    padding: 7px 10px 7px 12px;
    background: var(--surface);
}

.ob-document-name {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
    color: var(--text-secondary);
}

.ob-document-name .ui-icon {
    flex: 0 0 auto;
    color: var(--text-muted);
}

.ob-document-name strong {
    color: var(--heading);
    font-size: 11.5px;
    font-weight: 700;
    line-height: 1.35;
}

.ob-document-item .table-action {
    flex: 0 0 auto;
    min-height: 28px;
    padding: 3px 7px;
    font-size: 10.75px;
}

.ob-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    width: 100%;
    overflow: hidden;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.ob-detail-grid > div {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 10px 12px;
    background: var(--surface);
}

.ob-detail-grid small {
    color: var(--text-muted);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.ob-detail-grid strong {
    color: var(--heading);
    font-size: 11.5px;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.ob-clear-card {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 18px 20px;
    color: var(--success);
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.ob-clear-card strong {
    display: block;
    color: var(--heading);
    font-size: 14px;
}

.ob-clear-card p {
    margin: 3px 0 0;
    color: var(--text-muted);
    font-size: 11.5px;
}

.ob-no-match {
    padding: 24px;
    color: var(--text-muted);
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-radius: 10px;
    text-align: center;
}

/* Responsive ------------------------------------------------------------- */

@media (max-width: 1080px) {
    .ob-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ob-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .ob-summary { grid-template-columns: 1fr; }

    .ob-current-header {
        align-items: stretch;
        flex-direction: column;
    }

    .ob-search { width: 100%; }

    .ob-case-top {
        flex-direction: column;
    }

    .ob-case-state {
        justify-items: start;
        margin-left: 55px;
    }

    .ob-case-summary,
    .ob-linked-restriction,
    .ob-next-action,
    .ob-case-actions {
        margin-left: 0;
    }

    .ob-next-action {
        align-items: flex-start;
        flex-direction: column;
        gap: 4px;
    }

    .ob-detail-grid { grid-template-columns: 1fr; }
}

/* Borrower summary cards use the same KPI language as the dashboards and
   AO/Admin accountability summary: white body, colored top accent, tinted icon. */
.ob-summary-card {
    border-top:3px solid var(--ob-accent);
    box-shadow:0 1px 2px rgba(7, 27, 53, .05);
    transition:transform .16s ease, border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.ob-summary-card:hover {
    transform:translateY(-1px);
    border-color:var(--border-strong);
    border-top-color:var(--ob-accent);
    background:linear-gradient(color-mix(in srgb, var(--ob-accent) 7%, transparent), color-mix(in srgb, var(--ob-accent) 7%, transparent)), var(--surface-elevated);
    box-shadow:0 8px 20px rgba(7, 27, 53, .07);
}
.ob-summary-icon {
    border:1px solid color-mix(in srgb, var(--ob-accent) 28%, transparent);
    border-radius:8px;
}
.ob-summary-card.is-empty { opacity:1; }
html[data-theme="dark"] .ob-summary-card:hover { background:var(--surface-hover); }


/* -----------------------------------------------------------------------
| My Obligations summary cards — exact dashboard KPI proportions
| Matches the SPMU Head / Administrator dashboard card dimensions and
| information hierarchy while remaining non-clickable summary content.
| ------------------------------------------------------------------------ */
.ob-summary-card {
    position: relative;
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr);
    grid-template-rows: auto auto auto;
    column-gap: 12px;
    row-gap: 2px;
    min-height: 122px;
    padding: 16px 18px;
    align-items: initial;
    border: 1px solid var(--border);
    border-top: 3px solid var(--ob-accent);
    border-radius: 12px;
    background: var(--surface);
    box-shadow: 0 1px 2px rgba(7, 27, 53, .05);
}

.ob-summary-copy {
    display: contents;
}

.ob-summary-icon {
    grid-column: 1;
    grid-row: 1 / span 3;
    display: grid;
    place-items: center;
    align-self: center;
    width: 44px;
    height: 44px;
    margin: 0;
    border: 1px solid color-mix(in srgb, var(--ob-accent) 28%, transparent);
    border-radius: 50%;
}

.ob-summary-label {
    grid-column: 2;
    grid-row: 1;
    align-self: end;
    margin: 0;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 800;
    line-height: 1.25;
}

.ob-summary-value {
    grid-column: 2;
    grid-row: 2;
    display: block;
    margin: 0;
    color: var(--heading);
    font-size: 25px;
    font-weight: 800;
    line-height: 1.15;
}

.ob-summary-note {
    grid-column: 2;
    grid-row: 3;
    display: block;
    margin: 0;
    color: var(--text-muted);
    font-size: 10px;
    line-height: 1.35;
}

@media (max-width: 1300px) {
    .ob-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 680px) {
    .ob-summary { grid-template-columns: 1fr; }
}

/* Resolved History disclosure --------------------------------------------
   Kept out of the way by default so My Obligations stays one clean, active
   workspace; the borrower's own resolved records remain one click away. */
.ob-resolved-history-disclosure {
    margin-top: 4px;
    padding: 16px 18px;
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.ob-resolved-history-disclosure > summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    cursor: pointer;
    color: var(--heading);
    font-size: 14px;
    font-weight: 800;
    list-style: none;
}

.ob-resolved-history-disclosure > summary::-webkit-details-marker { display: none; }

.ob-resolved-history-meta {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ob-resolved-history-disclosure[open] > summary { margin-bottom: 14px; }

.ob-resolved-history-body { padding-top: 2px; }
.ob-resolved-history-body > .content-area { margin: 0; }

</style>
<style>
/* Final borrower-facing obligation cleanup: current information first,
   no hover affordance on summary-only cards, and one consistent disclosure. */
.ob-workspace { gap: 18px; }

.ob-summary-card {
    min-height: 124px;
    cursor: default;
    transition: none;
}
.ob-summary-card:hover {
    transform: none;
    background: var(--surface);
    border-color: var(--border);
    border-top-color: var(--ob-accent);
    box-shadow: 0 1px 2px rgba(7, 27, 53, .05);
}
.ob-summary-value {
    font-size: clamp(20px, 1.55vw, 25px);
    overflow-wrap: anywhere;
}
.ob-current-header {
    align-items: flex-end;
    padding-top: 4px;
}
.ob-current-header h2 { margin: 0; }

.ob-case-card { padding: 17px 19px; }
.ob-case-summary { margin-top: 10px; }
.ob-next-action { margin-top: 12px; }
.ob-case-actions { margin-top: 12px; }

.ob-clear-card {
    min-height: 104px;
    padding: 20px 22px;
    border-left: 3px solid var(--success);
}
.ob-clear-card > span { flex: 0 0 auto; }
.ob-clear-card strong { font-size: 14px; }
.ob-clear-card p { max-width: 780px; line-height: 1.5; }

.ob-resolved-history-disclosure { margin-top: 0; }

@media (max-width: 720px) {
    .ob-current-header { align-items: stretch; flex-direction: column; }
    .ob-search { width: 100%; }
}


/* Compact borrower surface: do not let an empty My Obligations workspace
   stretch its lone state card to the remaining viewport height. */
.app-main > .ob-workspace {
    flex: 0 0 auto;
    width: 100%;
    align-content: start;
}

.ob-summary-card.is-clickable {
    cursor: pointer;
    color: inherit;
    text-decoration: none;
    transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.ob-summary-card.is-clickable:hover,
.ob-summary-card.is-clickable:focus-visible {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ob-accent) 30%, var(--border));
    border-top-color: var(--ob-accent);
    background: linear-gradient(color-mix(in srgb, var(--ob-accent) 6%, transparent), color-mix(in srgb, var(--ob-accent) 6%, transparent)), var(--surface-elevated);
    box-shadow: 0 8px 20px rgba(7, 27, 53, .07);
}
.ob-summary-card.is-clickable:focus-visible {
    outline: 3px solid color-mix(in srgb, var(--ob-accent) 24%, transparent);
    outline-offset: 3px;
}
.ob-summary-card.is-selected {
    border-color: color-mix(in srgb, var(--ob-accent) 25%, var(--border));
    border-top-color: var(--ob-accent);
}
.ob-summary-arrow {
    position: absolute;
    top: 13px;
    right: 14px;
    color: var(--text-muted);
    font-size: 15px;
    line-height: 1;
    transition: transform .16s ease, color .16s ease;
}
.ob-summary-card.is-clickable:hover .ob-summary-arrow,
.ob-summary-card.is-clickable:focus-visible .ob-summary-arrow {
    color: var(--ob-accent);
    transform: translateX(2px);
}

.ob-clear-card {
    min-height: 0;
    padding: 15px 18px;
}
</style>

<style>
/* MY OBLIGATIONS FULL COLOR KPI START */

/*
 | Exact visual alignment with the main role dashboard KPI cards.
 | Presentation only.
 */

/* ============================================================
   GRID
   ============================================================ */

.ob-workspace .ob-summary {
    display:grid !important;
    grid-template-columns:repeat(4,minmax(0,1fr)) !important;
    gap:12px !important;
    width:100% !important;
}


/* ============================================================
   CARD
   ============================================================ */

.ob-workspace .ob-summary-card {
    --ob-from:#32a5f5;
    --ob-mid:#1479ea;
    --ob-to:#075fd3;
    --ob-shadow:rgba(20,121,234,.20);

    position:relative !important;

    display:grid !important;
    grid-template-columns:52px minmax(0,1fr) !important;
    grid-template-rows:auto auto auto !important;

    column-gap:12px !important;
    row-gap:2px !important;

    align-content:center !important;

    min-width:0 !important;

    height:132px !important;
    min-height:132px !important;

    padding:16px 43px 16px 20px !important;

    overflow:hidden !important;

    border:0 !important;
    border-radius:11px !important;

    background:
        linear-gradient(
            135deg,
            var(--ob-from) 0%,
            var(--ob-mid) 55%,
            var(--ob-to) 100%
        ) !important;

    color:#fff !important;

    box-shadow:
        0 4px 12px var(--ob-shadow) !important;

    text-decoration:none !important;

    opacity:1 !important;
    transform:none !important;

    transition:
        transform 160ms ease,
        box-shadow 160ms ease !important;
}


/* ============================================================
   DASHBOARD LOWER CURVE
   ============================================================ */

.ob-workspace .ob-summary-card::before {
    content:"" !important;

    position:absolute !important;

    right:-28px !important;
    bottom:-58px !important;

    width:180px !important;
    height:88px !important;

    border-radius:55% 0 0 0 !important;

    background:rgba(255,255,255,.085) !important;

    transform:rotate(-5deg) !important;

    pointer-events:none !important;
}


/* ============================================================
   DASHBOARD ARROW
   ============================================================ */

.ob-workspace .ob-summary-card::after {
    content:"→" !important;

    position:absolute !important;
    top:14px !important;
    right:13px !important;

    display:grid !important;
    place-items:center !important;

    width:30px !important;
    height:30px !important;

    border:1px solid rgba(255,255,255,.15) !important;
    border-radius:50% !important;

    background:rgba(255,255,255,.12) !important;

    color:#fff !important;

    font-size:14px !important;
    font-weight:700 !important;
    line-height:1 !important;

    pointer-events:none !important;

    transition:
        background 160ms ease,
        transform 160ms ease !important;
}

.ob-workspace .ob-summary-card > * {
    position:relative !important;
    z-index:1 !important;
}


/* ============================================================
   UNIVERSAL DASHBOARD SEMANTIC COLORS
   ============================================================ */

/*
 | RED
 | Active Obligations = same metric and same semantic treatment
 | as the Borrower Dashboard.
 */
.ob-workspace .ob-summary-card.is-info {
    --ob-from:#fb5d5d;
    --ob-mid:#ea3939;
    --ob-to:#c92525;
    --ob-shadow:rgba(234,57,57,.20);
}


/*
 | BLUE
 | Needs My Action = primary/current action.
 */
.ob-workspace .ob-summary-card.is-warning {
    --ob-from:#32a5f5;
    --ob-mid:#1479ea;
    --ob-to:#075fd3;
    --ob-shadow:rgba(20,121,234,.20);
}


/*
 | AMBER
 | Amount Due = financial warning / outstanding amount.
 */
.ob-workspace .ob-summary-card.is-orange {
    --ob-from:#ffc443;
    --ob-mid:#f6a10c;
    --ob-to:#dc7b00;
    --ob-shadow:rgba(246,161,12,.21);
}


/*
 | GREEN
 | Borrowing status clear.
 */
.ob-workspace .ob-summary-card.is-success {
    --ob-from:#2acb9e;
    --ob-mid:#12ab83;
    --ob-to:#087b69;
    --ob-shadow:rgba(18,171,131,.20);
}


/*
 | If Borrowing Status becomes restricted, its existing Blade
 | changes the tone to warning. Since "warning" above is used by
 | Needs My Action, override the last card specifically to amber.
 */
.ob-workspace .ob-summary-card:last-child.is-warning {
    --ob-from:#ffc443;
    --ob-mid:#f6a10c;
    --ob-to:#dc7b00;
    --ob-shadow:rgba(246,161,12,.21);
}


/* Future danger support */
.ob-workspace .ob-summary-card.is-danger {
    --ob-from:#fb5d5d;
    --ob-mid:#ea3939;
    --ob-to:#c92525;
    --ob-shadow:rgba(234,57,57,.20);
}


/* ============================================================
   ICON
   ============================================================ */

.ob-workspace .ob-summary-icon {
    grid-column:1 !important;
    grid-row:1 / span 3 !important;

    display:grid !important;

    width:52px !important;
    height:52px !important;

    place-items:center !important;
    align-self:center !important;

    margin:0 !important;
    padding:0 !important;

    border:1px solid rgba(255,255,255,.42) !important;
    border-radius:50% !important;

    background:rgba(255,255,255,.12) !important;

    color:#fff !important;
}

.ob-workspace .ob-summary-icon svg,
.ob-workspace .ob-summary-icon .ui-icon {
    width:20px !important;
    height:20px !important;
    color:#fff !important;
}


/* ============================================================
   LABEL
   ============================================================ */

.ob-workspace .ob-summary-label {
    grid-column:2 !important;
    grid-row:1 !important;

    align-self:end !important;

    min-width:0 !important;
    margin:0 !important;

    color:#fff !important;

    font-size:12px !important;
    font-weight:800 !important;
    line-height:1.25 !important;

    letter-spacing:0 !important;
    text-transform:none !important;

    display:-webkit-box !important;
    -webkit-box-orient:vertical !important;
    -webkit-line-clamp:2 !important;

    overflow:hidden !important;
}


/* ============================================================
   VALUE
   ============================================================ */

.ob-workspace .ob-summary-value {
    grid-column:2 !important;
    grid-row:2 !important;

    min-width:0 !important;

    margin:2px 0 0 !important;

    color:#fff !important;

    font-size:29px !important;
    font-weight:800 !important;
    line-height:1.05 !important;

    letter-spacing:-.02em !important;

    font-variant-numeric:tabular-nums !important;

    white-space:nowrap !important;
}


/* Amount Due */
.ob-workspace .ob-summary-card.is-orange .ob-summary-value {
    font-size:28px !important;
}


/* ============================================================
   NOTE
   ============================================================ */

.ob-workspace .ob-summary-note {
    grid-column:2 !important;
    grid-row:3 !important;

    min-width:0 !important;

    margin:2px 0 0 !important;

    color:rgba(255,255,255,.96) !important;

    font-size:10.5px !important;
    font-weight:600 !important;
    line-height:1.3 !important;

    display:-webkit-box !important;
    -webkit-box-orient:vertical !important;
    -webkit-line-clamp:2 !important;

    overflow:hidden !important;
}


/* ============================================================
   HOVER
   ============================================================ */

.ob-workspace .ob-summary-card:hover {
    background:
        linear-gradient(
            135deg,
            var(--ob-from) 0%,
            var(--ob-mid) 55%,
            var(--ob-to) 100%
        ) !important;

    color:#fff !important;

    transform:translateY(-1px) !important;

    box-shadow:
        0 7px 17px var(--ob-shadow) !important;
}

.ob-workspace .ob-summary-card:hover::after {
    background:rgba(255,255,255,.20) !important;
    transform:translateX(1px) !important;
}


/* ============================================================
   REMOVE FILTER-SELECTION VISUAL DIFFERENCE
   ============================================================ */

/*
 | The URL focus behavior still works.
 | We only remove the special yellow/white outline so cards remain
 | visually identical to dashboard cards.
 */

.ob-workspace .ob-summary-card.is-selected,
.ob-workspace .ob-summary-card[aria-current="true"] {
    outline:0 !important;
    border:0 !important;

    box-shadow:
        0 4px 12px var(--ob-shadow) !important;
}

.ob-workspace .ob-summary-card.is-selected:hover,
.ob-workspace .ob-summary-card[aria-current="true"]:hover {
    box-shadow:
        0 7px 17px var(--ob-shadow) !important;
}


/* keyboard accessibility remains visible */
.ob-workspace .ob-summary-card:focus-visible {
    outline:2px solid rgba(255,255,255,.95) !important;
    outline-offset:2px !important;
}


/* Zero values retain full semantic color */
.ob-workspace .ob-summary-card.is-empty {
    opacity:1 !important;
}


/* ============================================================
   RESPONSIVE
   ============================================================ */

@media (max-width:1100px) {

    .ob-workspace .ob-summary {
        grid-template-columns:repeat(2,minmax(0,1fr)) !important;
    }

}

@media (max-width:680px) {

    .ob-workspace .ob-summary {
        grid-template-columns:1fr !important;
        gap:10px !important;
    }

    .ob-workspace .ob-summary-card {
        height:auto !important;
        min-height:124px !important;
    }

}

@media (prefers-reduced-motion:reduce) {

    .ob-workspace .ob-summary-card,
    .ob-workspace .ob-summary-card::after {
        transition:none !important;
    }

    .ob-workspace .ob-summary-card:hover {
        transform:none !important;
    }

}

/* MY OBLIGATIONS FULL COLOR KPI END */
</style>

<style>
/* MY OBLIGATIONS ALL CLEAR PANEL START */

.ob-workspace
.ob-clear-card.ob-all-clear-card {
    position:relative !important;

    display:grid !important;
    grid-template-columns:42px minmax(0,1fr) auto !important;

    align-items:center !important;

    gap:14px !important;

    min-height:76px !important;

    margin:0 !important;
    padding:14px 17px !important;

    overflow:hidden !important;

    border:1px solid var(--border) !important;
    border-left:3px solid #16825d !important;
    border-radius:11px !important;

    background:
        linear-gradient(
            90deg,
            rgba(22,130,93,.055) 0%,
            rgba(255,255,255,.98) 42%,
            #fff 100%
        ) !important;

    box-shadow:
        0 1px 3px rgba(15,23,42,.04) !important;

    color:inherit !important;
}


/* ICON */

.ob-workspace
.ob-all-clear-icon {
    display:grid !important;
    place-items:center !important;

    width:40px !important;
    height:40px !important;

    border:1px solid rgba(22,130,93,.24) !important;
    border-radius:50% !important;

    background:#ecfdf5 !important;

    color:#087b55 !important;
}

.ob-workspace
.ob-all-clear-icon svg,
.ob-workspace
.ob-all-clear-icon .ui-icon {
    width:22px !important;
    height:22px !important;

    color:#087b55 !important;
}


/* COPY */

.ob-workspace
.ob-all-clear-copy {
    min-width:0 !important;
}

.ob-workspace
.ob-all-clear-copy strong {
    display:block !important;

    margin:0 !important;

    color:var(--heading) !important;

    font-size:13.5px !important;
    font-weight:800 !important;
    line-height:1.35 !important;
}

.ob-workspace
.ob-all-clear-copy p {
    max-width:760px !important;

    margin:4px 0 0 !important;

    color:var(--text-muted) !important;

    font-size:11px !important;
    line-height:1.45 !important;
}


/* VIEW HISTORY */

.ob-workspace
.ob-all-clear-history {
    display:inline-flex !important;
    align-items:center !important;

    gap:6px !important;

    padding:7px 10px !important;

    border-radius:8px !important;

    color:#087b55 !important;
    background:transparent !important;

    font-size:11px !important;
    font-weight:750 !important;

    text-decoration:none !important;
    white-space:nowrap !important;
}

.ob-workspace
.ob-all-clear-history:hover {
    background:rgba(22,130,93,.06) !important;
    color:#075f46 !important;
}


#obligation-history {
    scroll-margin-top:
        calc(var(--topbar-height, 64px) + 16px) !important;
}


@media (max-width:680px) {

    .ob-workspace
    .ob-clear-card.ob-all-clear-card {
        grid-template-columns:38px minmax(0,1fr) !important;

        gap:11px !important;

        padding:13px 14px !important;
    }

    .ob-workspace
    .ob-all-clear-icon {
        width:38px !important;
        height:38px !important;
    }

    .ob-workspace
    .ob-all-clear-history {
        grid-column:2 !important;
        justify-self:start !important;
        padding-left:0 !important;
    }
}

/* MY OBLIGATIONS ALL CLEAR PANEL END */
</style>

<style>
/* Borrower My Obligations: viewport-filling KPI row and compact record disclosures.
   The three-card layout must override the older forced four-column KPI CSS. */
.ob-workspace { width:100%; min-width:0; gap:14px; align-content:start; }
.ob-workspace .ob-summary.is-three { grid-template-columns:repeat(3,minmax(0,1fr)) !important; }
.ob-workspace .ob-summary:not(.is-three) { grid-template-columns:repeat(4,minmax(0,1fr)) !important; }
.ob-workspace .ob-summary-card { width:100%; min-width:0; height:126px !important; }
.ob-workspace .ob-focused-results { min-height:0; }
.ob-workspace .ob-resolved-history-disclosure { width:100%; min-width:0; margin:0; padding:0; overflow:hidden; }
.ob-workspace .ob-resolved-history-disclosure > summary { min-height:58px; padding:15px 19px; }
.ob-workspace .ob-resolved-history-disclosure[open] > summary { margin-bottom:0; border-bottom:1px solid var(--border); }
.ob-workspace .ob-resolved-history-body { padding:12px 16px 16px; }
.ob-workspace .ob-resolved-history-body > .content-area { margin:0; padding:0; min-height:0; display:block; }
.ob-workspace .ob-resolved-history-body .resolved-history-card { margin:0 0 9px; padding:12px 15px; min-height:0; }
.ob-workspace .ob-resolved-history-body .resolved-history-card:last-of-type { margin-bottom:0; }
.ob-workspace .ob-resolved-history-body .resolved-history-card .card-header { padding:0; min-height:0; margin:0; }
.ob-workspace .ob-resolved-history-body .resolved-history-card .card-header h3 { margin:0; }
.ob-workspace .ob-resolved-history-body .resolved-history-card .card-header small { display:block; margin-top:3px; }
.ob-workspace .ob-resolved-history-body .resolved-history-summary { margin:8px 0 0; padding:7px 0 0; border-top:1px solid var(--border); }
.ob-workspace .ob-resolved-history-body .resolved-history-card > .accountability-case-details { margin-top:8px; padding:0; }
/* A collapsed sanctions disclosure must be only its heading, never a full-height panel. */
.ob-sanctions-history { display:block; flex:0 0 auto; align-self:auto; height:auto; min-height:0 !important; width:100%; margin:0; padding:0; overflow:hidden; }
.ob-sanctions-history > summary { min-height:58px; padding:15px 19px; }
.ob-sanctions-history:not([open]) > .accountability-sanctions-section { display:none !important; }
.ob-sanctions-history[open] > summary { margin-bottom:0; border-bottom:1px solid var(--border); }
.ob-sanctions-history > .accountability-sanctions-section { display:block; flex:none; width:100%; min-height:0 !important; height:auto; margin:0; padding:12px 15px 15px; }
.ob-sanctions-history .accountability-sanctions-card { margin:0; padding:0; min-height:0; height:auto; border:0; box-shadow:none; }
.ob-sanctions-history .accountability-cases-table { margin:0; }
@media(max-width:1000px) {
 .ob-workspace .ob-summary.is-three, .ob-workspace .ob-summary:not(.is-three) { grid-template-columns:repeat(2,minmax(0,1fr)) !important; }
}
@media(max-width:660px) {
 .ob-workspace .ob-summary.is-three, .ob-workspace .ob-summary:not(.is-three) { grid-template-columns:1fr !important; }
 .ob-workspace .ob-summary-card { height:auto !important; min-height:118px; }
 .ob-workspace .ob-resolved-history-body { padding:10px; }
 .ob-sanctions-history > .accountability-sanctions-section { padding:10px; }
}
/* Final borrower spacing rhythm: no empty grid row in the clear state. */
.ob-workspace { gap: 16px !important; margin: 0 !important; padding-top: 0 !important; padding-bottom: 0 !important; }
.ob-workspace .ob-summary { gap: 14px !important; margin: 0 !important; }
.ob-workspace .ob-summary-card { margin: 0 !important; }
.ob-workspace .ob-resolved-history-disclosure { margin: 0 !important; }
.ob-workspace .ob-resolved-history-disclosure > summary,
.ob-sanctions-history > summary { min-height: 60px !important; padding: 16px 20px !important; }
/* Sanctions follow the self-contained borrower workspace as a separate sibling. */
body:has(.ob-workspace) #sanction-history { margin-top: 16px !important; margin-bottom: 0 !important; }
/* Keep the heading close to the dashboard rather than stacking large generic margins. */
body:has(.ob-workspace) .accountability-heading { margin-bottom: 18px !important; padding-bottom: 0 !important; }
@media (max-width: 660px) {
  .ob-workspace { gap: 12px !important; }
  body:has(.ob-workspace) #sanction-history { margin-top: 12px !important; }
  body:has(.ob-workspace) .accountability-heading { margin-bottom: 14px !important; }
}
/* Borrower dashboard: balanced spacing for active and clear states. */
.ob-workspace { gap: 20px !important; align-content: start !important; }
.ob-workspace .ob-summary { gap: 14px !important; }
.ob-workspace .ob-summary-card { height: 122px !important; min-height: 122px !important; }
.ob-workspace .ob-focused-results {
    display: grid;
    gap: 12px;
    margin: 0 !important;
    padding: 0 !important;
    min-height: 0 !important;
}
.ob-workspace .ob-current-header { padding: 0 !important; margin: 0 !important; align-items: center; }
.ob-workspace .ob-current-header h2 { font-size: 15px; line-height: 1.3; }
.ob-workspace .ob-case-list { gap: 14px; }
.ob-workspace .ob-case-card { padding: 15px 18px !important; }
.ob-workspace .ob-case-identity { gap: 11px; }
.ob-workspace .ob-case-icon { width: 38px; height: 38px; }
.ob-workspace .ob-case-identity h3 { font-size: 15px; line-height: 1.3; }
.ob-workspace .ob-case-summary,
.ob-workspace .ob-linked-restriction,
.ob-workspace .ob-next-action,
.ob-workspace .ob-case-actions { margin-left: 49px; }
.ob-workspace .ob-case-summary { margin-top: 7px !important; }
.ob-workspace .ob-linked-restriction { margin-top: 8px; }
.ob-workspace .ob-next-action {
    margin-top: 11px !important;
    padding: 9px 11px;
    gap: 11px;
    min-height: 37px;
}
.ob-workspace .ob-next-action > strong { font-size: 12px; font-weight: 650; }
.ob-workspace .ob-case-actions { margin-top: 11px !important; }
.ob-workspace .ob-resolved-history-disclosure { margin: 0 !important; }
/* Sanction disclosure is a sibling, not a child of .ob-workspace. */
body:has(.ob-workspace) #sanction-history { margin: 18px 0 0 !important; }
@media(max-width:1000px) {
 .ob-workspace .ob-summary-card { height: 122px !important; }
}
@media(max-width:660px) {
 .ob-workspace { gap: 16px !important; }
 .ob-workspace .ob-summary-card { height: auto !important; min-height: 112px !important; }
 .ob-workspace .ob-case-card { padding: 14px !important; }
 .ob-workspace .ob-case-summary,
 .ob-workspace .ob-linked-restriction,
 .ob-workspace .ob-next-action,
 .ob-workspace .ob-case-actions { margin-left: 0; }
 body:has(.ob-workspace) #sanction-history { margin-top: 16px !important; }
}
/* Only Amount Due uses blue when the outstanding balance is zero. */
.ob-workspace .ob-summary-card.is-blue {
    --ob-from:#38a0f2;
    --ob-mid:#1777dc;
    --ob-to:#0758b8;
    --ob-shadow:rgba(23,119,220,.18);
}
</style>
