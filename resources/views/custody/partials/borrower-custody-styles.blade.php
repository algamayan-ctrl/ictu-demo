<style>
/*
|--------------------------------------------------------------------------
| Borrower - My Borrowing detail
|--------------------------------------------------------------------------
|
| One stylesheet for the borrower view of a custody transaction. The page is
| a record, not a dashboard: thin borders, one accent per state, and figures
| that line up so the borrower can check quantities at a glance.
|
| Sections: 1. Stack  2. Status  3. Summary  4. Items  5. Processing
|           6. Early return  7. Responsive
|
*/

/* 0. Back link ------------------------------------------------------------ */

.borrower-custody-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    color: var(--interactive);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.borrower-custody-back:hover,
.borrower-custody-back:focus-visible { text-decoration: underline; }

.borrower-custody-back .ui-icon { transform: rotate(180deg); }

/* 1. Stack ---------------------------------------------------------------- */

.borrower-custody-stack {
    display: grid;
    gap: 20px;
}

.borrower-custody-stack .card { padding: 0; }

.borrower-custody-stack .card-header {
    align-items: center;
    margin: 0;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
}

.borrower-custody-stack .card-header .eyebrow {
    margin: 0 0 2px;
    font-size: 11px;
    letter-spacing: .05em;
}

.borrower-custody-stack .card-header h2 {
    margin: 0;
    font-size: 16px;
    line-height: 1.3;
}

.borrower-card-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: var(--interactive);
}

.borrower-section-note {
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 650;
    white-space: nowrap;
}

/* 2. Status --------------------------------------------------------------- */

/*
 * A single accented line carries the state. No callout box: the state is
 * already the loudest thing on the page and does not need a second frame.
 */
.borrower-custody-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-left: 3px solid var(--interactive);
    border-radius: var(--radius);
}

.borrower-custody-status-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: var(--interactive);
}

.borrower-custody-status-copy { flex: 1 1 auto; }

.borrower-custody-status-fact {
    flex: 0 0 auto;
    min-width: 0;
    padding-left: 22px;
    border-left: 1px solid var(--border);
}

.borrower-custody-status-fact small {
    display: block;
    margin-bottom: 5px;
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .03em;
}

.borrower-custody-status-fact strong {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--heading);
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}

.borrower-custody-status-fact strong .ui-icon {
    flex: 0 0 auto;
    color: var(--interactive);
}

.borrower-custody-status .borrower-custody-status-action { gap: 8px; }

.borrower-custody-status-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}

.borrower-custody-status-actions form { margin: 0; }

.borrower-custody-status.is-success { border-left-color: var(--success); }
.borrower-custody-status.is-warning { border-left-color: var(--warning); }
.borrower-custody-status.is-danger { border-left-color: var(--danger); }

.borrower-custody-status-copy { min-width: 0; }

.borrower-custody-status-copy h2 {
    margin: 0;
    color: var(--heading);
    font-size: 17px;
    font-weight: 700;
    line-height: 1.3;
}

.borrower-custody-status-copy p {
    max-width: 760px;
    margin: 5px 0 0;
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.5;
}

.borrower-custody-status-key {
    color: var(--text-secondary);
    font-weight: 700;
}

.borrower-custody-status .button { flex: 0 0 auto; }

/* 3. Summary -------------------------------------------------------------- */

