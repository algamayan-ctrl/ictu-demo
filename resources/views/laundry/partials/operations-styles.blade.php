<style>
.laundry-operations {
    --laundry-blue: #0866df;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    color: var(--text);
    font-size: 13px;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.laundry-operations [hidden] { display: none !important; }
.laundry-operations .laundry-operations-heading { column-gap: 20px; margin-bottom: 20px; }
.laundry-operations-heading .eyebrow { margin-bottom: 9px; font-size: 9.5px; letter-spacing: .1em; }
.laundry-operations .laundry-operations-heading h1 { margin: 0 0 8px; font-size: clamp(21px, 1.65vw, 25px); }
.laundry-operations .laundry-operations-heading > div > p:last-child { font-size: 12.5px; line-height: 1.4; }
.laundry-operations .card { border-radius: 10px; }
.laundry-operations .laundry-flow-card {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    padding: 0;
    margin: 0 0 14px;
    overflow: visible;
}
.laundry-flow-body { padding: 18px 20px 12px; }
.laundry-operations #laundry-flow-title { margin: 0; font-size: 16px; font-weight: 750; }
.laundry-flow-rail {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    width: 100%;
    min-width: 0;
    margin: 20px 0 0;
    padding: 0;
    list-style: none;
}
.laundry-flow-step { position: relative; display: flex; min-width: 0; flex-direction: column; align-items: center; gap: 13px; text-align: center; }
.laundry-flow-step:not(:last-child)::after {
    content: "";
    position: absolute;
    top: 28px;
    left: calc(50% + 38px);
    width: calc(100% - 76px);
    height: 2px;
    background: #3885ec;
}
.laundry-flow-step.is-emphasized::after { background: var(--border-strong); }
.laundry-flow-marker { position: relative; display: grid; place-items: center; width: 58px; height: 58px; flex-shrink: 0; border: 1px solid var(--border); border-radius: 50%; color: var(--text-muted); background: var(--surface-subtle, #f8fafc); }
.laundry-flow-number { position: absolute; bottom: -3px; right: -3px; display: grid; place-items: center; width: 22px; height: 22px; border: 2px solid var(--surface-elevated); border-radius: 50%; background: #1169cb; color: #fff; font-size: 11px; font-weight: 750; line-height: 1; }
.laundry-flow-step > strong { max-width: 170px; padding: 0 4px; color: var(--text-muted); font-size: 12px; font-weight: 750; line-height: 1.5; }
.laundry-flow-step.is-emphasized .laundry-flow-marker { border-color: var(--laundry-blue); background: var(--info-bg); color: var(--navy-800); box-shadow: 0 0 0 3px color-mix(in srgb, var(--info-bg) 55%, transparent); }
.laundry-flow-step.is-emphasized > strong { color: var(--laundry-blue); }
.laundry-flow-step.is-internal .laundry-flow-marker { color: var(--heading); }
.laundry-flow-step.is-internal .laundry-flow-number { background: #8595ab; }
.laundry-flow-step { cursor: help; outline: none; }
.laundry-flow-step:focus-visible .laundry-flow-marker { outline: 2px solid var(--laundry-blue); outline-offset: 3px; }
.laundry-flow-tooltip {
    position: absolute;
    z-index: 30;
    top: calc(100% + 10px);
    left: 50%;
    width: min(260px, calc(100vw - 44px));
    margin: 0;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 7px;
    color: var(--text-secondary);
    background: var(--surface-elevated);
    box-shadow: var(--shadow-md, 0 10px 24px rgba(7, 27, 53, .12));
    font-size: 11px;
    line-height: 1.5;
    text-align: left;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translate(-50%, 4px);
    transition: opacity .14s ease, transform .14s ease, visibility .14s ease;
}
.laundry-flow-tooltip::before {
    content: "";
    position: absolute;
    top: -6px;
    left: calc(50% - 6px);
    width: 10px;
    height: 10px;
    border-top: 1px solid var(--border);
    border-left: 1px solid var(--border);
    background: var(--surface-elevated);
    transform: rotate(45deg);
}
.laundry-flow-tooltip strong { color: var(--heading); }
.laundry-flow-step:hover .laundry-flow-tooltip,
.laundry-flow-step:focus .laundry-flow-tooltip,
.laundry-flow-step:focus-within .laundry-flow-tooltip {
    opacity: 1;
    visibility: visible;
    transform: translate(-50%, 0);
}
.laundry-flow-step:first-child .laundry-flow-tooltip { left: 0; transform: translate(0, 4px); }
.laundry-flow-step:first-child:hover .laundry-flow-tooltip,
.laundry-flow-step:first-child:focus .laundry-flow-tooltip,
.laundry-flow-step:first-child:focus-within .laundry-flow-tooltip { transform: translate(0, 0); }
.laundry-flow-step:first-child .laundry-flow-tooltip::before { left: 27px; }
.laundry-flow-step:last-child .laundry-flow-tooltip { right: 0; left: auto; transform: translate(0, 4px); }
.laundry-flow-step:last-child:hover .laundry-flow-tooltip,
.laundry-flow-step:last-child:focus .laundry-flow-tooltip,
.laundry-flow-step:last-child:focus-within .laundry-flow-tooltip { transform: translate(0, 0); }
.laundry-flow-step:last-child .laundry-flow-tooltip::before { right: 27px; left: auto; }
.laundry-flow-footer { display: flex; align-items: flex-start; gap: 9px; margin: 0; padding: 12px 18px; border-top: 1px solid var(--row-border); color: var(--text-muted); font-size: 11px; line-height: 1.5; }
.laundry-flow-footer > .ui-icon { flex-shrink: 0; }
.laundry-operations .laundry-toolbar { display: grid; grid-template-columns: minmax(0, 2.4fr) minmax(0, .95fr) minmax(0, .9fr); gap: 14px; align-items: end; padding: 16px 18px; margin-bottom: 10px; }
.laundry-toolbar label { min-width: 0; margin: 0; color: var(--heading); font-size: 12px; }
.laundry-toolbar .search-input-shell, .laundry-toolbar select { margin-top: 7px; }
.laundry-operations .laundry-toolbar input, .laundry-operations .laundry-toolbar select { min-height: 40px; width: 100%; font-size: 12px; }
.laundry-filter-scope { grid-column: 1 / -1; margin: -2px 0 0; color: var(--text-muted); font-size: 11px; }
.laundry-operations .laundry-cases-card { padding: 16px 18px 4px; }
.laundry-cases-heading { margin-bottom: 14px; }
.laundry-operations .laundry-cases-heading h2 { margin: 0 0 6px; font-size: 17px; font-weight: 750; }
.laundry-cases-heading p { margin: 0; color: var(--text-muted); font-size: 12px; }
.laundry-table-wrap { width: 100%; min-width: 0; overflow-x: auto; }
.laundry-operations .laundry-cases-table { width: 100%; min-width: 980px; margin: 0; }
.laundry-operations .laundry-cases-table th { padding: 10px 8px; color: var(--heading); background: var(--table-heading-bg); font-size: 11px; font-weight: 750; letter-spacing: 0; text-transform: none; white-space: nowrap; }
.laundry-operations .laundry-cases-table td { padding: 11px 8px; border-bottom: 1px solid var(--row-border); font-size: 12px; line-height: 1.5; vertical-align: middle; }
.laundry-cases-table th:first-child { width: 15%; }
.laundry-cases-table th:nth-child(2) { width: 16%; }
.laundry-cases-table th:nth-child(3) { width: 17%; }
.laundry-cases-table th:nth-child(4) { width: 14%; }
.laundry-cases-table th:nth-child(5) { width: 19%; }
.laundry-cases-table td > strong, .laundry-cases-table td > small { display: block; }
.laundry-cases-table td > strong { color: var(--heading); font-size: 12px; font-weight: 650; }
.laundry-cases-table td > small { margin-top: 1px; font-size: 12px; }
.laundry-request-link { color: var(--laundry-blue); font-weight: 750; text-decoration: none; }
.laundry-request-link:hover { text-decoration: underline; }
.laundry-returned-date { white-space: nowrap; }
.laundry-operations .laundry-cases-table .status-badge { border: 0; border-radius: 999px; padding: 4px 10px; font-size: 10px; white-space: nowrap; }
.laundry-operations [data-status="FOR_LAUNDRY"] .status-badge { color: #a74a05; background: #fff0e2; }
.laundry-operations [data-status="TURNED_OVER_TO_LAUNDRY"] .status-badge { color: #075cd7; background: #e6efff; }
.laundry-operations .button.laundry-view-link { white-space: nowrap; }
.laundry-pagination { padding: 13px 0 9px; }
.laundry-completed-block { margin-top: 18px; scroll-margin-top: 86px; }
.laundry-completed-block .laundry-completed-toolbar { margin-bottom: 10px; }
.laundry-completed-card { margin-bottom: 0; }
.laundry-inline-empty { display: flex; min-height: 104px; align-items: center; justify-content: center; gap: 14px; padding: 14px 10px 20px; color: var(--text-muted); text-align: left; }
.laundry-inline-empty .laundry-empty-illustration { width: 62px; height: 62px; margin: 0; flex-shrink: 0; }
.laundry-inline-empty > .ui-icon { flex-shrink: 0; color: var(--text-muted); }
.laundry-inline-empty strong { display: block; margin-bottom: 4px; color: var(--heading); font-size: 13px; font-weight: 750; }
.laundry-inline-empty p { margin: 0; color: var(--text-muted); font-size: 12px; line-height: 1.5; }
.laundry-operations .laundry-completed-table { min-width: 860px; }
.laundry-completed-table th:nth-child(1) { width: 19%; }
.laundry-completed-table th:nth-child(2) { width: 22%; }
.laundry-completed-table th:nth-child(3) { width: 10%; }
.laundry-completed-table th:nth-child(4) { width: 17%; }
.laundry-completed-table th:nth-child(5) { width: 20%; }
.laundry-completed-table th:nth-child(6) { width: 12%; }
.laundry-completed-date time, .laundry-completed-date time > span { display: block; }
.laundry-completed-date time { white-space: nowrap; }
.laundry-outcomes { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 6px; }
.laundry-outcome-badge { display: inline-block; width: fit-content; max-width: 170px; padding: 4px 8px; border: 1px solid transparent; border-radius: 6px; font-size: 10px; line-height: 1.45; }
.laundry-outcome-badge.is-available { color: #087a34; background: #eef9f1; border-color: #d2ecd9; }
.laundry-outcome-badge.is-accountability { color: #c1610a; background: #fff7ed; border-color: #ffe2bf; }
.laundry-outcome-badge.is-neutral { color: var(--text-muted); background: var(--surface-subtle); border-color: var(--border); }
.laundry-filter-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 26px 12px; text-align: center; color: var(--text-muted); }
.laundry-filter-empty strong { color: var(--heading); font-size: 14px; }
.laundry-filter-empty p { margin: 0; }
html[data-theme="dark"] .laundry-operations { --laundry-blue: #72b7f4; }
html[data-theme="dark"] .laundry-flow-step.is-emphasized .laundry-flow-marker { color: var(--laundry-blue); }
html[data-theme="dark"] .laundry-operations [data-status="FOR_LAUNDRY"] .status-badge { color: var(--warning); background: var(--warning-bg); }
html[data-theme="dark"] .laundry-operations [data-status="TURNED_OVER_TO_LAUNDRY"] .status-badge { color: var(--info); background: var(--info-bg); }
html[data-theme="dark"] .laundry-outcome-badge.is-available { color: var(--success); background: var(--success-bg); border-color: var(--success-border); }
html[data-theme="dark"] .laundry-outcome-badge.is-accountability { color: var(--warning); background: var(--warning-bg); border-color: var(--warning-border); }
@media (max-width: 1000px) {
    .laundry-operations .laundry-toolbar { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, .8fr); }
}
@media (max-width: 700px) {
    .laundry-operations .laundry-operations-heading { align-items: stretch; gap: 12px; }
    .laundry-flow-body { padding: 16px 12px 12px; }
    .laundry-flow-marker { width: 46px; height: 46px; }
    .laundry-flow-marker > svg { width: 23px; height: 23px; }
    .laundry-flow-number { width: 20px; height: 20px; font-size: 10px; }
    .laundry-flow-step:not(:last-child)::after { top: 23px; left: calc(50% + 28px); width: calc(100% - 56px); }
    .laundry-flow-step > strong { font-size: 11px; }
    .laundry-operations .laundry-toolbar { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
    .laundry-toolbar > label:first-child { grid-column: 1 / -1; }
    .laundry-operations .laundry-cases-card { padding-right: 12px; padding-left: 12px; }
    .laundry-inline-empty { justify-content: flex-start; }
}
@media (max-width: 400px) {
    .laundry-flow-marker { width: 40px; height: 40px; }
    .laundry-flow-step:not(:last-child)::after { top: 20px; left: calc(50% + 24px); width: calc(100% - 48px); }
    .laundry-flow-step > strong { padding: 0 2px; font-size: 10px; }
    .laundry-operations .laundry-toolbar { grid-template-columns: minmax(0, 1fr); }
}
/* Compact unified Laundry Operations workspace */
.laundry-operations .laundry-unified-workspace { padding: 0; overflow: hidden; margin: 0; }
.laundry-operations .laundry-tabs { display: flex; gap: 6px; padding: 12px 15px 0; border-bottom: 1px solid var(--row-border); }
.laundry-operations .laundry-tab { display: inline-flex; align-items: center; gap: 9px; border: 0; border-bottom: 2px solid transparent; border-radius: 0; background: transparent; color: var(--text-muted); padding: 10px 14px; font: inherit; font-weight: 700; cursor: pointer; }
.laundry-operations .laundry-tab:hover { color: var(--laundry-blue); background: var(--surface-subtle, #f8fafc); }
.laundry-operations .laundry-tab.is-selected { color: var(--laundry-blue); border-bottom-color: var(--laundry-blue); }
.laundry-operations .laundry-tab:focus-visible { outline: 2px solid var(--laundry-blue); outline-offset: -3px; }
.laundry-operations .laundry-tab-count { min-width: 23px; padding: 2px 7px; border-radius: 12px; background: var(--surface-subtle, #edf1f6); color: var(--text-muted); font-size: 11px; text-align: center; }
.laundry-operations .laundry-tab.is-selected .laundry-tab-count { background: var(--info-bg, #e7f0ff); color: var(--laundry-blue); }
.laundry-operations .laundry-unified-workspace .laundry-toolbar { border: 0; border-radius: 0; box-shadow: none; margin: 0; padding: 12px 18px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-card { border: 0; border-radius: 0; box-shadow: none; padding: 10px 18px 8px; margin: 0; }
.laundry-operations .laundry-unified-workspace .laundry-cases-heading { margin-bottom: 9px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-heading h2 { font-size: 14px; margin-bottom: 3px; }
.laundry-operations .laundry-unified-workspace .laundry-completed-block { margin: 0; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table td { padding-top: 9px; padding-bottom: 9px; }
.laundry-operations .laundry-operations-heading { margin-bottom: 14px; }
@media (max-width: 700px) { .laundry-operations .laundry-tabs { padding: 7px 9px 0; } .laundry-operations .laundry-tab { padding: 10px 12px; } }
/* LAUNDRY_SPACING_FIX_START */
/* Scoped presentation tweaks only: preserve the existing form, tab, and pagination logic. */
.laundry-operations .laundry-unified-workspace .laundry-tabs {
    gap: 8px;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--row-border);
}
.laundry-operations .laundry-unified-workspace .laundry-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 36px;
    padding: 7px 13px;
    border: 1px solid var(--border);
    border-radius: 7px;
    color: var(--text-secondary);
    background: var(--surface-elevated);
    font-size: 12px;
    font-weight: 650;
    line-height: 1.35;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.laundry-operations .laundry-unified-workspace .laundry-tab:hover {
    border-color: var(--laundry-blue);
    color: var(--laundry-blue);
    background: var(--surface-subtle, #f8fafc);
}
.laundry-operations .laundry-unified-workspace .laundry-tab.is-selected {
    border-color: var(--border-strong, #b7cde4);
    background: var(--info-bg, #eaf3fd);
    color: var(--heading);
}
.laundry-operations .laundry-unified-workspace .laundry-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    min-height: 19px;
    padding: 1px 6px;
    font-size: 10.5px;
    line-height: 1;
}
.laundry-operations .laundry-unified-workspace .laundry-toolbar {
    grid-template-columns: minmax(0, 2.4fr) minmax(180px, .95fr) minmax(175px, .9fr);
    align-items: end;
    gap: 12px;
    padding: 14px 16px 12px;
}
.laundry-operations .laundry-unified-workspace .laundry-toolbar label {
    display: block;
    font-size: 12px;
    line-height: 1.35;
    font-weight: 650;
}
.laundry-operations .laundry-unified-workspace .laundry-toolbar .search-input-shell,
.laundry-operations .laundry-unified-workspace .laundry-toolbar select {
    margin-top: 6px;
}
.laundry-operations .laundry-unified-workspace .laundry-toolbar input,
.laundry-operations .laundry-unified-workspace .laundry-toolbar select {
    height: 39px;
    min-height: 39px;
    border-radius: 7px;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-card {
    padding: 8px 16px 12px;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-heading {
    margin-bottom: 10px;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-heading h2 {
    margin: 0 0 4px;
    font-size: 15px;
    line-height: 1.4;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-heading p {
    line-height: 1.4;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-table th {
    padding: 10px 10px;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-table td {
    padding: 10px 10px;
}
.laundry-operations .laundry-unified-workspace .laundry-view-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 11px;
    border-radius: 7px;
    text-decoration: none;
}
@media (max-width: 950px) {
    .laundry-operations .laundry-unified-workspace .laundry-toolbar {
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar > label:last-child { grid-column: 1 / -1; }
}
@media (max-width: 700px) {
    .laundry-operations .laundry-unified-workspace .laundry-tabs { padding: 9px 12px; }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar { grid-template-columns: 1fr; gap: 10px; padding: 12px; }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar > label:last-child { grid-column: auto; }
    .laundry-operations .laundry-unified-workspace .laundry-cases-card { padding: 10px 12px; }
}
/* LAUNDRY_SPACING_FIX_END */
/* LAUNDRY_WINDOW_FIT_START */
/* Keep all Laundry table columns inside the available content width on desktop. */
.laundry-operations, .laundry-operations .laundry-unified-workspace,
.laundry-operations [data-laundry-panel],
.laundry-operations .laundry-cases-card,
.laundry-operations .laundry-table-wrap { min-width: 0; max-width: 100%; box-sizing: border-box; }
.laundry-operations .laundry-unified-workspace .laundry-table-wrap { overflow-x: auto; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table,
.laundry-operations .laundry-unified-workspace .laundry-completed-table {
    width: 100%; min-width: 0; max-width: 100%;
    table-layout: fixed; border-collapse: collapse;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-table th,
.laundry-operations .laundry-unified-workspace .laundry-cases-table td {
    box-sizing: border-box; padding: 10px 7px;
    overflow-wrap: anywhere; word-break: normal;
}
.laundry-operations .laundry-unified-workspace .laundry-cases-table th { white-space: normal; line-height: 1.35; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table td { line-height: 1.45; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table td:last-child,
.laundry-operations .laundry-unified-workspace .laundry-cases-table th:last-child { text-align: center; }
.laundry-operations .laundry-unified-workspace .laundry-view-link { max-width: 100%; white-space: normal; text-align: center; }
/* Seven columns: proportionate widths, including room for Actions. */
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(1) { width: 16%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(2) { width: 16%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(3) { width: 14%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(4) { width: 17%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(5) { width: 16%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(6) { width: 11%; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) th:nth-child(7) { width: 10%; }
/* Six columns: Completed cases. */
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(1) { width: 19%; }
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(2) { width: 21%; }
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(3) { width: 9%; }
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(4) { width: 17%; }
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(5) { width: 22%; }
.laundry-operations .laundry-unified-workspace .laundry-completed-table th:nth-child(6) { width: 12%; }
/* Constrain the filter bar without losing input field usability. */
.laundry-operations .laundry-unified-workspace .laundry-toolbar { min-width: 0; grid-template-columns: minmax(0,2.2fr) minmax(0,0.9fr) minmax(0,0.9fr); }
.laundry-operations .laundry-unified-workspace .laundry-toolbar > label { min-width: 0; }
@media (max-width: 1150px) {
    .laundry-operations .laundry-unified-workspace .laundry-cases-table th,
    .laundry-operations .laundry-unified-workspace .laundry-cases-table td { font-size: 11px; padding: 9px 5px; }
    .laundry-operations .laundry-unified-workspace .laundry-view-link { padding: 6px 7px; font-size: 11px; }
}
@media (max-width: 900px) {
    .laundry-operations .laundry-unified-workspace .laundry-toolbar { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar > label:last-child { grid-column: 1 / -1; }
    /* At narrow widths, keep cells readable; scroll the TABLE only rather than cut it. */
    .laundry-operations .laundry-unified-workspace .laundry-cases-table:not(.laundry-completed-table) { min-width: 760px; }
    .laundry-operations .laundry-unified-workspace .laundry-completed-table { min-width: 680px; }
}
@media (max-width: 700px) {
    .laundry-operations .laundry-unified-workspace .laundry-toolbar { grid-template-columns: minmax(0,1fr); }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar > label:last-child { grid-column: auto; }
}
/* LAUNDRY_WINDOW_FIT_END */
/* LAUNDRY_VERTICAL_FIT_START */
/* Content determines its own height: never stretch a one-record panel to viewport height. */
.laundry-operations {
    flex: 0 0 auto;
    height: auto;
    min-height: 0;
    max-height: none;
    overflow: visible;
}
.laundry-operations .laundry-unified-workspace,
.laundry-operations [data-laundry-panel],
.laundry-operations .laundry-cases-card {
    flex: 0 0 auto;
    height: auto;
    min-height: 0;
    max-height: none;
}
.laundry-operations .laundry-operations-heading {
    margin-bottom: 12px;
    gap: 8px;
}
.laundry-operations .laundry-operations-heading .eyebrow { margin-bottom: 5px; }
.laundry-operations .laundry-operations-heading h1 { margin-bottom: 5px; line-height: 1.2; }
.laundry-operations .laundry-operations-heading > div > p:last-child { margin-bottom: 0; }
.laundry-operations .laundry-unified-workspace .laundry-tabs {
    padding: 8px 14px;
    gap: 7px;
}
.laundry-operations .laundry-unified-workspace .laundry-tab { min-height: 35px; padding: 6px 12px; }
.laundry-operations .laundry-unified-workspace .laundry-toolbar {
    margin: 0;
    padding: 10px 14px 8px;
    gap: 10px;
}
.laundry-operations .laundry-unified-workspace .laundry-toolbar .search-input-shell,
.laundry-operations .laundry-unified-workspace .laundry-toolbar select { margin-top: 5px; }
.laundry-operations .laundry-unified-workspace .laundry-toolbar input,
.laundry-operations .laundry-unified-workspace .laundry-toolbar select { height: 38px; min-height: 38px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-card { padding: 8px 14px 10px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-heading { margin-bottom: 8px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-heading h2 { margin: 0 0 3px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table th { padding-top: 9px; padding-bottom: 9px; }
.laundry-operations .laundry-unified-workspace .laundry-cases-table td { padding-top: 9px; padding-bottom: 9px; }
.laundry-operations .laundry-unified-workspace .laundry-pagination { padding: 9px 0 3px; }
/* Do not hide rows or clip panels: more than one record must remain accessible. */
@media (max-width: 700px) {
    .laundry-operations .laundry-unified-workspace .laundry-tabs { padding: 8px 10px; }
    .laundry-operations .laundry-unified-workspace .laundry-toolbar { padding: 10px; }
    .laundry-operations .laundry-unified-workspace .laundry-cases-card { padding: 8px 10px 10px; }
}
/* LAUNDRY_VERTICAL_FIT_END */
</style>
