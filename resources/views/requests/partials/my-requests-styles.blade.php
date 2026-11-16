<style>

.my-requests {
    width: 100%;
    min-width: 0;

    height: 100%;
    display: flex;
    flex-direction: column;
}

.my-requests [hidden] { display: none !important; }
.my-requests > .mr-empty { flex: 1 1 auto; }

.my-requests .mr-toolbar { margin-bottom: 12px; }

.mr-results {
    min-width: 0;
}

.mr-results-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 0 2px 10px;
    color: var(--text-secondary);
}

.mr-result-count {
    color: var(--heading);
    font-size: 13px;
    font-weight: 800;
}

.mr-list { gap: 10px; }

.mr-list .operational-record-primary > strong { overflow-wrap: anywhere; }
.mr-list .operational-record-primary > span,
.mr-list .operational-record-primary > small { overflow-wrap: anywhere; }
.mr-list .operational-record-facts strong { overflow-wrap: anywhere; }

.mr-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 12px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}

.mr-footer > p {
    margin: 0;
    color: var(--text-muted);
    font-size: 11px;
}

.mr-footer .app-pagination {
    margin-top: 0;
    margin-left: auto;
}

.mr-page-previous { transform: rotate(180deg); }

.mr-empty {
    display: grid;
    justify-items: center;
    align-content: center;
    gap: 12px;
    min-height: 300px;
    padding: 40px 16px 44px;
    background: var(--surface-elevated);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    text-align: center;
}

.mr-empty-art {
    width: 156px;
    height: 146px;
    color: #b9d4f2;
}

