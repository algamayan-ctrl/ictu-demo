<style>
/*
|--------------------------------------------------------------------------
| Borrower workspace - My Requests
|--------------------------------------------------------------------------
|
| Keep this page on the shared record-list design used by My Borrowings and
| SPMU operational queues. The toolbar comes from .universal-record-toolbar;
| rows come from .operational-record; statuses use the shared status-badge component.
|
*/
.my-requests {
    width: 100%;
    min-width: 0;
    /* Lets .mr-empty below grow to fill leftover height when it is the only
       content on the page (an empty request list). */
    height: 100%;
    display: flex;
    flex-direction: column;
}

.my-requests [hidden] { display: none !important; }
.my-requests > .mr-empty { flex: 1 1 auto; }

/* The shared toolbar already owns sizing, radius, spacing, and field height. */
.my-requests .mr-toolbar { margin-bottom: 16px; }

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

/* Use the same row density as My Borrowings / Release / Return records. */
.mr-list { gap: 10px; }

/* Request-specific emphasis only; geometry remains the universal record row. */
.mr-list .operational-record-primary > strong { overflow-wrap: anywhere; }
.mr-list .operational-record-primary > span,
.mr-list .operational-record-primary > small { overflow-wrap: anywhere; }
.mr-list .operational-record-facts strong { overflow-wrap: anywhere; }

/* Shared pagination styling, with a lightweight results footer rather than a
   second floating card. */
.mr-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 16px;
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

/* Empty states keep the same card shell and spacing language used elsewhere. */
.mr-empty {
    display: grid;
    justify-items: center;
    align-content: center;
    gap: 16px;
    min-height: 300px;
    padding: 40px 20px 44px;
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
    padding: 32px 20px;
}

.mr-filter-empty .mr-empty-art { width: 112px; height: 104px; }

@media (max-width: 760px) {
    .mr-footer { align-items: flex-start; }
    .mr-footer .app-pagination { margin-left: 0; }
}
</style>

<style>
/* MY REQUESTS TOOLBAR FINAL LAYOUT START */

/*
 | Borrower My Requests
 | Force the same desktop Search | Status | Sort layout
 | used by the universal record-browser toolbar reference.
 */

/* =========================================================
   TOOLBAR CARD
   ========================================================= */

.mr-toolbar.record-browser-toolbar {
    display:grid !important;

    grid-template-columns:
        minmax(320px, 1fr)
        minmax(190px, 230px)
        minmax(170px, 190px) !important;

    align-items:end !important;

    gap:12px !important;

    width:100% !important;

    margin:0 0 16px !important;
    padding:14px !important;

    border:1px solid var(--border) !important;
    border-radius:12px !important;

    background:var(--surface-elevated, #fff) !important;

    box-shadow:
        0 1px 2px rgba(15,23,42,.04) !important;
}


/* =========================================================
   FIELDS
   ========================================================= */

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


/* Search column must consume remaining width */
.mr-toolbar.record-browser-toolbar
> .record-browser-search {
    width:100% !important;
    min-width:0 !important;
}


/* =========================================================
   SEARCH
   ========================================================= */

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


/* =========================================================
   STATUS / SORT
   ========================================================= */

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


/* =========================================================
   FOCUS
   ========================================================= */

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


/* =========================================================
   TABLET
   ========================================================= */

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


/* =========================================================
   MOBILE
   ========================================================= */

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

/* MY REQUESTS TOOLBAR FINAL LAYOUT END */
</style>

<style>
/* MY REQUESTS SEARCH ICON FIX START */

/*
 | Fix inherited/global search icon positioning.
 | My Requests uses a flex search shell:
 |
 | [ icon ][ input text ]
 */

/* Search shell */
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


/* ---------------------------------------------------------
   ICON
   --------------------------------------------------------- */

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


/* ---------------------------------------------------------
   INPUT
   --------------------------------------------------------- */

.mr-toolbar.record-browser-toolbar
#request-search {
    position:static !important;

    flex:1 1 auto !important;

    width:100% !important;
    min-width:0 !important;
    height:40px !important;

    margin:0 !important;

    /*
     | Important: remove any global left padding/text indent
     | intended for absolutely-positioned search icons.
     */
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


/* Placeholder starts after the flex icon */
.mr-toolbar.record-browser-toolbar
#request-search::placeholder {
    color:var(--text-muted) !important;
    opacity:1 !important;
}


/* Remove WebKit search decorations that can affect spacing */
.mr-toolbar.record-browser-toolbar
#request-search::-webkit-search-decoration {
    -webkit-appearance:none !important;
}

.mr-toolbar.record-browser-toolbar
#request-search::-webkit-search-cancel-button {
    cursor:pointer;
}


/* MY REQUESTS SEARCH ICON FIX END */
</style>

<style>
/* MY REQUESTS STATUS BESIDE NUMBER START */

/*
 | Request identity:
 | BR-XXXXXXXXXX   [Status]
 | Event / purpose
 | Office / College / Unit
 */

.mr-request-identity-line {
    display:flex !important;
    align-items:center !important;

    gap:8px !important;

    min-width:0 !important;

    margin:0 0 2px !important;
}


/* Request number */
.mr-request-identity-line > strong {
    min-width:0 !important;

    margin:0 !important;

    color:var(--heading) !important;

    font-size:13px !important;
    font-weight:800 !important;
    line-height:1.3 !important;

    white-space:nowrap !important;
}


/* Status badge stays compact beside the request number */
.mr-request-identity-line > .status-badge {
    flex:0 0 auto !important;

    min-height:23px !important;

    padding:4px 8px !important;

    font-size:9.5px !important;
    line-height:1 !important;

    white-space:nowrap !important;
}


/* ========================================================
   RIGHT ACTION
   ======================================================== */

/*
 | Status no longer belongs here.
 | Right side is now action-only.
 */

.operational-record-action {
    justify-content:center !important;
    align-items:flex-end !important;
}


/*
 | Retain existing button visual for
 | Resume Draft / View Request.
 */
.operational-record-action > strong {
    margin:0 !important;
}


/* ========================================================
   MOBILE
   ======================================================== */

@media (max-width:680px) {

    .mr-request-identity-line {
        flex-wrap:wrap !important;

        row-gap:5px !important;
    }

}

/* MY REQUESTS STATUS BESIDE NUMBER END */
</style>

<style>
/* MY REQUESTS ACTION ICONS START */

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

/* MY REQUESTS ACTION ICONS END */
</style>
