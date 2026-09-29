@extends('layouts.app', ['title' => 'Borrower Accountability'])
@section('content')

@include('accountability.partials.obligations-styles')

<style>
    /* Borrower accountability uses the same compact scale as the rest of the
       SPMU workspace. Case cards stay readable, but status/action copy never
       becomes a second oversized dashboard. */
    .borrower-accountability-workspace .page-heading { margin-bottom: 14px; }
    .borrower-accountability-workspace .page-heading h1 {
        font-size: clamp(24px, 1.9vw, 29px);
        margin: 4px 0 5px;
    }
    .borrower-accountability-workspace .page-heading p:not(.eyebrow) {
        font-size: 12.5px;
        line-height: 1.45;
    }
    .accountability-back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 7px;
        color: var(--interactive);
        font-size: 11.5px;
        font-weight: 750;
        text-decoration: none;
    }
    .accountability-back-link:hover { text-decoration: underline; }

    /* Exact case opened from a staff queue is a single-record workspace, not
       another borrower overview. Keep borrower identity + the actual record,
       while removing dashboard chrome and nested card framing. */
    .borrower-accountability-workspace.is-focused-case-view .page-heading { margin-bottom: 16px; }
    .borrower-accountability-workspace.is-focused-case-view .accountability-custody-group__header {
        margin: 0 0 8px;
        padding: 0;
        border: 0;
        color: var(--text-muted);
    }
    .borrower-accountability-workspace.is-focused-case-view .accountability-custody-group__header strong {
        font-size: 11px;
        font-weight: 750;
    }
    .borrower-accountability-workspace.is-focused-case-view .accountability-standalone-groups { gap: 0; }

    .accountability-workspace-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .accountability-summary-card {
        --summary-from: #1d4ed8;
        --summary-to: #38bdf8;
        --summary-glow: rgba(29, 78, 216, .28);

        position: relative;
        display: grid;
        grid-template-columns: 46px minmax(0, 1fr);
        grid-template-rows: auto auto auto;
        column-gap: 12px;
        row-gap: 2px;
        min-width: 0;
        min-height: 122px;
        padding: 16px 18px;
        border: 0;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, var(--summary-from), var(--summary-to));
        color: #fff;
        text-decoration: none;
        box-shadow: 0 10px 24px var(--summary-glow);
        transition: transform .16s ease, box-shadow .16s ease;
    }
    .accountability-summary-card::before {
        content: "";
        position: absolute;
        inset: auto -8% -28% 32%;
        height: 72%;
        border-radius: 999px;
        background: rgba(255, 255, 255, .08);
        transform: rotate(-8deg);
    }
    .accountability-summary-card::after {
        content: "";
        position: absolute;
        inset: -18% auto auto -12%;
        width: 42%;
        aspect-ratio: 1;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
    }
    .accountability-summary-card > * { position: relative; z-index: 1; }
    .accountability-summary-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 28px var(--summary-glow);
    }
    .accountability-summary-card:focus-visible {
        outline: 3px solid rgba(255, 255, 255, .34);
        outline-offset: 3px;
    }
    .accountability-summary-card.tone-danger { --summary-from:#b91c1c; --summary-to:#f97316; --summary-glow:rgba(185,28,28,.28); }
    .accountability-summary-card.tone-success { --summary-from:#0f766e; --summary-to:#10b981; --summary-glow:rgba(15,118,110,.28); }
    .accountability-summary-card.tone-info { --summary-from:#1d4ed8; --summary-to:#38bdf8; --summary-glow:rgba(29,78,216,.28); }
    .accountability-summary-card.tone-warning { --summary-from:#c87912; --summary-to:#f59e0b; --summary-glow:rgba(200,121,18,.28); }
    .accountability-summary-icon {
        grid-column: 1;
        grid-row: 1 / span 3;
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        align-self: center;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 50%;
        background: rgba(255, 255, 255, .12);
        color: #fff;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
    }
    .accountability-summary-label {
        grid-column: 2;
        grid-row: 1;
        align-self: end;
        color: rgba(255, 255, 255, .95);
        font-size: 11px;
        font-weight: 800;
    }
    .accountability-summary-value {
        grid-column: 2;
        grid-row: 2;
        color: #fff;
        font-size: 25px;
        line-height: 1.15;
    }
    .accountability-summary-note {
        grid-column: 2;
        grid-row: 3;
        color: rgba(255, 255, 255, .86);
        font-size: 10px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .accountability-workspace-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin: 0 0 16px;
    }
    .accountability-workspace-tab {
        appearance: none;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        min-height: 40px;
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: var(--surface-elevated);
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 750;
        line-height: 1.2;
        text-align: center;
        text-decoration: none;
    }
    .accountability-workspace-tab:hover { border-color: var(--border-strong); background: var(--surface-hover); }
    .accountability-workspace-tab.is-active {
        border-color: var(--interactive);
        background: var(--info-bg);
        color: var(--interactive);
        box-shadow: inset 0 2px 0 var(--interactive);
    }

    /* KPI drill-down: keep the title and return action in one compact row,
       aligned with the case card that follows. No new card styling. */
    .borrower-accountability-workspace .accountability-scoped-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px 16px;
        margin: 0 0 12px;
        padding: 0;
    }
    .borrower-accountability-workspace .accountability-scoped-heading h2 {
        margin: 0;
        font-size: 16px;
        line-height: 1.4;
        font-weight: 700;
    }
    .borrower-accountability-workspace .accountability-scoped-heading .button {
        width: auto;
        margin: 0;
        flex-shrink: 0;
    }

    /* Documents are grouped by their accountability matter so staff can scan
       the case first, then its RSLDDP/Billing/Notice records. This intentionally
       reuses the same restrained card/border language as Active Cases. */
    .accountability-document-panel { padding: 0; overflow: hidden; }
    .accountability-document-groups { display: grid; gap: 12px; }
    .accountability-document-group { padding: 0; overflow: hidden; }
    .accountability-document-group__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 13px 14px 12px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-subtle);
    }
    .accountability-document-group__identity { display: grid; gap: 3px; min-width: 0; }
    .accountability-document-group__identity > strong {
        color: var(--heading);
        font-size: 12.5px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .accountability-document-group__identity > span {
        color: var(--text-secondary);
        font-size: 11.25px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }
    .accountability-document-group__identity > small {
        color: var(--text-muted);
        font-size: 10.5px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }
    .accountability-document-case-state {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 3px 8px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-elevated);
        color: var(--text-secondary);
        font-size: 9.5px;
        font-weight: 800;
        white-space: nowrap;
    }
    .accountability-document-case-state.is-open {
        border-color: #ead5ab;
        background: #fff8e9;
        color: #8a5a08;
    }
    .accountability-document-case-state.is-resolved {
        border-color: #c3e5d0;
        background: #eefaf3;
        color: #16723b;
    }
    .accountability-document-list { display: grid; }
    .accountability-document-row {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        min-width: 0;
        padding: 11px 14px;
        border-bottom: 1px solid var(--row-border);
        text-align: left;
    }
    .accountability-document-row:last-child { border-bottom: 0; }
    .accountability-document-row .dashboard-activity-icon {
        width: 34px;
        height: 34px;
        justify-self: start;
    }
    .accountability-document-copy {
        display: grid;
        min-width: 0;
        justify-self: stretch;
        text-align: left;
    }
    .accountability-document-copy strong,
    .accountability-document-copy span,
    .accountability-document-copy small {
        display: block;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .accountability-document-copy strong {
        color: var(--heading);
        font-size: 12px;
        font-weight: 750;
        line-height: 1.35;
    }
    .accountability-document-copy span {
        margin-top: 2px;
        color: var(--text-secondary);
        font-size: 11.25px;
        line-height: 1.4;
    }
    .accountability-document-copy small {
        margin-top: 3px;
        color: var(--text-muted);
        font-size: 10.5px;
        line-height: 1.4;
    }
    .accountability-document-row > .button {
        justify-self: end;
        width: auto;
        min-width: 74px;
        white-space: nowrap;
    }

    .accountability-custody-groups { display: grid; gap: 12px; }
    .accountability-custody-group { padding: 14px; }
    .accountability-custody-group__header {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        padding-bottom: 9px;
        border-bottom: 1px solid var(--border);
        color: var(--text-secondary);
    }
    .accountability-custody-group__header strong {
        color: var(--text-primary);
        font-size: 13px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .accountability-custody-group .ob-case-list { gap: 10px; }
    .accountability-custody-case {
        --case-from: #1d4ed8;
        --case-to: #38bdf8;
        --case-accent: #1d4ed8;
        --case-tint: rgba(29, 78, 216, .10);
        --case-glow: rgba(29, 78, 216, .10);

        position: relative;
        overflow: hidden;
        padding: 13px 14px;
        border: 1px solid var(--border);
        background: var(--surface-elevated);
        box-shadow: 0 6px 16px rgba(15, 23, 42, .04);
    }
    /* Operational case surfaces stay neutral. Tone remains only on icons,
       badges, and the slim role/action accent. */
    .accountability-custody-case::before,
    .accountability-custody-case::after {
        content: none;
        display: none;
    }
    .accountability-custody-case > * {
        position: relative;
        z-index: 1;
    }

    .accountability-custody-case.tone-danger { --case-from:#b91c1c; --case-to:#f97316; --case-accent:#c2410c; --case-tint:rgba(194,65,12,.10); --case-glow:rgba(194,65,12,.10); }
    .accountability-custody-case.tone-warning { --case-from:#c87912; --case-to:#f59e0b; --case-accent:#c87912; --case-tint:rgba(200,121,18,.11); --case-glow:rgba(200,121,18,.10); }
    .accountability-custody-case.tone-info { --case-from:#1d4ed8; --case-to:#38bdf8; --case-accent:#1769e0; --case-tint:rgba(23,105,224,.10); --case-glow:rgba(23,105,224,.10); }
    .accountability-custody-case.tone-success { --case-from:#0f766e; --case-to:#10b981; --case-accent:#0f766e; --case-tint:rgba(15,118,110,.10); --case-glow:rgba(15,118,110,.10); }
    .accountability-custody-case.tone-orange { --case-from:#b45309; --case-to:#fb923c; --case-accent:#c2410c; --case-tint:rgba(194,65,12,.10); --case-glow:rgba(194,65,12,.10); }
    .accountability-custody-case .ob-case-identity { gap: 10px; }
    .accountability-custody-case .ob-case-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        border: 1px solid color-mix(in srgb, var(--case-accent) 18%, var(--border));
        background: color-mix(in srgb, var(--case-accent) 10%, var(--surface-elevated));
        color: var(--case-accent);
    }
    .accountability-custody-case .ob-case-type { font-size: 9px; margin-bottom: 3px; }
    .accountability-case-title-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 7px 9px;
        min-width: 0;
    }
    .accountability-case-title-row h3 {
        margin: 0;
        font-size: 13.5px;
        line-height: 1.35;
    }
    .accountability-case-status {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        min-height: 22px;
        padding: 3px 8px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--surface-subtle);
        color: var(--text-secondary);
        font-size: 9.5px;
        font-weight: 750;
        line-height: 1.3;
        overflow-wrap: anywhere;
        white-space: normal;
    }
    .accountability-case-status.is-danger { color: var(--danger); background: var(--danger-bg); border-color: var(--danger-border); }
    .accountability-case-status.is-warning { color: var(--warning); background: var(--warning-bg); border-color: var(--warning-border); }
    .accountability-case-status.is-info { color: var(--info); background: var(--info-bg); border-color: var(--info-border); }
    .accountability-case-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 5px 14px;
        margin: 8px 0 0 44px;
        color: var(--text-secondary);
        font-size: 11.5px;
        line-height: 1.45;
    }
    .accountability-case-summary span { overflow-wrap: anywhere; }
    .accountability-case-summary strong { color: var(--text-primary); font-weight: 700; }

    .accountability-case-responsibility {
        display: grid;
        grid-template-columns: minmax(135px, .75fr) minmax(0, 1.5fr) auto;
        align-items: center;
        gap: 8px 14px;
        margin: 10px 0 0 44px;
        padding: 9px 10px;
        border: 1px solid var(--border);
        border-left: 3px solid var(--case-accent, var(--info));
        border-radius: 8px;
        background: var(--surface-elevated);
    }
    .accountability-role-copy { min-width: 0; }
    .accountability-role-copy small {
        display: block;
        margin-bottom: 2px;
        color: var(--text-muted);
        font-size: 8.5px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .accountability-role-copy strong,
    .accountability-role-copy span {
        display: block;
        color: var(--text-primary);
        font-size: 10.75px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }
    .accountability-role-copy span { color: var(--text-secondary); }
    .accountability-action-button {
        min-height: 32px;
        padding: 6px 10px;
        white-space: normal;
        text-align: center;
        line-height: 1.25;
        color: var(--interactive);
        background: var(--surface-elevated);
        border-color: var(--interactive);
    }
    .accountability-action-button:hover,
    .accountability-action-button:focus-visible {
        color: #fff;
        background: var(--interactive);
        border-color: var(--interactive);
        box-shadow: var(--shadow-sm);
        transform: translateY(-1px);
    }
    [data-accountability-standalone-toggle],
    .accountability-case-record__row > .button,
    details.accountability-case-documents > summary {
        transition: background-color .16s ease, color .16s ease, border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }
    [data-accountability-standalone-toggle]:hover,
    [data-accountability-standalone-toggle]:focus-visible,
    .accountability-case-record__row > .button:hover,
    .accountability-case-record__row > .button:focus-visible {
        color: #fff;
        background: var(--interactive);
        border-color: var(--interactive);
        box-shadow: var(--shadow-sm);
        transform: translateY(-1px);
    }
    details.accountability-case-documents > summary:hover,
    details.accountability-case-documents > summary:focus-visible {
        color: var(--interactive);
        background: color-mix(in srgb, var(--interactive) 8%, transparent);
        border-radius: 7px;
    }
    details.accountability-case-record > summary {
        cursor: pointer;
        transition: background-color .16s ease, color .16s ease;
    }
    details.accountability-case-record > summary:hover,
    details.accountability-case-record > summary:focus-visible {
        color: var(--interactive);
        background: color-mix(in srgb, var(--interactive) 8%, transparent);
        border-radius: 7px;
    }
    /* Standalone billing/restriction matters are already self-contained case
       cards. Avoid wrapping them in a second card/header, and keep the
       disclosure trigger anchored in the same right-hand grid cell when open. */
    .accountability-standalone-groups { margin-top: 0; gap: 10px; }
    .accountability-standalone-case { border: 1px solid var(--border); }
    .accountability-standalone-toggle { justify-self: end; min-width: 118px; }
    .accountability-standalone-detail-body {
        display: grid;
        gap: 8px;
        min-width: 0;
        margin: 8px 0 0 44px;
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface-elevated);
    }
    .accountability-standalone-detail-body[hidden] { display: none; }
    .accountability-standalone-reference {
        color: var(--text-muted);
        font-size: 9px;
        font-weight: 700;
    }

    .accountability-case-record {
        margin: 9px 0 0 44px;
        border-top: 1px solid var(--border);
    }
    .accountability-case-record__toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-height: 36px;
        padding: 8px 2px 0;
        color: var(--text-secondary);
        font-size: 10.5px;
        font-weight: 800;
        cursor: pointer;
        list-style: none;
    }
    .accountability-case-record__toggle::-webkit-details-marker { display: none; }
    .accountability-case-record__toggle > span { display: inline-flex; align-items: center; gap: 7px; min-width: 0; }
    .accountability-case-record__count {
        display: inline-grid;
        min-width: 20px;
        height: 20px;
        place-items: center;
        padding: 0 6px;
        border-radius: 999px;
        background: color-mix(in srgb, var(--case-accent, var(--interactive)) 12%, var(--info-bg));
        color: var(--case-accent, var(--interactive));
        font-size: 9px;
    }
    .accountability-case-record__chevron { transition: transform .16s ease; }
    .accountability-case-record[open] .accountability-case-record__chevron { transform: rotate(180deg); }
    .accountability-case-record__body {
        display: grid;
        overflow: hidden;
        margin-top: 7px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface-elevated);
    }
    .accountability-case-record__row {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr) auto;
        align-items: center;
        gap: 9px;
        min-width: 0;
        padding: 9px 10px;
        border-bottom: 1px solid var(--border);
    }
    .accountability-case-record__row:last-child { border-bottom: 0; }
    .accountability-case-record__icon { display: grid; width: 28px; height: 28px; place-items: center; border-radius: 7px; background: color-mix(in srgb, var(--case-accent, var(--interactive)) 10%, var(--surface-subtle)); color: var(--case-accent, var(--text-secondary)); }
    .accountability-case-record__copy { display: grid; gap: 1px; min-width: 0; }
    .accountability-case-record__copy strong { color: var(--text-primary); font-size: 10.5px; overflow-wrap: anywhere; }
    .accountability-case-record__copy span { color: var(--text-secondary); font-size: 9.5px; overflow-wrap: anywhere; }
    .accountability-case-record__copy small { color: var(--text-muted); font-size: 9px; }
    .accountability-case-record__row > .button { min-height: 30px; padding: 5px 10px; }

    .accountability-action-panel {
        width: 100%;
        margin: -1px 0 0;
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--border);
        border-top: 1px solid var(--border);
        border-radius: 0 0 12px 12px;
        background: var(--surface-elevated);
        color: var(--text-primary);
        box-shadow: var(--shadow-sm);
    }
    .is-focused-action-view .accountability-custody-group {
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }
    .is-focused-action-view .accountability-custody-group__header {
        display: none;
    }
    .is-focused-action-view .accountability-custody-case {
        display: block;
        border-radius: 12px 12px 0 0;
        box-shadow: none;
    }
    .is-focused-action-view .accountability-custody-case .accountability-case-responsibility,
    .is-focused-action-view .accountability-custody-case .accountability-case-records,
    .is-focused-action-view .accountability-custody-case details.accountability-case-documents {
        display: none;
    }
    .is-focused-action-view .ob-case-list {
        display: grid;
        gap: 0;
    }
    .accountability-action-panel__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 16px 8px;
        border-bottom: 0;
    }
    .accountability-action-panel__header > div { min-width: 0; }
    .accountability-action-panel__header small {
        display: block;
        margin-bottom: 3px;
        color: var(--text-muted);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .accountability-action-panel__header h3 {
        margin: 0;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
        overflow-wrap: anywhere;
    }
    .is-focused-action-view .accountability-case-summary {
        margin-bottom: 0;
    }
    .borrower-accountability-workspace.is-focused-case-view .borrower-workspace-header {
        display: grid;
        gap: 4px;
        margin-bottom: 14px;
    }
    .borrower-accountability-workspace.is-focused-case-view .borrower-workspace-header .eyebrow {
        margin: 0;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--text-muted);
    }
    .borrower-accountability-workspace.is-focused-case-view .borrower-workspace-header h1 {
        margin: 0;
        font-size: 26px;
        line-height: 1.15;
        font-weight: 750;
        letter-spacing: -.02em;
    }
    .borrower-accountability-workspace.is-focused-case-view .borrower-workspace-header .borrower-subtitle {
        margin: 0;
        color: var(--text-secondary);
        font-size: 13px;
        line-height: 1.4;
    }
    .accountability-action-panel__header::after {
        content: "";
        position: absolute;
        left: 16px;
        right: 16px;
        top: 0;
        height: 1px;
        background: var(--border);
    }
    .accountability-action-panel__header {
        position: relative;
    }
    .accountability-action-panel__body {
        max-height: none;
        overflow: visible;
        padding: 0 16px 16px;
    }
    .accountability-action-panel__body form {
        display: grid;
        gap: 10px;
    }
    .is-focused-action-view .accountability-dialog-note {
        margin-bottom: 0;
    }
    .is-focused-action-view .accountability-dialog-actions {
        margin-top: 2px;
        padding-top: 10px;
        border-top: 1px solid var(--border);
    }
    .accountability-action-panel__body .form-grid { gap: 10px; }
    .accountability-action-panel__body .form-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .accountability-action-panel__body label {
        min-width: 0;
        font-size: 10.5px;
    }
    .accountability-action-panel__body textarea { min-height: 88px; resize: vertical; }
    /* Focused Late Return billing form: compact, legible and consistent with staff forms. */
    .accountability-action-panel__body .accountability-late-billing-form { gap: 12px; }
    .accountability-action-panel__body .accountability-late-billing-form .accountability-dialog-note {
        font-weight: 400;
    }
    .accountability-action-panel__body .accountability-late-billing-form label {
        display: flex;
        flex-direction: column;
        gap: 5px;
        font-size: 12px;
    }
    .accountability-action-panel__body .accountability-late-billing-form .accountability-field-heading {
        display: inline-flex;
        align-items: baseline;
        gap: 6px;
        line-height: 1.35;
    }
    .accountability-action-panel__body .accountability-late-billing-form .accountability-field-heading .muted {
        font-size: 11px;
        font-weight: 400;
    }
    .accountability-action-panel__body .accountability-late-billing-form textarea {
        min-height: 84px;
        height: 84px;
    }
    .accountability-action-panel__body .accountability-late-billing-form .accountability-field-help {
        display: block;
        margin: 0;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 400;
        line-height: 1.4;
    }
    .accountability-action-panel__body label.accountability-resolution-confirmation {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        margin: 0;
        line-height: 1.4;
        cursor: pointer;
    }
    .accountability-action-panel__body .accountability-resolution-confirmation input[type="checkbox"] {
        flex: 0 0 auto;
        width: 15px;
        height: 15px;
        min-width: 15px;
        margin: 0;
        padding: 0;
    }
    .accountability-action-panel__body .accountability-resolution-confirmation span {
        display: inline;
        margin: 0;
    }
    .accountability-dialog-note {
        margin: 0;
        padding: 9px 10px;
        border-left: 3px solid var(--info);
        border-radius: 7px;
        background: var(--surface-subtle);
        color: var(--text-secondary);
        font-size: 10.5px;
        line-height: 1.5;
    }
    .accountability-dialog-supporting-document {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface-elevated);
    }
    .accountability-dialog-supporting-document__icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--surface-subtle);
        color: var(--text-secondary);
    }
    .accountability-dialog-supporting-document__copy {
        display: grid;
        gap: 2px;
        min-width: 0;
    }
    .accountability-dialog-supporting-document__copy small {
        color: var(--text-muted);
        font-size: 8.5px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .accountability-dialog-supporting-document__copy strong {
        color: var(--text-primary);
        font-size: 10.75px;
        overflow-wrap: anywhere;
    }
    .accountability-dialog-supporting-document__copy span {
        color: var(--text-muted);
        font-size: 9.5px;
        overflow-wrap: anywhere;
    }
    .accountability-dialog-supporting-document .button {
        min-height: 30px;
        padding: 5px 10px;
        white-space: nowrap;
    }
    .accountability-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
        padding-top: 2px;
    }

    @media (max-width: 820px) {
        .accountability-workspace-summary { grid-template-columns: 1fr; }
        .accountability-summary-card { min-height: 104px; }
        .accountability-case-responsibility { grid-template-columns: 1fr 1fr; }
        .accountability-case-responsibility .accountability-action-button { grid-column: 1 / -1; justify-self: start; }
    }
    @media (max-width: 620px) {
        .accountability-workspace-tabs { grid-template-columns: 1fr; gap: 7px; }
        .accountability-document-row {
            grid-template-columns: 30px minmax(0, 1fr);
            gap: 9px;
            padding: 11px 12px;
        }
        .accountability-document-row > .button {
            grid-column: 2;
            justify-self: start;
            min-width: 0;
            margin-top: 2px;
        }
        .accountability-workspace-tab { min-height: 38px; }
        .accountability-custody-group { padding: 12px; }
        .accountability-custody-case { padding: 12px; }
        .accountability-case-summary,
        .accountability-case-responsibility,
        .accountability-standalone-detail-body,
        .accountability-case-record { margin-left: 0; }
        .accountability-case-responsibility { grid-template-columns: 1fr; }
        .accountability-case-responsibility .accountability-action-button { grid-column: auto; width: 100%; }
        .accountability-standalone-toggle { justify-self: start; width: 100%; }
        .accountability-action-panel__body .form-grid.two { grid-template-columns: 1fr; }
    }