.mr-empty-sheet { fill: #eef5fd; stroke: #9dc4ec; stroke-width: 3.4; }
.mr-empty-clip { fill: #dbe9fa; stroke: #9dc4ec; stroke-width: 3.4; }
.mr-empty-line rect { fill: #c3daf3; }

html[data-theme="dark"] .mr-empty-art { color: #2f4a68; }
html[data-theme="dark"] .mr-empty-sheet { fill: #16273a; stroke: #3d6288; }
html[data-theme="dark"] .mr-empty-clip { fill: #1d3247; stroke: #3d6288; }
html[data-theme="dark"] .mr-empty-line rect { fill: #2c455f; }

.mr-empty-copy { display: grid; gap: 6px; }
.mr-empty-copy strong { color: var(--heading); font-size: 17px; font-weight: 800; }
.mr-empty-copy span { color: var(--text-muted); font-size: 13px; }

.mr-filter-empty {
    min-height: 260px;
    margin-top: 10px;
    padding: 24px 16px;
}

.mr-filter-empty .mr-empty-art { width: 112px; height: 104px; }

@media (max-width: 760px) {
    .mr-footer { align-items: flex-start; }
    .mr-footer .app-pagination { margin-left: 0; }
}
</style>

<style>

.mr-toolbar.record-browser-toolbar {
    display:grid !important;

    grid-template-columns:
        minmax(320px, 1fr)
        minmax(190px, 230px)
        minmax(170px, 190px) !important;

    align-items:end !important;

    gap:12px !important;

    width:100% !important;

    margin:0 0 12px !important;
    padding:12px !important;

    border:1px solid var(--border) !important;
    border-radius:12px !important;

    background:var(--surface-elevated, #fff) !important;

    box-shadow:
        0 1px 2px rgba(15,23,42,.04) !important;
}

.mr-toolbar.record-browser-toolbar > label {
    display:flex !important;
    flex-direction:column !important;

    min-width:0 !important;

    gap:7px !important;

    margin:0 !important;

    color:var(--text-secondary) !important;

    font-size:11.5px !important;
    font-weight:700 !important;
    line-height:1.3 !important;
}

.mr-toolbar.record-browser-toolbar
> .record-browser-search {
    width:100% !important;
    min-width:0 !important;
}

.mr-toolbar.record-browser-toolbar
.search-input-shell {
    display:flex !important;
    align-items:center !important;

    gap:9px !important;

    width:100% !important;
    min-width:0 !important;

    height:42px !important;

    padding:0 12px !important;

    border:1px solid var(--border-strong, var(--border)) !important;
    border-radius:8px !important;

    background:var(--surface, #fff) !important;

    box-shadow:none !important;
}

.mr-toolbar.record-browser-toolbar
.search-input-icon {
    display:grid !important;
    place-items:center !important;

    flex:0 0 18px !important;

    width:18px !important;
    height:18px !important;

    color:var(--text-muted) !important;
}

.mr-toolbar.record-browser-toolbar
#request-search {
    display:block !important;

    flex:1 1 auto !important;

    width:100% !important;
    min-width:0 !important;

    height:40px !important;

    margin:0 !important;
    padding:0 !important;

    border:0 !important;
    outline:0 !important;

    background:transparent !important;

    color:var(--heading) !important;

    font:inherit !important;
    font-size:13px !important;
    font-weight:400 !important;

    box-shadow:none !important;
}

.mr-toolbar.record-browser-toolbar
#request-status-filter,

.mr-toolbar.record-browser-toolbar
#request-sort {
    display:block !important;

    width:100% !important;
    min-width:0 !important;

    height:42px !important;

    margin:0 !important;

    padding:0 38px 0 12px !important;

    border:1px solid var(--border-strong, var(--border)) !important;
    border-radius:8px !important;

    background-color:var(--surface, #fff) !important;

    color:var(--heading) !important;

    font:inherit !important;
    font-size:13px !important;
    font-weight:500 !important;

    box-shadow:none !important;

    cursor:pointer !important;
}

.mr-toolbar.record-browser-toolbar
.search-input-shell:focus-within,

.mr-toolbar.record-browser-toolbar
#request-status-filter:focus,

.mr-toolbar.record-browser-toolbar
#request-sort:focus {
    border-color:var(--interactive, #1769e0) !important;

    outline:none !important;

    box-shadow:
        0 0 0 3px rgba(23,105,224,.10) !important;
}

@media (max-width:850px) {

    .mr-toolbar.record-browser-toolbar {
        grid-template-columns:
            minmax(0,1fr)
            minmax(180px,220px) !important;
    }

    .mr-toolbar.record-browser-toolbar
    > .record-browser-search {
        grid-column:1 / -1 !important;
    }
}

@media (max-width:600px) {

    .mr-toolbar.record-browser-toolbar {
        grid-template-columns:1fr !important;

        gap:10px !important;

        padding:12px !important;
    }

    .mr-toolbar.record-browser-toolbar
    > .record-browser-search {
        grid-column:auto !important;
    }
}

</style>

<style>

.mr-toolbar.record-browser-toolbar
.search-input-shell {
    position:relative !important;

    display:flex !important;
    align-items:center !important;

    gap:10px !important;

    width:100% !important;
    min-width:0 !important;
    height:42px !important;

    padding:
        0
        13px !important;

    overflow:hidden !important;
}

.mr-toolbar.record-browser-toolbar
.search-input-icon {
    position:static !important;

    top:auto !important;
    right:auto !important;
    bottom:auto !important;
    left:auto !important;

    inset:auto !important;

    transform:none !important;

    display:flex !important;
    align-items:center !important;
    justify-content:center !important;

    flex:0 0 18px !important;

    width:18px !important;
    height:18px !important;

    margin:0 !important;
    padding:0 !important;

    color:var(--text-muted) !important;

    pointer-events:none !important;
}

.mr-toolbar.record-browser-toolbar
.search-input-icon svg,
.mr-toolbar.record-browser-toolbar
.search-input-icon .ui-icon {
    display:block !important;

    width:17px !important;
    height:17px !important;

    flex:0 0 17px !important;

    margin:0 !important;

    transform:none !important;
}

.mr-toolbar.record-browser-toolbar
#request-search {
    position:static !important;

    flex:1 1 auto !important;

    width:100% !important;
    min-width:0 !important;
    height:40px !important;

    margin:0 !important;

    padding:
        0 !important;

    text-indent:0 !important;

    border:0 !important;
    outline:0 !important;

    background:transparent !important;

    box-shadow:none !important;

    -webkit-appearance:none !important;
    appearance:none !important;
}

.mr-toolbar.record-browser-toolbar
#request-search::placeholder {
    color:var(--text-muted) !important;
    opacity:1 !important;
}

.mr-toolbar.record-browser-toolbar
#request-search::-webkit-search-decoration {
    -webkit-appearance:none !important;
}

.mr-toolbar.record-browser-toolbar
#request-search::-webkit-search-cancel-button {
    cursor:pointer;
}

</style>

<style>

.mr-request-identity-line {
    display:flex !important;
    align-items:center !important;

    gap:8px !important;

    min-width:0 !important;

    margin:0 0 2px !important;
}

.mr-request-identity-line > strong {
    min-width:0 !important;

    margin:0 !important;

    color:var(--heading) !important;

    font-size:13px !important;
    font-weight:800 !important;
    line-height:1.3 !important;

    white-space:nowrap !important;
}

.mr-request-identity-line > .status-badge {
    flex:0 0 auto !important;

    min-height:23px !important;

    padding:4px 8px !important;

    font-size:9.5px !important;
    line-height:1 !important;

    white-space:nowrap !important;
}

.operational-record-action {
    justify-content:center !important;
    align-items:flex-end !important;
}

.operational-record-action > strong {
    margin:0 !important;
}

@media (max-width:680px) {

    .mr-request-identity-line {
        flex-wrap:wrap !important;

        row-gap:5px !important;
    }

}

</style>

<style>

.operational-record-action
.mr-request-action-label {
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;

    gap:6px !important;

    margin:0 !important;
}

.operational-record-action
.mr-request-action-label svg,
.operational-record-action
.mr-request-action-label .ui-icon {
    flex:0 0 auto !important;

    margin:0 !important;
}

</style>
