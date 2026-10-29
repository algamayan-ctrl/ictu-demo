<style>
.inventory-detail-page.is-action-officer { display: flex; flex-direction: column; min-width: 0; }
.inventory-detail-page.is-action-officer > .page-heading,
.inventory-detail-page.is-action-officer > .inventory-admin-summary,
.inventory-detail-page.is-action-officer > .inventory-detail-tabs { flex: 0 0 auto; }
.inventory-detail-page.is-action-officer > [data-inventory-panel="borrowing-history"]:not([hidden]) { display: flex; flex-direction: column; flex: 1 0 auto; }
.is-action-officer .inventory-admin-summary { gap: 0; margin: 0 0 16px; overflow: hidden; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-elevated); box-shadow: var(--shadow-sm); }
.is-action-officer .inventory-admin-summary-item { min-height: 78px; padding: 14px 18px; border: 0; border-right: 1px solid var(--border); border-radius: 0; background: transparent; box-shadow: none; }
.is-action-officer .inventory-admin-summary-item:last-child { border-right: 0; }
.is-action-officer .inventory-summary-icon { display: none; }
.is-action-officer .inventory-summary-copy span { font-size: 11px; letter-spacing: .025em; }
.is-action-officer .inventory-summary-copy strong { font-size: 22px; line-height: 1.3; }
.is-action-officer .inventory-detail-tabs { display: flex; gap: 22px; margin: 0 0 16px; border-bottom: 1px solid var(--border); }
.is-action-officer .inventory-detail-tab { flex: 0 1 auto; min-height: 42px; padding: 10px 0; border: 0; border-bottom: 2px solid transparent; border-radius: 0; color: var(--text-muted); background: transparent; font-size: 12.5px; font-weight: 700; box-shadow: none; transform: none; }
.is-action-officer .inventory-detail-tab::before { display: none; }
.is-action-officer .inventory-detail-tab.is-active { color: var(--interactive); border-bottom-color: var(--interactive); background: transparent; box-shadow: none; }
.is-action-officer .inventory-detail-tab:hover { color: var(--interactive); background: transparent; box-shadow: none; transform: none; }
.is-action-officer .inventory-tab-overview > .card { margin-top: 12px; }
.is-action-officer .sao-item-details { padding: 18px 20px; }
.sao-item-details h2 { margin: 0 0 18px; font-size: 16px; }
.sao-inventory-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 28px; margin: 0; }
.sao-inventory-facts > div { min-width: 0; }
.sao-inventory-facts dt { margin: 0 0 5px; color: var(--text-muted); font-size: 12px; line-height: 1.4; }
.sao-inventory-facts dd { margin: 0; color: var(--heading); font-size: 13px; font-weight: 600; line-height: 1.45; overflow-wrap: anywhere; }
.sao-inventory-description { grid-column: 1 / -1; }
.sao-item-description + .sao-item-facts-row,
.sao-item-facts-row + .sao-item-facts-row { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--border); }
.sao-record-dates { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
.sao-record-dates > summary { color: var(--text-secondary); font-size: 12px; font-weight: 650; cursor: pointer; }
.sao-record-dates > .sao-inventory-facts { margin-top: 14px; }
.is-action-officer .sao-stock-details,
.is-action-officer .sao-current-records { padding: 0; }
.sao-stock-details > summary,
.sao-current-records > summary { padding: 15px 20px; color: var(--heading); font-size: 13px; font-weight: 750; cursor: pointer; }
.sao-stock-details > summary span,
.sao-current-records > summary span { margin-left: 10px; color: var(--text-muted); font-size: 12px; font-weight: 400; }
.sao-stock-details[open] > summary,
.sao-current-records[open] > summary { border-bottom: 1px solid var(--border); }
.sao-stock-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 28px; padding: 18px 20px; }
.sao-stock-detail-grid h3,
.sao-source-groups h3 { margin: 0 0 10px; font-size: 12px; font-weight: 750; }
.sao-stock-detail-row { display: flex; justify-content: space-between; gap: 16px; padding: 9px 0; border-bottom: 1px solid var(--row-border); font-size: 12.5px; }
.sao-stock-detail-row:last-child { border-bottom: 0; }
.sao-stock-detail-grid .meta { margin: 0; }
.sao-source-groups { display: grid; gap: 18px; padding: 18px 20px; }
.sao-source-record { display: flex; align-items: center; gap: 18px; padding: 12px 0; border-bottom: 1px solid var(--row-border); }
.sao-source-record:last-child { border-bottom: 0; }
.sao-source-record > div:first-child { flex: 1 1 auto; min-width: 0; }
.sao-source-record strong { color: var(--heading); font-size: 12.5px; overflow-wrap: anywhere; }
.sao-source-record p,
.sao-source-record small { display: block; margin: 4px 0 0; color: var(--text-muted); font-size: 12px; line-height: 1.4; }
.sao-source-quantity { flex: 0 1 180px; text-align: right; }
.sao-source-record .button { flex-shrink: 0; }
.is-action-officer .sao-stock-card { padding: 0; }
.sao-stock-card-heading { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 18px 20px; }
.sao-stock-card-heading h2 { margin: 0; font-size: 16px; }
.sao-stock-card-heading .meta { margin: 0; }
.sao-stock-card-heading > .button { flex-shrink: 0; }
.sao-stock-card form { margin: 0; }
.sao-stock-card-filters { display: grid; grid-template-columns: minmax(0, 1fr) 220px; gap: 14px; padding: 16px 20px; border-top: 1px solid var(--border); }
.sao-stock-card-filters label { display: grid; gap: 7px; margin: 0; font-size: 12px; font-weight: 650; }
.sao-stock-card-filters input, .sao-stock-card-filters select { min-height: 44px; width: 100%; margin: 0; }
.sao-stock-card-table-wrap { border-top: 1px solid var(--border); border-radius: 0; }
.sao-stock-card-identity { width: 100%; min-width: 860px; table-layout: fixed; border-collapse: collapse; }
.sao-stock-card-identity td { padding: 8px 10px; border: 1px solid var(--border); font-size: 12px; line-height: 1.4; overflow-wrap: anywhere; }
.is-action-officer .sao-stock-card-identity td > strong { display: inline; margin-right: 5px; color: var(--heading); }
.sao-stock-card-identity td:first-child { border-left: 0; }
.sao-stock-card-identity td:last-child { border-right: 0; }
.sao-stock-card-identity tbody tr:last-child > td { border-bottom: 0; }
.sao-stock-card-table { width: 100%; min-width: 860px; table-layout: fixed; border-collapse: collapse; }
.sao-stock-card-table th { padding: 8px 10px; text-align: center; border: 1px solid var(--border); background: var(--surface-subtle); font-size: 11px; line-height: 1.35; white-space: normal; }
.sao-stock-card-table td { padding: 10px; border: 1px solid var(--border); vertical-align: middle; font-size: 12px; line-height: 1.4; overflow-wrap: anywhere; }
.sao-stock-card-table th:first-child, .sao-stock-card-table td:first-child { border-left: 0; }
.sao-stock-card-table th:last-child, .sao-stock-card-table td:last-child { border-right: 0; }
.sao-stock-card-table td:first-child, .sao-stock-card-table td:nth-child(5) { text-align: center; }
.sao-stock-card-table tbody tr:last-child > td { border-bottom: 1px solid var(--border); }
.sao-stock-card-table th:first-child { min-width: 100px; }
.sao-stock-card-table td:nth-child(2) { max-width: 240px; }
.sao-stock-card-table .stock-number { text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; }
.sao-stock-card-table .stock-balance { font-weight: 750; }
.sao-stock-card-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 16px 20px; }
.sao-stock-card-footer > p { margin: 0; }
.sao-stock-card-pagination, .sao-stock-card-pagination nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sao-stock-card-pagination select { width: auto; min-height: 40px; margin: 0; }
.sao-stock-card-pagination [aria-disabled="true"] { opacity: .5; cursor: default; }
.sao-stock-card-warning { margin: 0 20px 16px; padding: 12px; border: 1px solid var(--warning); border-radius: 8px; color: var(--heading); font-size: 12px; }
.is-action-officer .sao-borrowing-history { display: flex; flex-direction: column; flex: 1 0 auto; min-height: 320px; padding: 0; overflow: hidden; }
.sao-history-heading, .sao-history-filters { flex: 0 0 auto; }
#sao-borrowing-history-filters { display: flex; flex-direction: column; flex: 1 0 auto; margin: 0; }
#sao-borrowing-history-results { display: flex; flex-direction: column; flex: 1 0 auto; }
#sao-borrowing-history-results > .sao-history-table-wrap { flex: 0 0 auto; }
.sao-history-heading { padding: 18px 20px; border-bottom: 1px solid var(--border); }
.sao-history-heading h2 { margin: 0; font-size: 16px; }
.sao-history-filters { display: grid; grid-template-columns: minmax(200px, 1fr) 145px 145px 180px; gap: 12px; padding: 16px 20px; }
.sao-history-filters label { display: grid; gap: 7px; margin: 0; min-width: 0; font-size: 12px; font-weight: 650; }
.sao-history-filters input, .sao-history-filters select { width: 100%; min-height: 44px; margin: 0; font-size: 12px; }
.sao-history-error { margin: 0 20px 12px; }
.sao-history-table-wrap { border-top: 1px solid var(--border); border-radius: 0; }
.sao-history-table { width: 100%; min-width: 900px; table-layout: fixed; border-collapse: collapse; }
.sao-history-table th { padding: 11px 8px; background: var(--surface-subtle); border-bottom: 1px solid var(--border); text-align: left; font-size: 10.5px; line-height: 1.35; }
.sao-history-table td { padding: 13px 8px; border-bottom: 1px solid var(--border); vertical-align: top; font-size: 12px; line-height: 1.4; overflow-wrap: anywhere; }
.sao-history-table tbody tr:last-child > td { border-bottom: 1px solid var(--border); }
.sao-history-table td > strong { display: block; color: var(--heading); font-size: 12px; }
.sao-history-table td > small { display: block; margin-top: 4px; color: var(--text-muted); font-size: 11px; }
.sao-history-table th:nth-child(n+2), .sao-history-table td:nth-child(n+2) { text-align: center; }
.sao-history-table .sao-history-quantity { font-weight: 700; font-variant-numeric: tabular-nums; }
.sao-history-status .status-badge { max-width: 100%; padding: 4px 7px; font-size: 10px; white-space: normal; }
.sao-history-status .inventory-history-accountability { margin-top: 5px; font-size: 10.5px; line-height: 1.35; }
.sao-history-table .sao-history-view { min-height: 35px; padding: 8px 10px; font-size: 11px; line-height: 1.2; }
.sao-history-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex: 0 0 auto; flex-wrap: wrap; margin-top: auto; padding: 16px 20px; border-top: 1px solid var(--border); }
.sao-history-footer > p { margin: 0; }
.sao-history-pagination, .sao-history-pagination nav { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sao-history-pagination select { width: auto; min-height: 40px; margin: 0; }
.sao-history-pagination [aria-disabled="true"] { opacity: .5; cursor: default; }
.sao-borrowing-history [aria-busy="true"] { opacity: .6; }
@media (max-width: 1000px) {
    .sao-history-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .sao-history-search, .sao-history-filters label:last-child { grid-column: 1 / -1; }
}
@media (max-width: 800px) {
    .is-action-officer .inventory-admin-summary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .is-action-officer .inventory-admin-summary-item { border-bottom: 1px solid var(--border); }
    .is-action-officer .inventory-admin-summary-item:nth-child(3) { border-right: 0; }
    .is-action-officer .inventory-admin-summary-item:nth-child(n+4) { border-bottom: 0; }
}
@media (max-width: 560px) {
    .sao-history-filters { grid-template-columns: minmax(0, 1fr); }
    .sao-history-search, .sao-history-filters label:last-child { grid-column: auto; }
    .sao-stock-card-heading { align-items: flex-start; flex-direction: column; }
    .sao-stock-card-filters { grid-template-columns: minmax(0, 1fr); }
    .is-action-officer .inventory-admin-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .is-action-officer .inventory-admin-summary-item { padding: 12px 14px; border-bottom: 1px solid var(--border); }
    .is-action-officer .inventory-admin-summary-item:nth-child(3) { border-right: 1px solid var(--border); }
    .is-action-officer .inventory-admin-summary-item:nth-child(even) { border-right: 0; }
    .is-action-officer .inventory-admin-summary-item:nth-child(4) { border-bottom: 1px solid var(--border); }
    .is-action-officer .inventory-admin-summary-item:last-child { grid-column: 1 / -1; border: 0; }
    .is-action-officer .inventory-detail-tabs { gap: 16px; }
    .is-action-officer .inventory-detail-tab { font-size: 12px; }
    .sao-inventory-facts,
    .sao-stock-detail-grid { grid-template-columns: minmax(0, 1fr); }
    .sao-stock-details > summary span,
    .sao-current-records > summary span { display: block; margin: 4px 0 0 14px; }
    .sao-source-record { flex-wrap: wrap; }
    .sao-source-quantity { flex-basis: auto; }
}
</style>