</style>

@php
    $requestedTab = (string) request('tab', 'cases');
    $activeTab = in_array($requestedTab, ['cases', 'history'], true) ? $requestedTab : 'cases';
    $activeRestrictionCount = (int) ($overview['restrictions'] ?? 0);
    $borrowingStatus = $activeRestrictionCount > 0 ? 'Restricted' : 'Clear';
    $classification = auth()->user()?->access_classification?->value;
    $isOfficer = $classification === 'SPMU_OFFICER';
    $isHead = $classification === 'SPMU_HEAD';
    $focusedCase = trim((string) request('focus', ''));
    $focusedCaseType = null;
    $focusedCaseId = null;

    if (preg_match('/^(incident|overdue|billing|restriction)-(\d+)$/', $focusedCase, $focusedMatch)) {
        $focusedCaseType = $focusedMatch[1];
        $focusedCaseId = (int) $focusedMatch[2];
    }

    $isFocusedCaseView = $focusedCaseType !== null && $focusedCaseId !== null;
    $requestedScope = (string) request('scope', 'all');
    $selectedScope = ! $isFocusedCaseView && $activeTab === 'cases'
        && in_array($requestedScope, ['all', 'status', 'open', 'pending', 'payment'], true)
        ? $requestedScope : 'all';
    $shouldAutoOpenFocusedAction = $isFocusedCaseView;
    $scopeMeta = match ($selectedScope) {
        'status' => ['Borrowing Restrictions', 'No active borrowing restrictions.', 'This borrower currently has no applicable active borrowing restriction.'],
        'pending' => ['Pending Borrower Compliance', 'No borrower compliance pending.', 'There are no recorded obligations currently awaiting this borrower’s action.'],
        'open' => ['Open Accountability Matters', 'No open accountability matters.', 'There are no unresolved property, late-return, or standalone financial obligations.'],
        'payment' => ['Outstanding Payment Cases', 'No outstanding payment cases.', 'This borrower has no outstanding payment requirement.'],
        default => ['Active Cases', 'No active accountability cases.', 'This borrower currently has no open property, late-return, billing, or borrowing-restriction accountability matter.'],
    };

    // The borrower header counts accountability matters, not sanctions/restrictions.
    // A linked restriction remains visible through Borrowing Status instead of being
    // counted again as another accountability case.
    $standaloneBillingCount = collect($rows)->where('category', 'billing')->count();
    $openMatterCount = $openIncidents->count() + $openOverdueCases->count() + $standaloneBillingCount;
    $openMatterBreakdownNote = collect([
        $openIncidents->count().' Property',
        $openOverdueCases->count().' Late Return',
        $standaloneBillingCount > 0 ? $standaloneBillingCount.' Financial' : null,
    ])->filter()->implode(' · ');

    $borrowerActionRows = collect($rows)->where('action_state', \App\Services\BorrowerObligationService::ACTION_BORROWER);
    $pendingBorrowerCount = $borrowerActionRows->count();
    $paymentPendingCount = $borrowerActionRows->filter(fn ($row) => str_contains(strtolower((string) ($row['next_action'] ?? '')), 'cashier') || str_contains(strtolower((string) ($row['next_action'] ?? '')), 'pay'))->count();
    $returnPendingCount = $borrowerActionRows->filter(fn ($row) => str_contains(strtolower((string) ($row['next_action'] ?? '')), 'return'))->count();
    $compliancePendingCount = max(0, $pendingBorrowerCount - $paymentPendingCount - $returnPendingCount);
    $pendingBorrowerParts = collect([
        $paymentPendingCount > 0 ? $paymentPendingCount.' payment'.($paymentPendingCount === 1 ? '' : 's').' pending' : null,
        $returnPendingCount > 0 ? $returnPendingCount.' return'.($returnPendingCount === 1 ? '' : 's').' pending' : null,
        $compliancePendingCount > 0 ? $compliancePendingCount.' compliance requirement'.($compliancePendingCount === 1 ? '' : 's').' pending' : null,
    ])->filter()->values();
    $pendingBorrowerNote = $pendingBorrowerParts->isNotEmpty() ? $pendingBorrowerParts->implode(' · ') : 'No borrower action pending';

    $documentsForCase = static function (string $key) use ($documentGroups) {
        $group = collect($documentGroups)->firstWhere('key', $key);
        return collect($group['documents'] ?? []);
    };

    $billingForIncident = static function ($incident) use ($openBillings) {
        return $openBillings->first(fn ($billing) => $billing->lines->contains(
            fn ($line) => (int) ($line->incident_id ?? 0) === (int) $incident->id
        ));
    };

    $billingForOverdue = static function ($overdue) use ($openBillings) {
        return $openBillings->first(fn ($billing) => $billing->lines->contains(
            fn ($line) => (int) ($line->penalty?->overdue_case_id ?? 0) === (int) $overdue->id
        ));
    };