.borrower-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.borrower-summary-fact {
    min-width: 0;
    padding: 13px 20px;
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

/* Three evenly sized facts per row on desktop. The grid draws internal
   separators without relying on a specific number of available facts. */
.borrower-summary-grid { gap: 1px; background: var(--border); }
.borrower-summary-fact { border: 0; background: var(--surface-elevated); }
.borrower-summary-fact:nth-child(3n + 1):last-child { grid-column: span 3; }
.borrower-summary-fact:nth-child(3n + 1):nth-last-child(2),
.borrower-summary-fact:nth-child(3n + 2):last-child { grid-column: span 1; }

.borrower-summary-fact small {
    display: block;
    margin-bottom: 4px;
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.borrower-summary-fact strong {
    display: block;
    color: var(--heading);
    font-size: 13.5px;
    font-weight: 700;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.borrower-summary-fact span {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 11px;
    line-height: 1.35;
}

/* 4. Items ---------------------------------------------------------------- */

/*
 * The table is as long as the borrowing is. No fixed height and no inner
 * scrollbar: a borrower checking quantities should not have to scroll a
 * box inside a page.
 */
.borrower-items-table th,
.borrower-items-table td { padding: 10px 20px; }

.borrower-items-table th {
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    white-space: nowrap;
}

.borrower-items-table td { font-size: 13px; }

.borrower-items-table td strong {
    color: var(--heading);
    font-weight: 650;
    overflow-wrap: anywhere;
}

.borrower-items-table td small {
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 11px;
}

/* Quantities read as a column of figures. */
.borrower-items-table th:not(:first-child),
.borrower-items-table td:not(:first-child) {
    width: 14%;
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.borrower-items-table td.is-quantity strong {
    color: var(--interactive);
    font-size: 13.5px;
    font-weight: 750;
}

.borrower-items-table td.is-muted { color: var(--text-muted); }

/* 5. Processing ----------------------------------------------------------- */

.borrower-processing-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 16px;
    align-items: center;
    padding: 13px 20px;
    border-bottom: 1px solid var(--border);
}

.borrower-processing-row:last-child { border-bottom: 0; }

.borrower-processing-copy { min-width: 0; }

.borrower-processing-copy strong {
    display: block;
    color: var(--heading);
    font-size: 13px;
    font-weight: 700;
}

.borrower-processing-copy small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.45;
}

/* 6. Early return --------------------------------------------------------- */

/*
 * Optional coordination, so it stays folded away until asked for. <details>
 * carries the keyboard behaviour and expanded state natively.
 */
.borrower-early-return { padding: 0; }

.borrower-early-return-summary {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 15px 20px;
    cursor: pointer;
    list-style: none;
}

.borrower-early-return-copy { flex: 1 1 auto; min-width: 0; }

.borrower-early-return-summary::-webkit-details-marker { display: none; }

.borrower-early-return-summary:focus-visible {
    outline: 0;
    border-radius: var(--radius);
    box-shadow: var(--focus-ring);
}

.borrower-early-return-summary strong {
    display: block;
    color: var(--heading);
    font-size: 14px;
    font-weight: 700;
}

.borrower-early-return-summary small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12.5px;
}

.borrower-early-return-toggle {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    flex: 0 0 auto;
    color: var(--interactive);
    font-size: 12.5px;
    font-weight: 700;
}

.borrower-early-return-toggle .ui-icon { transition: transform 160ms ease; }

.borrower-early-return .is-close,
.borrower-early-return[open] .is-open { display: none; }
.borrower-early-return[open] .is-close { display: inline; }
.borrower-early-return[open] .borrower-early-return-toggle .ui-icon { transform: rotate(180deg); }

.borrower-early-return-body {
    display: grid;
    gap: 14px;
    padding: 16px 20px 18px;
    border-top: 1px solid var(--border);
}

.borrower-early-return-body label {
    display: grid;
    gap: 5px;
    margin: 0;
    color: var(--text-secondary);
    font-size: 12.5px;
    font-weight: 700;
}

.borrower-early-return-body input,
.borrower-early-return-body textarea {
    margin: 0;
    font-weight: 400;
}

.borrower-early-return-body textarea { min-height: 78px; resize: vertical; }

.borrower-early-return-body .meta {
    margin: 0;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.5;
}

.borrower-early-return-outstanding {
    width: 100%;
    min-width: 0;
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow-x: auto;
}

.borrower-early-return-outstanding table {
    width: 100%;
    min-width: 480px;
    margin: 0;
    border-collapse: collapse;
    table-layout: fixed;
}

.borrower-early-return-outstanding th,
.borrower-early-return-outstanding td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    font-size: 12.5px;
    line-height: 1.5;
    text-align: left;
    vertical-align: middle;
}

.borrower-early-return-outstanding th + th,
.borrower-early-return-outstanding td + td { border-left: 1px solid var(--border); }

