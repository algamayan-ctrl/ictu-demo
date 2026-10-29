<style>
.spmu-inventory { --inventory-blue: #0865df; width: 100%; min-width: 0; font-size: 13px; }
.spmu-inventory [hidden] { display: none !important; }
.spmu-inventory-card { padding: 17px 18px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-elevated); }
.spmu-inventory-card label { min-width: 0; margin: 0; display: grid; gap: 8px; color: var(--text-secondary); font-size: 12px; font-weight: 700; }
.spmu-inventory-card input, .spmu-inventory-card select { width: 100%; min-height: 44px; border-radius: 9px; font-size: 12px; }

{{-- /* Availability window */ --}}

{{-- /* Search and category */ --}}
.spmu-inventory-filters { display: grid; grid-template-columns: minmax(0, 1.8fr) repeat(3, minmax(155px, 1fr)); align-items: end; gap: 14px 18px; margin-bottom: 16px; }
.spmu-inventory-filters .search-input-shell input { padding-left: 36px; }

{{-- /* Result summary above the table */ --}}
.spmu-inventory-summary { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px 18px; margin-bottom: 12px; padding: 0 2px; color: var(--text-secondary); font-size: 12px; }
.spmu-inventory-page-size { display: inline-flex; align-items: center; }
.spmu-inventory-page-size select { min-height: 40px; width: auto; min-width: 168px; padding: 8px 36px 8px 13px; border: 1px solid var(--border); border-radius: 9px; background-color: var(--surface-elevated); color: var(--heading); font-size: 12px; font-weight: 650; }

{{-- /* Table */ --}}
.spmu-inventory-table-wrap { border: 1px solid var(--border); border-radius: 12px; background: var(--surface-elevated); }
.spmu-inventory-table-scroll { width: 100%; min-width: 0; overflow-x: auto; }
.spmu-inventory .spmu-inventory-table { width: 100%; min-width: 1140px; margin: 0; border-collapse: collapse; }
.spmu-inventory .spmu-inventory-table th { padding: 13px 14px; border-bottom: 1px solid var(--border); background: var(--table-heading-bg); color: var(--text-secondary); font-size: 10px; font-weight: 750; letter-spacing: .05em; line-height: 1.35; text-transform: uppercase; text-align: left; vertical-align: bottom; }
.spmu-inventory .spmu-inventory-table td { padding: 14px; border-bottom: 1px solid var(--row-border); color: var(--heading); font-size: 12px; line-height: 1.5; vertical-align: middle; }
.spmu-inventory-table tbody tr:last-child td { border-bottom: 0; }
.spmu-inventory-table tbody tr:hover:not(:has(.spmu-inventory-static-empty)) td { background: var(--row-hover); }
.spmu-inventory-table th.is-numeric, .spmu-inventory-table td.is-numeric { text-align: center; }
.spmu-inventory-table th.is-nowrap { white-space: nowrap; }
.spmu-inventory-table th:nth-child(1) { width: 9%; }
.spmu-inventory-table th:nth-child(2) { width: 20%; }
.spmu-inventory-table th:nth-child(7) { width: 10%; }
.spmu-inventory-table th:nth-child(8) { width: 10%; }
.spmu-inventory-table th:nth-child(9) { width: 11%; }
.spmu-inventory-table th:nth-child(10) { width: 11%; }
.spmu-inventory-id { display: inline-flex; align-items: center; justify-content: center; min-height: 28px; padding: 4px 9px; border: 1px solid var(--border); border-radius: 9px; background: var(--surface-subtle); color: var(--heading); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; font-weight: 700; white-space: nowrap; }
.spmu-inventory-item strong { display: block; color: var(--heading); font-size: 12px; font-weight: 700; line-height: 1.4; }
.spmu-inventory-item small { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11px; }
.spmu-inventory-count { font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
.spmu-inventory-states { display: grid; gap: 2px; color: var(--text-secondary); font-size: 11px; }
.spmu-inventory-states strong { color: var(--heading); font-size: 11px; font-weight: 750; }
.spmu-inventory-states span.has-open { color: var(--warning); font-weight: 700; }
.spmu-condition-all-good { display: inline-flex; align-items: center; min-height: 25px; padding: 3px 8px; border: 1px solid var(--success-border); border-radius: 999px; background: var(--success-bg); color: var(--success); font-size: 10.5px; font-weight: 750; white-space: nowrap; }
.spmu-condition-summary { display: grid; gap: 1px; color: var(--text-secondary); font-size: 10.5px; line-height: 1.35; }
.spmu-condition-summary strong { color: var(--heading); font-size: 11px; }
.spmu-condition-summary small { color: var(--warning); font-size: 10.5px; font-weight: 650; }
.spmu-inventory-use { color: var(--text-secondary); font-size: 11px; line-height: 1.45; }
.spmu-inventory-use small { display: block; margin-top: 3px; color: var(--text-muted); }
.spmu-inventory-actions { display: flex; align-items: center; gap: 10px; flex-wrap: nowrap; white-space: nowrap; }
.spmu-inventory .spmu-inventory-actions .table-action { gap: 6px; }
.spmu-inventory.is-inventory-head .spmu-inventory-table td { padding-top: 7px; padding-bottom: 7px; }
.spmu-inventory.is-inventory-head .spmu-inventory-actions { flex-direction: row; align-items: center; gap: 8px; }
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-icon-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    min-height: 28px;
    padding: 4px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    box-shadow: none;
}
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-icon-action:hover,
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-icon-action:focus-visible {
    border: 0;
    background: var(--surface-subtle);
}
.spmu-inventory-static-empty { padding: 34px 16px !important; color: var(--text-muted); text-align: center; }