@endphp

<div class="borrower-accountability-workspace {{ $isFocusedCaseView ? 'is-focused-case-view' : '' }} {{ $shouldAutoOpenFocusedAction ? 'is-focused-action-view' : '' }}">
<section class="page-heading">
    <div>
        <a class="accountability-back-link" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">
            <span>← {{ $isHead ? 'Accountability Oversight' : 'Accountability Processing' }}</span>
        </a>
        <p class="eyebrow">Borrower Accountability</p>
        <h1>{{ $borrower->full_name }}</h1>
        <p>{{ $borrower->organizationalUnit?->unit_name ?: $borrower->organizationalUnit?->name }}</p>
    </div>
</section>

@unless($isFocusedCaseView)
<section class="accountability-workspace-summary" aria-label="Borrower accountability summary">
    <a
        class="accountability-summary-card tone-{{ $activeRestrictionCount > 0 ? 'danger' : 'success' }}"
        href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'scope' => 'status']) }}#accountability-active-cases"
        aria-label="Borrowing Status: {{ $borrowingStatus }}. {{ $activeRestrictionCount > 0 ? $activeRestrictionCount.' active restriction(s)' : 'No active restriction' }}."
    >
        <span class="accountability-summary-icon" aria-hidden="true"><x-icon :name="$activeRestrictionCount > 0 ? 'lock' : 'check-circle'" size="18" /></span>
        <span class="accountability-summary-label">Borrowing Status</span>
        <strong class="accountability-summary-value">{{ $borrowingStatus }}</strong>
        <span class="accountability-summary-note">{{ $activeRestrictionCount > 0 ? $activeRestrictionCount.' active restriction(s)' : 'No active restriction' }}</span>
    </a>
    <a
        class="accountability-summary-card tone-info"
        href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'scope' => 'open']) }}#accountability-active-cases"
        aria-label="Open Accountability Matters: {{ $openMatterCount }}. Unresolved accountability records."
    >
        <span class="accountability-summary-icon" aria-hidden="true"><x-icon name="accountability" size="18" /></span>
        <span class="accountability-summary-label">Open Accountability Matters</span>
        <strong class="accountability-summary-value">{{ $openMatterCount }}</strong>
        <span class="accountability-summary-note">{{ $openMatterBreakdownNote }}</span>
    </a>
    <a
        class="accountability-summary-card tone-warning"
        href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'scope' => 'pending']) }}#accountability-active-cases"
        aria-label="Pending Borrower Compliance: {{ $pendingBorrowerCount }}. {{ $pendingBorrowerNote }}."
    >
        <span class="accountability-summary-icon" aria-hidden="true"><x-icon name="warning" size="18" /></span>
        <span class="accountability-summary-label">Pending Borrower Compliance</span>
        <strong class="accountability-summary-value">{{ $pendingBorrowerCount }}</strong>
        <span class="accountability-summary-note">{{ $pendingBorrowerNote }}</span>
    </a>