.borrower-early-return-outstanding th {
    color: var(--text-secondary);
    background: var(--table-heading-bg);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.borrower-early-return-outstanding th:first-child { width: 60%; }
.borrower-early-return-outstanding th:nth-child(2) { width: 16%; }
.borrower-early-return-outstanding td { background: var(--surface-elevated); }
.borrower-early-return-outstanding td strong { color: var(--heading); font-weight: 650; overflow-wrap: anywhere; }
.borrower-early-return-outstanding td:nth-child(2) { color: var(--text-muted); overflow-wrap: anywhere; }
.borrower-early-return-outstanding tbody tr:last-child td { border-bottom: 0; }
.borrower-early-return-outstanding td.is-quantity { color: var(--heading); font-weight: 700; }

.borrower-early-return-outstanding th:last-child,
.borrower-early-return-outstanding td:last-child {
    width: 24%;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.borrower-early-return-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Active coordination notice */
.borrower-early-return-notice {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 13px 20px;
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-left: 3px solid var(--info);
    border-radius: var(--radius);
}

.borrower-early-return-notice strong {
    display: block;
    color: var(--heading);
    font-size: 13px;
    font-weight: 700;
}

.borrower-early-return-notice small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.45;
}

/* 7. Responsive ----------------------------------------------------------- */

@media (max-width: 1050px) {
    .borrower-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .borrower-summary-fact:nth-child(3n + 1):last-child { grid-column: span 2; }
    .borrower-summary-fact:nth-child(3n + 1):nth-last-child(2),
    .borrower-summary-fact:nth-child(3n + 2):last-child { grid-column: auto; }
}

@media (max-width: 700px) {
    .borrower-custody-status,
    .borrower-early-return-summary,
    .borrower-early-return-notice {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .borrower-custody-status-fact {
        padding-left: 0;
        border-left: 0;
    }

    .borrower-custody-status-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .borrower-summary-grid { grid-template-columns: 1fr; }
    .borrower-summary-fact,
    .borrower-summary-fact:nth-child(3n + 1):last-child { grid-column: auto; }

    .borrower-processing-row {
        grid-template-columns: 1fr;
        justify-items: start;
        gap: 9px;
    }

    .borrower-items-table th,
    .borrower-items-table td { padding: 10px 14px; }
}

/* ==========================================================
   BORROWER TRANSACTION TIMELINE STYLE START
   ========================================================== */

.borrower-activity-list {
    display: grid;
}

.borrower-activity-row {
    display: grid;
    grid-template-columns: 14px minmax(0, 1fr) auto;
    gap: 12px;
    align-items: start;
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
}

.borrower-activity-row:last-child {
    border-bottom: 0;
}

.borrower-activity-marker {
    width: 9px;
    height: 9px;
    margin-top: 5px;
    border: 2px solid var(--surface);
    border-radius: 999px;
    background: var(--interactive);
    outline: 1px solid var(--border);
}

.borrower-activity-copy {
    min-width: 0;
}

.borrower-activity-copy strong {
    display: block;
    color: var(--heading);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.35;
}

.borrower-activity-copy small {
    display: block;
    margin-top: 2px;
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.45;
}

.borrower-activity-time {
    color: var(--text-muted);
    font-size: 11.5px;
    font-weight: 650;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
    white-space: nowrap;
}

@media (max-width: 680px) {
    .borrower-activity-row {
        grid-template-columns: 14px minmax(0, 1fr);
    }

    .borrower-activity-time {
        grid-column: 2;
        text-align: left;
        white-space: normal;
    }
}

/* ==========================================================
   BORROWER TRANSACTION TIMELINE STYLE END
   ========================================================== */

/* BORROWER TIMELINE DISCLOSURE STYLE START */

.borrower-activity-disclosure {
    overflow: hidden;
}

.borrower-activity-summary {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 16px;
    width: 100%;
    cursor: pointer;
    list-style: none;
    user-select: none;
}

.borrower-activity-summary::-webkit-details-marker {
    display: none;
}

.borrower-activity-summary::marker {
    display: none;
    content: "";
}

.borrower-activity-summary-copy {
    min-width: 0;
}

.borrower-activity-summary-copy h2 {
    margin-bottom: 0;
}

.borrower-activity-summary-side {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 10px;
}

.borrower-activity-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    transition: transform .18s ease;
}

.borrower-activity-disclosure[open] .borrower-activity-chevron {
    transform: rotate(180deg);
}

.borrower-activity-summary:hover {
    background: var(--surface-hover);
}

.borrower-activity-summary:focus-visible {
    outline: 2px solid var(--interactive);
    outline-offset: -2px;
}

.borrower-activity-disclosure:not([open]) .borrower-activity-summary {
    border-bottom: 0;
}

.borrower-activity-disclosure[open] .borrower-activity-list {
    border-top: 1px solid var(--border);
}

@media (max-width: 560px) {
    .borrower-activity-summary {
        gap: 10px;
    }

    .borrower-activity-summary-side {
        gap: 7px;
    }

    .borrower-activity-summary .borrower-section-note {
        font-size: 11px;
    }
}

/* BORROWER TIMELINE DISCLOSURE STYLE END */

/* ==========================================================
   BORROWER TRANSACTION DOCUMENT HEADER START
   ========================================================== */

.borrower-document-card .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.borrower-document-card .card-header > svg,
.borrower-document-card .card-header > .icon {
    flex: 0 0 auto;
}

.borrower-document-left-title {
    flex: 0 1 auto;
    margin-right: auto;
    font-size: 17px;
    font-weight: 700;
    line-height: 1.25;
    color: var(--text);
}

.borrower-document-card .card-header > :last-child {
    margin-left: auto;
}

@media (max-width: 680px) {

    .borrower-document-card .card-header {
        flex-wrap: wrap;
        align-items: flex-start;
    }

    .borrower-document-left-title {
        flex: 1 1 auto;
    }
}

/* ==========================================================
   BORROWER TRANSACTION DOCUMENT HEADER END
   ========================================================== */
</style>