{{-- /* Footer / pagination */ --}}
.spmu-inventory-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 18px; padding: 15px 18px; border-top: 1px solid var(--border); }
.spmu-inventory-footer > p { margin: 0; color: var(--text-secondary); font-size: 12px; }
.spmu-inventory-pagination-group { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 10px 14px; margin-left: auto; }
.spmu-inventory-pagination { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-left: auto; }
.spmu-inventory-page { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-width: 36px; height: 38px; padding: 6px 11px; border: 1px solid var(--border); border-radius: 9px; background: var(--surface-elevated); color: var(--text-secondary); font: inherit; font-size: 12px; font-weight: 650; line-height: 1; cursor: pointer; }
.spmu-inventory-page:hover:not(:disabled):not(.is-active) { color: var(--inventory-blue); border-color: var(--inventory-blue); }
.spmu-inventory-page.is-active { color: #fff; background: var(--inventory-blue); border-color: var(--inventory-blue); font-weight: 750; }
.spmu-inventory-page:disabled { color: var(--text-soft); background: var(--surface-subtle); cursor: not-allowed; }
.spmu-inventory-page .ui-icon { flex-shrink: 0; }
.spmu-inventory-page-previous { transform: rotate(180deg); }
.spmu-inventory-page-ellipsis { padding: 0 3px; color: var(--text-muted); font-size: 11px; }

{{-- /* No results */ --}}
.spmu-inventory-no-results { display: flex; flex-direction: column; align-items: center; gap: 9px; padding: 40px 20px; color: var(--text-muted); text-align: center; }
.spmu-inventory-no-results strong { color: var(--heading); font-size: 14px; }
.spmu-inventory-no-results p { margin: 0; font-size: 12px; }

html[data-theme="dark"] .spmu-inventory { --inventory-blue: #72b7f4; }
html[data-theme="dark"] .spmu-inventory-page.is-active { color: var(--navy-950); }

@media (max-width: 900px) {
    .spmu-inventory-filters { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 620px) {
    .spmu-inventory-card { padding: 14px; }
    .spmu-inventory-summary { align-items: flex-start; }
    .spmu-inventory-page-size select { width: 100%; min-width: 0; }
    .spmu-inventory-footer { align-items: stretch; padding: 14px; }
    .spmu-inventory-pagination-group { align-items: stretch; justify-content: flex-start; margin-left: 0; }
    .spmu-inventory-pagination { margin-left: 0; }
}
{{-- /* Delete icon and confirmation */ --}}
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-delete-action { color: #c63535; cursor: pointer; font: inherit; }
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-delete-action:hover,
.spmu-inventory.is-inventory-head .spmu-inventory-actions .spmu-inventory-delete-action:focus-visible { background: rgba(198, 53, 53, .10); color: #af2929; }
.spmu-inventory-delete-dialog { width: min(92vw, 480px); max-width: 480px; border: 1px solid var(--border); border-radius: 14px; padding: 24px; background: var(--surface-elevated, #fff); color: var(--heading); box-shadow: 0 20px 56px rgba(0, 0, 0, .20); }
.spmu-inventory-delete-dialog::backdrop { background: rgba(13, 26, 46, .55); }
.spmu-inventory-delete-dialog form { display: grid; gap: 13px; }
.spmu-inventory-delete-dialog h2 { margin: 0; font-size: 19px; font-weight: 750; color: var(--heading); }
.spmu-inventory-delete-dialog p { margin: 0; color: var(--text-secondary); font-size: 13px; line-height: 1.6; }
.spmu-inventory-delete-dialog strong { color: var(--heading); }
.spmu-inventory-delete-dialog .spmu-inventory-delete-warning { padding: 11px 12px; border-radius: 8px; background: var(--surface-subtle); font-size: 12px; }
.spmu-inventory-delete-dialog label { color: var(--heading); font-size: 12px; font-weight: 700; }
.spmu-inventory-delete-dialog label span { color: #c63535; }
.spmu-inventory-delete-dialog textarea { width: 100%; min-height: 88px; border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; background: var(--surface-elevated, #fff); color: var(--heading); font: inherit; font-size: 13px; resize: vertical; }
.spmu-inventory-delete-dialog textarea:focus-visible { outline: 2px solid var(--inventory-blue, #0865df); outline-offset: 2px; }
.spmu-inventory-delete-buttons { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
.spmu-inventory-delete-buttons .button { padding: 10px 18px; min-height: 39px; }
.spmu-inventory-delete-confirm { border: 1px solid #bc2e2e !important; color: white !important; background: #c63535 !important; cursor: pointer; }
.spmu-inventory-delete-confirm:hover { background: #aa2525 !important; }

{{-- /* SPMU ACTION ICON FIX */ --}}

{{-- /* View, Edit, Delete - icon only, no box */ --}}
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action,
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action:is(:hover,:focus,:active) {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 30px !important;
    height: 30px !important;
    min-width: 30px !important;
    min-height: 30px !important;
    padding: 4px !important;
    margin: 0 !important;
    border: none !important;
    border-radius: 0 !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
    color: var(--inventory-blue, #0865df) !important;
    opacity: 1 !important;
    text-decoration: none !important;
    cursor: pointer;
    appearance: none;
}

{{-- /* Delete stays red */ --}}
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-delete-action,
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-delete-action:is(:hover,:focus,:active) {
    color: #c63535 !important;
}

{{-- /* SVG icons always remain visible */ --}}
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action .ui-icon {
    display: block !important;
    width: 16px !important;
    height: 16px !important;
    visibility: visible !important;
    opacity: 1 !important;
    fill: none !important;
    stroke: currentColor !important;
    color: inherit !important;
}

{{-- /* Subtle hover feedback, no outline */ --}}
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action:is(:hover,:focus-visible) .ui-icon {
    transform: scale(1.12) !important;
    visibility: visible !important;
    opacity: 1 !important;
}

{{-- /* Prevent decorative outlines */ --}}
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action::before,
.spmu-inventory.is-inventory-head
.spmu-inventory-actions
.spmu-inventory-icon-action::after {
    content: none !important;
    display: none !important;
}
{{-- /* SPMU COMPACT DELETE MODAL V1 */ --}}

.spmu-inventory-delete-dialog {
    box-sizing: border-box;
    width: min(92vw, 420px);
    max-width: 420px;
    max-height: calc(100dvh - 40px);
    overflow-y: auto;
    padding: 17px 18px;
    border-radius: 12px;
}

.spmu-inventory-delete-dialog form {
    display: grid;
    gap: 9px;
}

.spmu-inventory-delete-dialog h2 {
    margin: 0;
    font-size: 17px;
    line-height: 1.3;
    font-weight: 750;
}

.spmu-inventory-delete-dialog p {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
}

.spmu-inventory-delete-dialog .spmu-inventory-delete-warning {
    padding: 8px 10px;
    border-radius: 7px;
    font-size: 11px;
    line-height: 1.4;
}

.spmu-inventory-delete-dialog label {
    display: inline-flex !important;
    align-items: center;
    gap: 4px;
    margin: 0 !important;
    font-size: 12px;
    line-height: 1.3;
}

.spmu-inventory-delete-dialog label span {
    display: inline !important;
    margin: 0 !important;
}

.spmu-inventory-delete-dialog textarea {
    box-sizing: border-box;
    width: 100%;
    height: 68px;
    min-height: 64px;
    max-height: 130px;
    padding: 9px 10px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.45;
    resize: vertical;
}

.spmu-inventory-delete-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}

.spmu-inventory-delete-buttons .button {
    min-height: 35px;
    padding: 8px 14px;
    font-size: 12px;
    line-height: 1.2;
    border-radius: 7px;
}

@media (max-width: 480px) {
    .spmu-inventory-delete-dialog {
        width: 94vw;
        padding: 16px;
    }
}
</style>
