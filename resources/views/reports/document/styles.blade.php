<style>
{{-- /*
| Formal report document.
|
| One stylesheet for the on-screen preview and the printed page, so the sheet
| a reader reviews is the sheet that comes out of the printer. Institutional,
| not decorative: white ground, hairline rules, no radius, no shadow, no
| colour beyond the text itself.
*/ --}}
.doc-sheet {
    max-width: 1180px;
    margin: 0 auto;
    padding: 30px 34px 34px;
    background: #fff;
    color: #111;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    line-height: 1.45;
}

.doc-header { display: grid; grid-template-columns: auto 1fr; align-items: center; column-gap: 12px; padding: 0 0 8px; border-bottom: 1px solid #222; }
.doc-header-seal { width: 58px; height: 58px; object-fit: contain; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.doc-header-identity { min-width: 0; }
.doc-header-republic, .doc-header-address { display: block; font-size: 11px; line-height: 1.2; }
.doc-header-institution { display: block; font-size: 15px; font-weight: 700; line-height: 1.2; letter-spacing: .01em; }

.doc-title-block { margin: 18px 0 14px; text-align: center; }
.doc-title { margin: 0; font-size: 15px; font-weight: 700; letter-spacing: .04em; color: #111; }
.doc-period { margin: 3px 0 0; font-size: 11.5px; color: #333; }

.doc-meta { margin: 0 0 16px; padding: 0; display: grid; gap: 2px; }
.doc-meta > div { display: grid; grid-template-columns: 130px 1fr; column-gap: 8px; }
.doc-meta dt { font-size: 11.5px; color: #111; }
.doc-meta dt::after { content: " :"; }
.doc-meta dd { margin: 0; font-size: 11.5px; color: #111; }

.doc-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
.doc-table th {
    padding: 6px 7px;
    text-align: left;
    vertical-align: bottom;
    background: #f2f2f2;
    border: 1px solid #999;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: .02em;
    text-transform: uppercase;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.doc-table td { padding: 5px 7px; border: 1px solid #bbb; vertical-align: top; color: #111; }
.doc-table td.numeric, .doc-table th.numeric { text-align: right; font-variant-numeric: tabular-nums; }
.doc-table-empty { text-align: center; color: #555; padding: 18px 8px; }
.doc-table-action-column { width: 1%; white-space: nowrap; text-align: right; }
.doc-table-record-link { text-decoration: none; }

.doc-summary { margin: 16px 0 0; }
.doc-summary-heading { margin: 0 0 6px; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #111; }
.doc-summary dl { margin: 0; display: grid; gap: 2px; }
.doc-summary dl > div { display: grid; grid-template-columns: 220px auto; column-gap: 8px; }
.doc-summary dt { font-size: 11.5px; }
.doc-summary dt::after { content: " :"; }
.doc-summary dd { margin: 0; font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; }

.doc-footer { margin-top: 22px; padding-top: 8px; border-top: 1px solid #999; }
.doc-footer p { margin: 0; font-size: 10px; color: #333; }

{{-- /* The table can outgrow the screen; on paper it is fitted by orientation. */ --}}
.doc-table-scroll { overflow-x: auto; }

@media print {
    .doc-sheet { max-width: none; margin: 0; padding: 0; }
    .doc-table-scroll { overflow: visible; }

    {{-- /* An on-screen record link is not usable on paper. */ --}}
    .doc-table-action-column { display: none; }

{{-- /* Universal print table fitting */ --}}
.doc-table {
    width: 100%;
    max-width: 100%;
    table-layout: fixed;
}

.doc-table th,
.doc-table td {
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}
    .doc-table thead {
        display: table-header-group;
        break-after: avoid-page;
    }

    .doc-table tbody {
        display: table-row-group;
    }

    .doc-table tbody tr {
        break-inside: auto;
        page-break-inside: auto;
    }
    .doc-summary, .doc-footer { break-inside: avoid; }
}
</style>

<style>
{{-- /* REMOVE GENERATED REPORT SUMMARY FINAL */ --}}

{{-- /*
|--------------------------------------------------------------------------
| Generated formal reports
|--------------------------------------------------------------------------
| Formal reports should present institutional metadata + actual records.
| Summary/KPI-style counts belong to Analytics, not the printed record.
|--------------------------------------------------------------------------
*/ --}}

.doc-sheet .doc-summary,
.doc-sheet .report-summary,
.doc-sheet [data-report-summary],
.doc-sheet [class*="report-summary"],
.doc-sheet [class*="doc-summary"] {
    display: none !important;
}

{{-- /*
 * Hide a standalone Report Summary heading when it is part of
 * the shared generated document summary section.
 */ --}}
.doc-sheet .doc-summary-title,
.doc-sheet .report-summary-title,
.doc-sheet [data-report-summary-title] {
    display: none !important;
}

{{-- /*
 * Prevent residual spacing from a hidden summary section.
 */ --}}
.doc-sheet .doc-summary + *,
.doc-sheet .report-summary + *,
.doc-sheet [data-report-summary] + * {
    margin-top: 0 !important;
}

@media print {
    .doc-sheet .doc-summary,
    .doc-sheet .report-summary,
    .doc-sheet [data-report-summary],
    .doc-sheet [class*="report-summary"],
    .doc-sheet [class*="doc-summary"],
    .doc-sheet .doc-summary-title,
    .doc-sheet .report-summary-title,
    .doc-sheet [data-report-summary-title] {
        display: none !important;
    }
}

</style>

<style>
{{-- /* FORMAL REPORT FOOTER RENDERER FINAL */ --}}

{{-- /*
 * IMPORTANT:
 * This positioning is intentionally NOT limited to @media print.
 * The PDF renderer may render the document using screen media.
 */ --}}
.doc-footer {
    position: fixed !important;

    left: 0 !important;
    right: 0 !important;
    bottom: 6mm !important;

    box-sizing: border-box !important;

    width: auto !important;

    margin: 0 !important;

    padding:
        6px
        34mm
        0
        0 !important;

    border-top:
        1px
        solid
        #9aa5b1 !important;

    background: #fff !important;

    color: #42566d !important;

    font-size: 7.5pt !important;
    line-height: 1.35 !important;

    z-index: 50 !important;
}

.doc-footer-copy {
    display: block !important;

    margin: 0 !important;
    padding: 0 !important;
}

.doc-footer p {
    margin: 0 !important;
    padding: 0 !important;

    color: #42566d !important;

    font-size: 7.5pt !important;
    line-height: 1.35 !important;
}

{{-- /*
 * Reserve the bottom portion of the paper so the records table
 * cannot collide with the fixed institutional footer.
 */ --}}
.doc-sheet {
    box-sizing: border-box !important;

    padding-bottom: 24mm !important;
}


{{-- /* ============================================================
   NORMAL APP SCREEN
   ============================================================ */ --}}

{{-- /*
 * The formal print source is already hidden in the system preview.
 * If a document sheet is displayed directly, keep its footer
 * attached to the bottom of the sheet rather than the viewport.
 */ --}}
@media screen {

    .report-preview-sheet .doc-footer {
        position: absolute !important;

        left: 14mm !important;
        right: 14mm !important;
        bottom: 10mm !important;
    }

    .report-preview-sheet .doc-sheet {
        position: relative !important;
    }
}


{{-- /* ============================================================
   PRINT
   ============================================================ */ --}}

@media print {

    .doc-footer {
        position: fixed !important;

        left: 0 !important;
        right: 0 !important;
        bottom: 6mm !important;
    }

    .doc-sheet {
        padding-bottom: 24mm !important;
    }
}

</style>