</section>
@endunless

@unless($isFocusedCaseView)
<nav class="accountability-workspace-tabs" aria-label="Borrower accountability sections" role="tablist">
    <a role="tab" aria-selected="{{ $activeTab === 'cases' ? 'true' : 'false' }}" class="accountability-workspace-tab {{ $activeTab === 'cases' ? 'is-active' : '' }}" href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases']) }}">Active Cases</a>
    <a role="tab" aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}" class="accountability-workspace-tab {{ $activeTab === 'history' ? 'is-active' : '' }}" href="{{ route('accountability.borrower', [$borrower, 'tab' => 'history']) }}">History</a>
</nav>
@endunless

@if($activeTab === 'cases')
    <section id="accountability-active-cases" role="tabpanel" aria-label="Active Cases">
        @if(! $isFocusedCaseView && $selectedScope !== 'all')
            <div class="accountability-scoped-heading">
                <h2>{{ $scopeMeta[0] }}</h2>
                <a class="button secondary small" href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases']) }}#accountability-active-cases">View All Cases →</a>
            </div>
        @endif
        @php
            $allObligationRows = collect($rows)->keyBy(fn ($row) => (string) ($row['key'] ?? ''));
            $activeCaseRecords = collect()
                ->concat($openIncidents)
                ->concat($openOverdueCases);
            $outstandingBillingNumbers = $openBillings->filter(function ($billing): bool {
                $verified = (float) $billing->payments->where('status', 'VERIFIED')->sum('amount');
                return max(0, (float) $billing->total_amount - $verified) > 0;
            })->pluck('billing_no')->filter()->map(fn ($number) => strtolower((string) $number))->values();
            $scopedRows = match ($selectedScope) {
                'status' => collect($rows)->where('restricted', true),
                'pending' => collect($rows)->where('action_state', \App\Services\BorrowerObligationService::ACTION_BORROWER),
                'open' => collect($rows)->whereIn('category', ['property', 'overdue', 'billing']),
                'payment' => collect($rows)->filter(fn (array $row) => $outstandingBillingNumbers->contains(
                    fn ($number) => str_contains(strtolower((string) ($row['search'] ?? '')), $number)
                )),
                default => collect($rows),
            };
            $scopedRowKeys = $scopedRows->pluck('key')->map(fn ($key) => (string) $key);
            if (! $isFocusedCaseView && $selectedScope !== 'all') {
                $activeCaseRecords = $activeCaseRecords->filter(function ($record) use ($scopedRowKeys) {
                    $key = $record instanceof \App\Models\Incident
                        ? 'incident-'.$record->id : 'overdue-'.$record->id;
                    return $scopedRowKeys->contains($key);
                })->values();
            }

            if ($isFocusedCaseView) {
                if (in_array($focusedCaseType, ['incident', 'overdue'], true)) {
                    $activeCaseRecords = $activeCaseRecords->filter(function ($record) use ($focusedCaseType, $focusedCaseId) {
                        return ($focusedCaseType === 'incident' && $record instanceof \App\Models\Incident && (int) $record->id === $focusedCaseId)
                            || ($focusedCaseType === 'overdue' && $record instanceof \App\Models\OverdueCase && (int) $record->id === $focusedCaseId);
                    })->values();
                } else {
                    $activeCaseRecords = collect();
                }
            }

            $custodyGroups = $activeCaseRecords
                ->groupBy(fn ($record) => $record->custody_transaction_id ?: 'standalone-'.$record->getMorphClass().'-'.$record->id)
                ->sortByDesc(fn ($group) => $group->max(
                    fn ($record) => optional($record->reported_at ?? $record->overdue_started_at ?? $record->created_at)->timestamp
                ));

            $standaloneRows = collect($rows)
                ->whereIn('category', ['billing', 'restriction'])
                ->when(! $isFocusedCaseView && $selectedScope !== 'all', fn ($items) => $items->filter(
                    fn (array $row) => $scopedRowKeys->contains((string) ($row['key'] ?? ''))
                ))
                ->values();

            if ($isFocusedCaseView) {
                $standaloneRows = in_array($focusedCaseType, ['billing', 'restriction'], true)
                    ? $standaloneRows->where('key', $focusedCaseType.'-'.$focusedCaseId)->values()
                    : collect();
            }
        @endphp

        @if($custodyGroups->isEmpty() && $standaloneRows->isEmpty())
            <x-empty-state
                icon="accountability"
                :title="$scopeMeta[1]"
                :message="$scopeMeta[2]"
            />
        @else
            @if($custodyGroups->isNotEmpty())
            <div class="accountability-custody-groups">
                @foreach($custodyGroups as $groupRecords)
                    @php $groupCustody = $groupRecords->first()->custody; @endphp
                    <article class="card accountability-custody-group">
                        <header class="accountability-custody-group__header">
                            <x-icon name="inventory" size="15" />
                            <strong>{{ $groupCustody?->custody_no ?: 'No custody reference' }}</strong>
                        </header>

                        <div class="ob-case-list">
                            @foreach($groupRecords as $record)
                                @php $isOverdueCase = $record instanceof \App\Models\OverdueCase; @endphp

                                @if($isOverdueCase)
                                    @php
                                        $lateReturnService = app(\App\Services\LateReturnService::class);
                                        $assessment = $lateReturnService->assessment($record);
                                        $isStillOverdue = $record->status === \App\Services\LateReturnService::STATUS_OVERDUE;
                                        $isHeadReview = in_array($record->status, [
                                            \App\Services\LateReturnService::STATUS_FOR_AO_CONFIRMATION,
                                            \App\Services\LateReturnService::STATUS_FOR_HEAD_APPROVAL,
                                        ], true);
                                        $isAwaitingPayment = $record->status === \App\Services\LateReturnService::STATUS_AWAITING_PAYMENT;
                                        $linkedBilling = $billingForOverdue($record);
                                        $statusLabel = match (true) {
                                            $isStillOverdue => 'Overdue — Awaiting Return',
                                            $isHeadReview => 'For Head Review',
                                            $isAwaitingPayment => 'Awaiting Cashier Payment',
                                            default => \App\Services\LateReturnService::label($record->status),
                                        };
                                        $statusTone = $isStillOverdue ? 'danger' : ($isAwaitingPayment ? 'warning' : 'info');
                                        $responsibleNow = match (true) {
                                            $isStillOverdue => 'Borrower',
                                            $isHeadReview => 'SPMU Head/Admin',
                                            $isAwaitingPayment => 'Borrower',
                                            default => 'SPMU Processing',
                                        };
                                        $currentRoleOwnsAction = ($isOfficer && ($isStillOverdue || $isAwaitingPayment)) || ($isHead && $isHeadReview);
                                        $roleMessage = match (true) {
                                            $isStillOverdue => 'Borrower must return the outstanding item(s). The Action Officer records the return and inspection when presented.',
                                            $isHeadReview => 'SPMU Head/Admin must review the finalized late-return assessment and record the billing outcome.',
                                            $isAwaitingPayment => 'Borrower must pay through the CSPC Cashier and present the official receipt. The Action Officer records the receipt when presented.',
                                            default => 'Continue the applicable late-return process.',
                                        };
                                        $dialogId = 'late-return-action-'.$record->id;
                                        $remainingBilling = $linkedBilling
                                            ? max(0, (float) $linkedBilling->total_amount - (float) $linkedBilling->payments->where('status', 'VERIFIED')->sum('amount'))
                                            : null;
                                        $caseDocuments = $documentsForCase('overdue-'.$record->id);
                                    @endphp

                                    <article id="overdue-{{ $record->id }}" class="ob-case-card accountability-custody-case tone-{{ $isStillOverdue ? 'danger' : ($isAwaitingPayment ? 'warning' : 'info') }}">
                                        <div class="ob-case-top">
                                            <div class="ob-case-identity">
                                                <span class="ob-case-icon is-{{ $isStillOverdue ? 'danger' : 'warning' }}" aria-hidden="true"><x-icon name="clock" size="17" /></span>
                                                <div>
                                                    <span class="ob-case-type">Late Return</span>
                                                    <div class="accountability-case-title-row">
                                                        <h3>Late Return</h3>
                                                        <span class="accountability-case-status is-{{ $statusTone }}">{{ $statusLabel }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="accountability-case-summary">
                                            @if($isStillOverdue)
                                                <span><strong>Return:</strong> Awaiting physical return</span>
                                                <span><strong>Estimated Fee:</strong> {{ $assessment['rate'] === null ? 'Not determined' : 'PHP '.number_format($assessment['amount'], 2) }}</span>
                                            @else
                                                <span><strong>Late Days:</strong> {{ $assessment['late_days'] }}</span>
                                                <span><strong>{{ $assessment['is_estimate'] ? 'Estimated Fee' : 'Final Fee' }}:</strong> {{ $assessment['rate'] === null ? 'Not determined' : 'PHP '.number_format($assessment['amount'], 2) }}</span>
                                            @endif
                                        </div>

                                        <div class="accountability-case-responsibility">
                                            <div class="accountability-role-copy">
                                                <small>Waiting On</small>
                                                <strong>{{ $responsibleNow }}</strong>
                                            </div>
                                            <div class="accountability-role-copy">
                                                <small>Next Required Step</small>
                                                <span>{{ $roleMessage }}</span>
                                            </div>
                                            @if($currentRoleOwnsAction && $isStillOverdue)
                                                <a class="button secondary small accountability-action-button" href="{{ route('custody.return.show', $record->custody) }}">Record Return</a>
                                            @elseif($currentRoleOwnsAction && $isHeadReview && ! $shouldAutoOpenFocusedAction)
                                                <a class="button secondary small accountability-action-button"
                                                   href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'focus' => 'overdue-'.$record->id]) }}#overdue-{{ $record->id }}">
                                                    Review Assessment
                                                </a>
                                            @elseif($currentRoleOwnsAction && $isAwaitingPayment && $linkedBilling && ! $shouldAutoOpenFocusedAction)
                                                <a class="button secondary small accountability-action-button"
                                                   href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'focus' => 'overdue-'.$record->id]) }}#overdue-{{ $record->id }}">
                                                    Record Receipt
                                                </a>
                                            @endif
                                        </div>

                                        @include('accountability.partials.case-documents', ['documents' => $caseDocuments])
                                    </article>

                                    @if($shouldAutoOpenFocusedAction && $focusedCaseType === 'overdue' && $focusedCaseId === (int) $record->id && $currentRoleOwnsAction && $isHeadReview)
                                        <section class="accountability-action-panel" id="{{ $dialogId }}">
                                            <header class="accountability-action-panel__header">
                                                <div>
                                                    <h3>{{ ($assessment['rate'] === null || (float) $assessment['amount'] <= 0) ? 'Resolve Late Return' : 'Review Late Return Assessment' }}</h3>
                                                </div>
                                            </header>
                                            <div class="accountability-action-panel__body">
                                                @if($assessment['rate'] === null || (float) $assessment['amount'] <= 0)
                                                    <form method="post" action="{{ route('overdue.resolve-without-charge', $record) }}">
                                                        @csrf
                                                        <p class="accountability-dialog-note">No billable late-return rate is available for this finalized case. Record the Head/Admin resolution remarks to close it without a charge.</p>
                                                        <label>Resolution Remarks
                                                            <textarea name="resolution_remarks" required maxlength="2000" placeholder="State the basis for resolving this late-return case without a charge."></textarea>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Resolve Without Charge</button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <form method="post" action="{{ route('overdue.bill', $record) }}" class="accountability-late-billing-form">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Final assessment: {{ (int) $assessment['late_days'] }} late day(s) · PHP {{ number_format((float) $assessment['amount'], 2) }}</p>
                                                        <label>
                                                            <span class="accountability-field-heading">Assessment Basis <span aria-hidden="true">*</span></span>
                                                            <textarea name="basis" required maxlength="2000" rows="3" placeholder="Enter the basis for the late-return assessment."></textarea>
                                                        </label>
                                                        <label>
                                                            <span class="accountability-field-heading">Payment Due Date <span class="muted">(Optional)</span></span>
                                                            <input type="date" name="due_at" min="{{ now()->toDateString() }}">
                                                            <span class="accountability-field-help">Leave blank if no payment deadline is specified.</span>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Issue Billing Statement</button>
                                                        </div>
                                                    </form>
                                                @endif
                                            </div>
                                        </section>
                                    @elseif($shouldAutoOpenFocusedAction && $focusedCaseType === 'overdue' && $focusedCaseId === (int) $record->id && $currentRoleOwnsAction && $isAwaitingPayment && $linkedBilling)
                                        <section class="accountability-action-panel" id="{{ $dialogId }}">
                                            <header class="accountability-action-panel__header">
                                                <div>
                                                    <h3>Record Cashier Receipt</h3>
                                                </div>
                                            </header>
                                            <div class="accountability-action-panel__body">
                                                <form method="post" action="{{ route('payments.store', $linkedBilling) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <p class="accountability-dialog-note">Remaining balance: PHP {{ number_format((float) $remainingBilling, 2) }}. Record only the official CSPC Cashier receipt presented by the borrower.</p>
                                                    <div class="form-grid two">
                                                        <label>Cashier Receipt No.
                                                            <input name="official_receipt_no" required maxlength="255">
                                                        </label>
                                                        <label>Receipt Date
                                                            <input type="date" name="receipt_date" required max="{{ now()->toDateString() }}">
                                                        </label>
                                                    </div>
                                                    <label>Amount Paid
                                                        <input type="number" name="amount" min="0.01" step="0.01" max="{{ number_format((float) $remainingBilling, 2, '.', '') }}" required>
                                                    </label>
                                                    <label>Scanned Paid Receipt
                                                        <input type="file" name="evidence" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                    </label>
                                                    <label>Remarks <span class="muted">(optional)</span>
                                                        <textarea name="remarks" maxlength="1000"></textarea>
                                                    </label>
                                                    <div class="accountability-dialog-actions">
                                                        <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                        <button class="button primary small">Record Receipt</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </section>
                                    @endif
                                @else
                                    @php
                                        $incident = $record;
                                        $statusKey = strtoupper((string) $incident->status);
                                        $isDirectF2fResolution = ($statusKey === 'F2F_PROCESSING' && $incident->accountability_flow_version === 'V3')
                                            || (in_array($statusKey, ['RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING'], true) && ! $incident->official_disposition);
                                        $statusLabel = match ($statusKey) {
                                            'OPEN' => 'Awaiting Accountability Confirmation',
                                            'FOR_BILLING' => 'For Billing',
                                            'BILLING_PENDING' => 'Awaiting Payment',
                                            'COMPLIANCE_REQUIRED', 'COMPLIANCE_RSLDDP_PENDING' => 'Compliance Required',
                                            'F2F_PROCESSING' => 'Pending In-Person Resolution',
                                            'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING' => 'Pending In-Person Resolution',
                                            'RSLDDP_COMPLIANCE_VERIFICATION' => \App\Support\AccountabilityDispositionLabels::subStatusLabel($incident),
                                            'RSLDDP_FOR_ACCOUNTING_PROCESSING' => 'Official Billing Pending',
                                            'RSLDDP_PAYMENT_REQUIRED' => 'Awaiting Payment',
                                            'RSLDDP_FOR_RESOLUTION' => 'For Final Resolution',
                                            default => \App\Support\StatusLabels::label($statusKey) ?? str($statusKey)->replace('_', ' ')->title()->toString(),
                                        };
                                        $linkedBilling = $billingForIncident($incident);
                                        $responsibleNow = match ($statusKey) {
                                            'OPEN',
                                            'FOR_BILLING',
                                            'RSLDDP_AWAITING_UPLOAD',
                                            'F2F_PROCESSING',
                                            'RSLDDP_DISPOSITION_PENDING',
                                            'RSLDDP_FOR_ACCOUNTING_PROCESSING',
                                            'RSLDDP_FOR_RESOLUTION',
                                            'COMPLIANCE_RSLDDP_PENDING' => 'SPMU Action Officer',
                                            'BILLING_PENDING',
                                            'RSLDDP_PAYMENT_REQUIRED' => 'Borrower',
                                            'RSLDDP_COMPLIANCE_VERIFICATION' => $incident->official_disposition === 'OTHER' ? 'SPMU Head/Admin' : 'Borrower',
                                            'COMPLIANCE_REQUIRED' => 'Borrower',
                                            default => 'SPMU Processing',
                                        };
                                        $headOwnedStatuses = ['OPEN', 'FOR_BILLING', 'F2F_PROCESSING', 'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING', 'RSLDDP_FOR_ACCOUNTING_PROCESSING', 'RSLDDP_FOR_RESOLUTION'];
                                        $officerOwnedStatuses = ['BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED', 'COMPLIANCE_REQUIRED', 'COMPLIANCE_RSLDDP_PENDING'];
                                        $roleOwnsAction = ($isHead && in_array($statusKey, $headOwnedStatuses, true))
                                            || ($isOfficer && in_array($statusKey, $officerOwnedStatuses, true))
                                            || ($isOfficer && $statusKey === 'RSLDDP_COMPLIANCE_VERIFICATION' && in_array($incident->official_disposition, ['REPAIR', 'REPLACEMENT', 'RETURN_RECOVERY', 'MONETARY_SETTLEMENT'], true));
                                        $actionLabel = match ($statusKey) {
                                            'OPEN' => 'Confirm / Clear',
                                            'FOR_BILLING' => 'Generate Billing',
                                            'BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED' => 'Record Receipt',
                                            'F2F_PROCESSING' => 'Resolve Accountability',
                                            'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING' => 'Resolve Accountability',
                                            'COMPLIANCE_RSLDDP_PENDING' => 'Verify Compliance',
                                            'RSLDDP_COMPLIANCE_VERIFICATION' => $incident->official_disposition === 'MONETARY_SETTLEMENT' ? 'Record Receipt' : 'Record Verification',
                                            'COMPLIANCE_REQUIRED' => 'Verify Compliance',
                                            'RSLDDP_FOR_ACCOUNTING_PROCESSING' => 'Record Billing Statement',
                                            'RSLDDP_FOR_RESOLUTION' => 'Resolve Case',
                                            default => 'Open Action',
                                        };
                                        $roleMessage = match ($statusKey) {
                                            'OPEN' => 'SPMU Head/Admin reviews the recorded finding and confirms accountability or clears the finding.',
                                            'FOR_BILLING' => 'SPMU Head/Admin records the approved property Billing Statement details.',
                                            'BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED' => 'Borrower pays through the CSPC Cashier and presents the official receipt. The Action Officer records it when presented.',
                                            'F2F_PROCESSING' => 'The borrower completes the required in-person process with SPMU. Upon completion, the Head/Admin records the final resolution and attaches the accomplished RSLDDP.',
                                            'RSLDDP_AWAITING_UPLOAD', 'RSLDDP_DISPOSITION_PENDING' => 'The required in-person processing must be completed before the Head/Admin records the final resolution and attaches the accomplished RSLDDP.',
                                            'COMPLIANCE_RSLDDP_PENDING' => 'Existing compliance case: the borrower presents the required property compliance and the Action Officer verifies it before final resolution.',
                                            'RSLDDP_COMPLIANCE_VERIFICATION' => $incident->official_disposition === 'MONETARY_SETTLEMENT'
                                                ? 'Borrower pays through the CSPC Cashier and presents the official receipt. The Action Officer records it when presented.'
                                                : 'Borrower completes and presents the required property compliance. The Action Officer physically verifies it.',
                                            'COMPLIANCE_REQUIRED' => 'Borrower completes and presents the required property compliance. The Action Officer physically verifies it.',
                                            'RSLDDP_FOR_ACCOUNTING_PROCESSING' => 'SPMU Head/Admin records the official Accounting-issued Billing Statement after external processing.',
                                            'RSLDDP_FOR_RESOLUTION' => 'SPMU Head/Admin attaches the accomplished RSLDDP and confirms final resolution.',
                                            default => 'Continue the applicable accountability process.',
                                        };
                                        $propertySummary = $incident->lines
                                            ->map(function ($line) use ($incident) {
                                                $custodyLine = $incident->custody?->lines?->firstWhere('id', $line->custody_line_id);
                                                $description = $custodyLine?->requestItem?->description_snapshot ?: 'Inventory item';
                                                $quantity = (float) $line->quantity;
                                                $quantityLabel = fmod($quantity, 1.0) === 0.0 ? (string) (int) $quantity : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
                                                return $description.' ×'.$quantityLabel;
                                            })
                                            ->filter()
                                            ->implode(', ');
                                        $dialogId = 'property-action-'.$incident->id;
                                        $remainingBilling = $linkedBilling
                                            ? max(0, (float) $linkedBilling->total_amount - (float) $linkedBilling->payments->where('status', 'VERIFIED')->sum('amount'))
                                            : null;
                                        $caseDocuments = $documentsForCase('incident-'.$incident->id);
                                        // Keep historical generated RSLDDPs in audit storage, but
                                        // never present them as an accomplished document for an
                                        // unresolved accountability case.
                                        $caseDocuments = $caseDocuments
                                            ->reject(fn ($document) => strtoupper((string) $document->document_type) === 'RSLDDP')
                                            ->values();
                                    @endphp

                                    <article id="incident-{{ $incident->id }}" class="ob-case-card accountability-custody-case tone-warning">
                                        <div class="ob-case-top">
                                            <div class="ob-case-identity">
                                                <span class="ob-case-icon is-warning" aria-hidden="true"><x-icon name="warning" size="17" /></span>
                                                <div>
                                                    <span class="ob-case-type">Property Accountability</span>
                                                    <div class="accountability-case-title-row">
                                                        <h3>{{ str($incident->incident_type)->replace('_', ' ')->title() }}</h3>
                                                        <span class="accountability-case-status is-warning">{{ $statusLabel }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="accountability-case-summary">
                                            <span><strong>Item:</strong> {{ $propertySummary ?: 'See recorded finding' }}</span>
                                            @if($incident->official_disposition)
                                                <span><strong>Approved Requirement:</strong> {{ \App\Support\AccountabilityDispositionLabels::officialDispositionLabel($incident->official_disposition) }}</span>
                                            @endif
                                        </div>

                                        <div class="accountability-case-responsibility">
                                            <div class="accountability-role-copy">
                                                <small>Waiting On</small>
                                                <strong>{{ $responsibleNow }}</strong>
                                            </div>
                                            <div class="accountability-role-copy">
                                                <small>Next Required Step</small>
                                                <span>{{ $roleMessage }}</span>
                                            </div>
                                            @if($roleOwnsAction)
                                                @if(in_array($statusKey, ['BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED'], true) && ! $linkedBilling)
                                                    <span class="muted">Billing record pending</span>
                                                @elseif($statusKey === 'RSLDDP_COMPLIANCE_VERIFICATION' && $incident->official_disposition === 'MONETARY_SETTLEMENT' && ! $linkedBilling)
                                                    <span class="muted">Billing record pending</span>
                                                @elseif(! $shouldAutoOpenFocusedAction)
                                                    <a class="button secondary small accountability-action-button"
                                                       href="{{ route('accountability.borrower', [$borrower, 'tab' => 'cases', 'focus' => 'incident-'.$incident->id]) }}#incident-{{ $incident->id }}">
                                                        {{ $actionLabel }}
                                                    </a>
                                                @endif
                                            @endif
                                        </div>

                                        @include('accountability.partials.case-documents', ['documents' => $caseDocuments])
                                    </article>

                                    @if($shouldAutoOpenFocusedAction && $focusedCaseType === 'incident' && $focusedCaseId === (int) $incident->id && $roleOwnsAction)
                                        <section class="accountability-action-panel" id="{{ $dialogId }}">
                                            <header class="accountability-action-panel__header">
                                                <div>
                                                    <h3>{{ $actionLabel }}</h3>
                                                </div>
                                            </header>
                                            <div class="accountability-action-panel__body">
                                                @if($statusKey === 'OPEN')
                                                    <form method="post" action="{{ route('incidents.resolve', $incident) }}">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Confirm only when the recorded finding establishes borrower accountability. Clear closes this property finding without creating an offense from this case.</p>
                                                        <label>Decision
                                                            <select name="decision" required>
                                                                <option value="">Select decision</option>
                                                                <option value="CONFIRM">Confirm Accountability</option>
                                                                <option value="CLEAR">Clear Finding</option>
                                                            </select>
                                                        </label>
                                                        <label>Decision Remarks
                                                            <textarea name="resolution_remarks" required maxlength="2000" placeholder="State the basis for the decision."></textarea>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Save Decision</button>
                                                        </div>
                                                    </form>
                                                @elseif($isDirectF2fResolution)
                                                    <form method="post" action="{{ route('incidents.rslddp.resolve', $incident) }}" enctype="multipart/form-data">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Attach the accomplished RSLDDP upon completion of the required in-person accountability process.</p>
                                                        <label>Accomplished RSLDDP
                                                            <input type="file" name="rslddp_attachment" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                        </label>
                                                        <label>Resolution Remarks
                                                            <textarea name="resolution_remarks" required maxlength="2000" placeholder="State the basis for resolving this accountability case."></textarea>
                                                        </label>
                                                        <label class="accountability-resolution-confirmation">
                                                            <input type="checkbox" name="f2f_processing_completed" value="1" required>
                                                            <span>I confirm that the required in-person accountability processing has been completed.</span>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Confirm Resolution</button>
                                                        </div>
                                                    </form>
                                                @elseif(in_array($statusKey, ['COMPLIANCE_REQUIRED', 'COMPLIANCE_RSLDDP_PENDING'], true))
                                                    <form method="post" action="{{ route('incidents.resolve', $incident) }}">
                                                        @csrf
                                                        <input type="hidden" name="resolution_outcome" value="COMPLIANCE_COMPLETED">
                                                        <p class="accountability-dialog-note">Legacy case: verify the physical compliance already required by the Head/Admin. This verification does not make a new accountability decision.</p>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Verify Compliance</button>
                                                        </div>
                                                    </form>
                                                @elseif($statusKey === 'RSLDDP_COMPLIANCE_VERIFICATION' && in_array($incident->official_disposition, ['REPAIR', 'REPLACEMENT', 'RETURN_RECOVERY'], true))
                                                    <form method="post" action="{{ route('incidents.disposition.verify', $incident) }}">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Verify the actual {{ strtolower(\App\Support\AccountabilityDispositionLabels::officialDispositionLabel($incident->official_disposition)) }} against the recorded official disposition.</p>
                                                        <label>Verification Decision
                                                            <select name="decision" required>
                                                                <option value="">Select result</option>
                                                                <option value="ACCEPTED">Accepted</option>
                                                                <option value="NOT_ACCEPTED">Not Accepted</option>
                                                            </select>
                                                        </label>
                                                        <label>Remarks <span class="muted">(required if not accepted)</span>
                                                            <textarea name="remarks" maxlength="2000"></textarea>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Record Verification</button>
                                                        </div>
                                                    </form>
                                                @elseif(in_array($statusKey, ['BILLING_PENDING', 'RSLDDP_PAYMENT_REQUIRED'], true) || ($statusKey === 'RSLDDP_COMPLIANCE_VERIFICATION' && $incident->official_disposition === 'MONETARY_SETTLEMENT'))
                                                    @if($linkedBilling)
                                                        <form method="post" action="{{ route('payments.store', $linkedBilling) }}" enctype="multipart/form-data">
                                                            @csrf
                                                            <p class="accountability-dialog-note">Remaining balance: PHP {{ number_format((float) $remainingBilling, 2) }}. Record only the official CSPC Cashier receipt presented by the borrower.</p>
                                                            <div class="form-grid two">
                                                                <label>Cashier Receipt No.
                                                                    <input name="official_receipt_no" required maxlength="255">
                                                                </label>
                                                                <label>Receipt Date
                                                                    <input type="date" name="receipt_date" required max="{{ now()->toDateString() }}">
                                                                </label>
                                                            </div>
                                                            <label>Amount Paid
                                                                <input type="number" name="amount" min="0.01" step="0.01" max="{{ number_format((float) $remainingBilling, 2, '.', '') }}" required>
                                                            </label>
                                                            <label>Scanned Paid Receipt
                                                                <input type="file" name="evidence" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                            </label>
                                                            <label>Remarks <span class="muted">(optional)</span>
                                                                <textarea name="remarks" maxlength="1000"></textarea>
                                                            </label>
                                                            <div class="accountability-dialog-actions">
                                                                <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                                <button class="button primary small">Record Receipt</button>
                                                            </div>
                                                        </form>
                                                    @endif
                                                @elseif($statusKey === 'RSLDDP_FOR_ACCOUNTING_PROCESSING')
                                                    <form method="post" action="{{ route('incidents.rslddp.billing', $incident) }}" enctype="multipart/form-data">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Record the official Billing Statement issued by Accounting. This system does not generate that external document.</p>
                                                        <label>Official Billing Statement
                                                            <input type="file" name="evidence" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                        </label>
                                                        <div class="form-grid two">
                                                            <label>Amount
                                                                <input type="number" name="amount" min="0.01" step="0.01" required>
                                                            </label>
                                                            <label>Accounting Reference No.
                                                                <input name="billing_reference" required maxlength="255">
                                                            </label>
                                                        </div>
                                                        <label>Payment Due Date <span class="muted">(optional)</span>
                                                            <input type="date" name="due_at" min="{{ now()->toDateString() }}">
                                                            <span class="meta">Enter the deadline shown on the official Accounting document, if provided. Do not assign a different deadline.</span>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Record Billing Statement</button>
                                                        </div>
                                                    </form>
                                                @elseif($statusKey === 'RSLDDP_FOR_RESOLUTION')
                                                    <form method="post" action="{{ route('incidents.rslddp.resolve', $incident) }}" enctype="multipart/form-data">
                                                        @csrf
                                                        @if($incident->official_disposition)
                                                        <label>Resolution Outcome
                                                            <select name="resolution_outcome" required>
                                                                <option value="">Select outcome</option>
                                                                @foreach(['MONETARY_SETTLEMENT' => 'Paid / Monetary Settlement', 'REPAIR' => 'Repaired', 'REPLACEMENT' => 'Replaced', 'RETURN_RECOVERY' => 'Returned / Recovered', 'OTHER' => 'Other Approved Outcome'] as $outcomeCode => $outcomeLabel)
                                                                    @if($incident->official_disposition === $outcomeCode)
                                                                        <option value="{{ $outcomeCode }}">{{ $outcomeLabel }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        @endif
                                                        @if($incident->requires_rslddp)
                                                        <label>Accomplished RSLDDP <span class="muted">(attachment)</span>
                                                            <input type="file" name="rslddp_attachment" accept=".pdf,.png,.jpg,.jpeg,.webp" required>
                                                        </label>
                                                        @endif
                                                        <label>Resolution Remarks
                                                            <textarea name="resolution_remarks" required maxlength="2000" placeholder="State the basis for resolving this accountability case."></textarea>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Confirm Resolution</button>
                                                        </div>
                                                    </form>
                                                @elseif($statusKey === 'FOR_BILLING')
                                                    <form method="post" action="{{ route('incidents.bill', $incident) }}">
                                                        @csrf
                                                        <p class="accountability-dialog-note">Legacy property billing stage. Enter only the approved billing details for this existing case.</p>
                                                        <label>Amount
                                                            <input type="number" name="amount" min="0.01" step="0.01" required>
                                                        </label>
                                                        <label>Billing Basis
                                                            <textarea name="basis" required maxlength="2000"></textarea>
                                                        </label>
                                                        <label>Payment Due Date <span class="muted">(optional)</span>
                                                            <input type="date" name="due_at" min="{{ now()->toDateString() }}">
                                                            <span class="meta">Leave blank if no deadline is specified. Unpaid billing remains outstanding; linked restrictions follow existing settlement requirements.</span>
                                                        </label>
                                                        <div class="accountability-dialog-actions">
                                                            <a class="button secondary small" href="{{ route('accountability.index', ['queue' => 'work']) }}#accountability-workspace">Cancel</a>
                                                            <button class="button primary small">Generate Billing</button>
                                                        </div>
                                                    </form>
                                                @endif
                                            </div>
                                        </section>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
            @endif

            @if($standaloneRows->isNotEmpty())
                <div class="accountability-custody-groups accountability-standalone-groups">
                    @foreach($standaloneRows as $row)
                        @php
                            $rowRestriction = $row['restriction'] ?? null;
                            $rowActions = collect($row['actions'] ?? []);
                            $standaloneDetailId = 'accountability-standalone-detail-'.$loop->index;
                        @endphp
                        <article id="{{ $row['key'] ?? 'standalone-'.$loop->index }}" class="ob-case-card accountability-custody-case accountability-standalone-case tone-{{ in_array(($row['tone'] ?? 'warning'), ['danger', 'warning', 'info', 'success', 'orange'], true) ? ($row['tone'] ?? 'warning') : 'warning' }}">
                            <div class="ob-case-top">
                                <div class="ob-case-identity">
                                    <span class="ob-case-icon is-{{ $row['tone'] ?? 'warning' }}" aria-hidden="true">
                                        <x-icon :name="$row['icon'] ?? 'accountability'" size="17" />
                                    </span>
                                    <div>
                                        <span class="ob-case-type">{{ $row['type'] ?? 'Accountability' }}</span>
                                        <div class="accountability-case-title-row">
                                            <h3>{{ $row['title'] ?? 'Open accountability matter' }}</h3>
                                            <span class="accountability-case-status is-{{ ($row['badge_tone'] ?? 'warning') === 'danger' ? 'danger' : (($row['badge_tone'] ?? 'warning') === 'info' ? 'info' : 'warning') }}">{{ $row['badge'] ?? 'Open' }}</span>
                                        </div>
                                        @if(! empty($row['reference']) && $row['reference'] !== ($row['title'] ?? null))
                                            <span class="accountability-standalone-reference">{{ $row['reference'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="accountability-case-summary">
                                <span>{{ $row['summary'] ?? 'This accountability matter remains open.' }}</span>
                                @if(! empty($row['status_meta']))
                                    <span><strong>Status:</strong> {{ $row['status_meta'] }}</span>
                                @endif
                            </div>

                            <div class="accountability-case-responsibility">
                                <div class="accountability-role-copy">
                                    <small>Current Stage</small>
                                    <strong>{{ ($row['action_state'] ?? null) === \App\Services\BorrowerObligationService::ACTION_BORROWER ? 'Borrower action pending' : 'SPMU / policy processing' }}</strong>
                                </div>
                                <div class="accountability-role-copy">
                                    <small>Next Step</small>
                                    <span>{{ $row['next_action'] ?? 'Continue the applicable accountability process.' }}</span>
                                </div>
                                @if($rowActions->isNotEmpty() || ! empty($row['facts']) || $rowRestriction)
                                    <button
                                        type="button"
                                        class="button secondary small ui-pressable disclosure-trigger accountability-standalone-toggle"
                                        data-accountability-standalone-toggle
                                        aria-expanded="false"
                                        aria-controls="{{ $standaloneDetailId }}"
                                    >
                                        <span>View Details</span>
                                        <x-icon name="chevron-down" size="14" class="disclosure-trigger__chevron" />
                                    </button>
                                @endif
                            </div>

                            @if($rowActions->isNotEmpty() || ! empty($row['facts']) || $rowRestriction)
                                <div id="{{ $standaloneDetailId }}" class="accountability-standalone-detail-body" hidden>
                                    @if(! empty($row['facts']))
                                        <div class="ob-detail-grid">
                                            @foreach($row['facts'] as [$factLabel, $factValue])
                                                <div><small>{{ $factLabel }}</small><strong>{{ $factValue }}</strong></div>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($rowRestriction)
                                        <div class="ob-detail-grid">
                                            <div><small>Restriction</small><strong>{{ $rowRestriction->reason ?: str($rowRestriction->restriction_type)->replace('_', ' ')->title() }}</strong></div>
                                            <div><small>Effective</small><strong>{{ optional($rowRestriction->effective_from)->format('d M Y') ?: '—' }}</strong></div>
                                            <div><small>Until</small><strong>{{ optional($rowRestriction->effective_to)->format('d M Y') ?: 'Resolved' }}</strong></div>
                                        </div>
                                    @endif
                                    @if($rowActions->isNotEmpty())
                                        <div class="ob-detail-actions">
                                            @foreach($rowActions as $action)
                                                @php [$label, $url, $newTab, $buttonTone] = $action; @endphp
                                                <a class="button secondary small ui-pressable" href="{{ $url }}" @if($newTab) target="_blank" rel="noopener" @endif>{{ $label }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        @endif
    </section>
@else
    <section role="tabpanel" aria-label="History">
        @include('accountability.partials.resolved-history', [
            'resolvedHistory' => $resolvedHistory,
            'isBorrower' => false,
            'embeddedInBorrowerWorkspace' => true,
        ])
    </section>
@endif
</div>

@include('accountability.partials.cases-interactions')

<script>
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-accountability-standalone-toggle]');
    if (!toggle) return;

    const detailId = toggle.getAttribute('aria-controls');
    const detail = detailId ? document.getElementById(detailId) : null;
    if (!detail) return;

    const expanded = toggle.getAttribute('aria-expanded') === 'true';

    if (!expanded) {
        document.querySelectorAll('[data-accountability-standalone-toggle][aria-expanded="true"]').forEach((otherToggle) => {
            if (otherToggle === toggle) return;

            const otherDetailId = otherToggle.getAttribute('aria-controls');
            const otherDetail = otherDetailId ? document.getElementById(otherDetailId) : null;
            if (otherDetail) otherDetail.hidden = true;

            otherToggle.setAttribute('aria-expanded', 'false');
            const otherLabel = otherToggle.querySelector('span');
            if (otherLabel) otherLabel.textContent = 'View Details';
        });
    }

    const willExpand = !expanded;
    toggle.setAttribute('aria-expanded', String(willExpand));
    detail.hidden = !willExpand;

    const label = toggle.querySelector('span');
    if (label) {
        label.textContent = willExpand ? 'Hide Details' : 'View Details';
    }
});

</script>



@endsection
