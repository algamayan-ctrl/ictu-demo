<style>

.analytics-page { display: grid; gap: 16px; }

html:has(.analytics-page) { scroll-padding-top: calc(var(--topbar-height) + 12px); }
body:has(.analytics-page) .app-topbar { background: var(--surface-elevated); }

.analytics-filters {
 display: grid;
 grid-template-columns: repeat(4, minmax(0, 1fr));
 gap: 12px;
 padding: 12px 16px;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: var(--radius);
 box-shadow: var(--shadow-sm);
}

.analytics-filters label {
 display: grid;
 gap: 6px;
 margin: 0;
 min-width: 0;
 color: var(--heading);
 font-size: 12.5px;
 font-weight: 700;
}

.analytics-filters select {
 width: 100%;
 min-height: 42px;
 margin: 0;
 font-size: 13px;
 font-weight: 400;
}

.analytics-period-note {
 grid-column: 1 / -1;
 margin: 0;
 color: var(--text-muted);
 font-size: 12px;
}

.analytics-section {
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: var(--radius);
 box-shadow: var(--shadow-sm);
 overflow: hidden;
}

.analytics-section > h2 {
 margin: 0;
 padding: 12px 16px;
 color: var(--heading);
 font-size: 16px;
 font-weight: 700;
 border-bottom: 1px solid var(--border);
}

.analytics-section-body { padding: 16px 16px; }

.analytics-reading {
 margin: 0;
 padding: 13px 16px;
 color: var(--text-secondary);
 background: var(--surface-subtle);
 border-top: 1px solid var(--border);
 font-size: 13px;
 line-height: 1.55;
}

.analytics-empty {
 margin: 0;
 padding: 13px 16px;
 color: var(--text-muted);
 background: var(--surface-subtle);
 font-size: 13px;
 line-height: 1.5;
}

.analytics-section.is-empty > h2 { border-bottom: 0; }

.analytics-section.is-empty {
 display: grid;
 grid-template-columns: minmax(0, auto) minmax(0, 1fr);
 align-items: center;
}

.analytics-section.is-empty > h2 { padding-right: 0; }

.analytics-section.is-empty .analytics-empty { background: transparent; }

.analytics-figures {
 display: grid;
 grid-template-columns: repeat(4, minmax(0, 1fr));
}

.analytics-figure {
 min-width: 0;
 padding: 16px 16px;
 border-right: 1px solid var(--border);
}

.analytics-figure:last-child { border-right: 0; }

.analytics-figure strong {
 display: block;
 color: var(--heading);
 font-size: 30px;
 font-weight: 750;
 line-height: 1.05;
 letter-spacing: -.02em;
 font-variant-numeric: tabular-nums;
}

.analytics-figure span {
 display: block;
 margin-top: 5px;
 color: var(--text-secondary);
 font-size: 13px;
 font-weight: 650;
 line-height: 1.35;
}

.analytics-figure small {
 display: block;
 margin-top: 3px;
 color: var(--text-muted);
 font-size: 11.5px;
 line-height: 1.35;
}

.analytics-figure.is-attention strong { color: var(--danger); }

.analytics-figures.is-secondary .analytics-figure strong { font-size: 22px; }
.analytics-figures.is-secondary .analytics-figure { padding: 12px 16px; }

.analytics-bars { display: grid; gap: 13px; }

.analytics-bar-row { display: grid; gap: 6px; min-width: 0; }

.analytics-bar-head {
 display: flex;
 align-items: baseline;
 justify-content: space-between;
 gap: 12px;
}

.analytics-bar-name {
 color: var(--heading);
 font-size: 13px;
 font-weight: 650;
 overflow-wrap: anywhere;
}

.analytics-bar-value {
 flex: 0 0 auto;
 color: var(--text-secondary);
 font-size: 12.5px;
 font-weight: 700;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-bars.is-single .analytics-bar-track { display: none; }

.analytics-bars.is-single .analytics-bar-head { align-items: baseline; }

.analytics-bar-track {
 height: 10px;
 background: var(--surface-muted);
 border-radius: 999px;
 overflow: hidden;
}

.analytics-bar-fill {
 height: 100%;
 background: var(--interactive);
 border-radius: 999px;
}

.analytics-bar-link {
 display: grid;
 gap: 6px;
 padding: 9px 11px;
 border: 1px solid transparent;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
 cursor: pointer;
 transition: background-color var(--motion) ease, border-color var(--motion) ease;
}

.analytics-bar-link:hover {
 background: var(--surface-subtle);
 border-color: var(--border);
}

.analytics-bar-link:focus-visible {
 outline: none;
 border-color: var(--interactive);
 box-shadow: var(--focus-ring);
}

.analytics-bar-link.is-selected {
 background: var(--blue-50);
 border-color: var(--info-border);
}

.analytics-bar-link.is-selected .analytics-bar-name { font-weight: 750; }

@media (prefers-reduced-motion: reduce) {
 .analytics-bar-link { transition: none; }
}

.analytics-bar-row.is-academic .analytics-bar-fill { background: #1769e0; }
.analytics-bar-row.is-administration .analytics-bar-fill { background: #0e7c66; }
.analytics-bar-row.is-research .analytics-bar-fill { background: #7a4bc4; }

.analytics-split {
 display: grid;
 grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.analytics-split > section {
 min-width: 0;
 padding: 16px 16px;
 border-right: 1px solid var(--border);
}

.analytics-split > section:last-child { border-right: 0; }

.analytics-split h3 {
 margin: 0 0 12px;
 color: var(--text-muted);
 font-size: 11px;
 font-weight: 700;
 letter-spacing: .06em;
 text-transform: uppercase;
}

.analytics-insights {
 display: grid;
 gap: 0;
}

.analytics-insight {
 display: grid;
 grid-template-columns: 22px minmax(0, 1fr);
 gap: 12px;
 align-items: start;
 padding: 13px 16px;
 border-bottom: 1px solid var(--row-border);
 color: var(--text-secondary);
 font-size: 13px;
 line-height: 1.55;
}

.analytics-insight:last-child { border-bottom: 0; }

.analytics-insight-mark {
 display: grid;
 place-items: center;
 width: 22px;
 height: 22px;
 color: var(--interactive);
 background: var(--blue-50);
 border-radius: 50%;
 font-size: 11px;
 font-weight: 750;
 font-variant-numeric: tabular-nums;
}

@media (max-width: 1050px) {
 .analytics-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
 .analytics-figures { grid-template-columns: repeat(2, minmax(0, 1fr)); }
 .analytics-figure:nth-child(2n) { border-right: 0; }
 .analytics-figure:nth-child(-n + 2) { border-bottom: 1px solid var(--border); }
}

@media (max-width: 760px) {

 .analytics-section.is-empty { grid-template-columns: 1fr; }
 .analytics-section.is-empty > h2 { padding-bottom: 4px; }
 .analytics-section.is-empty .analytics-empty { padding-top: 0; }
}

@media (max-width: 620px) {
 .analytics-filters,
 .analytics-figures { grid-template-columns: 1fr; }

 .analytics-figure {
 border-right: 0;
 border-bottom: 1px solid var(--border);
 }

 .analytics-figure:last-child { border-bottom: 0; }

 .analytics-split > section {
 border-right: 0;
 border-bottom: 1px solid var(--border);
 }

 .analytics-split > section:last-child { border-bottom: 0; }
}

.analytics-tabs {
 display: flex;
 flex-wrap: wrap;
 gap: 6px;
 padding: 6px;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: var(--radius);
 box-shadow: var(--shadow-sm);
}

.analytics-tab {
 padding: 9px 12px;
 border-radius: 7px;
 color: var(--text-secondary);
 font-size: 13px;
 font-weight: 700;
 text-decoration: none;
 white-space: nowrap;
 transition: color var(--motion-fast) ease, background-color var(--motion-fast) ease;
}

.analytics-tab:hover { color: var(--interactive); background: var(--surface-hover); }

.analytics-tab.is-active {
 color: var(--heading);
 background: var(--info-bg);
 box-shadow: inset 0 -2px 0 var(--primary-action);
}

.analytics-kpis {
 display: grid;
 grid-template-columns: repeat(4, minmax(0, 1fr));
 gap: 12px;
}

.analytics-kpi {
 display: grid;
 gap: 5px;
 align-content: start;
 padding: 16px 16px;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: var(--radius);
 box-shadow: var(--shadow-sm);
 min-width: 0;
}

.analytics-kpi-label {
 color: var(--text-muted);
 font-size: 11px;
 font-weight: 800;
 letter-spacing: .06em;
 text-transform: uppercase;
}

.analytics-kpi > strong {
 color: var(--heading);
 font-size: 30px;
 font-weight: 750;
 line-height: 1.1;
}

.analytics-kpi > strong.is-text {
 font-size: 17px;
 line-height: 1.3;
 overflow-wrap: anywhere;
}

.analytics-kpi > small {
 color: var(--text-muted);
 font-size: 11.5px;
 line-height: 1.5;
}

.analytics-kpi.is-attention { border-color: var(--danger-border); }
.analytics-kpi.is-attention > strong { color: var(--danger); }

.analytics-kpi-link {
 --kpi-tone: var(--interactive);
 --kpi-tone-bg: var(--blue-50);
 --kpi-tone-border: var(--info-border);

 position: relative;
 gap: 8px;
 padding: 12px 16px 16px;
 border-top: 3px solid var(--kpi-tone);
 color: inherit;
 text-decoration: none;
 cursor: pointer;
 transition:
 border-color var(--motion) ease,
 box-shadow var(--motion) ease,
 background-color var(--motion) ease,
 transform var(--motion) ease;
}

.analytics-kpi-link.tone-requests {
 --kpi-tone: #0f62d6;
 --kpi-tone-bg: #eaf2fd;
 --kpi-tone-border: #bcd6f7;
}

.analytics-kpi-link.tone-custody {
 --kpi-tone: #0b7285;
 --kpi-tone-bg: #e4f5f8;
 --kpi-tone-border: #b3dde5;
}

.analytics-kpi-link.tone-followup {
 --kpi-tone: #b45309;
 --kpi-tone-bg: #fdf3e4;
 --kpi-tone-border: #f0d6ab;
}

.analytics-kpi-link.tone-stock {
 --kpi-tone: #b42318;
 --kpi-tone-bg: #fdeceb;
 --kpi-tone-border: #f2c2be;
}

.analytics-kpi-top {
 display: flex;
 align-items: center;
 gap: 10px;
 min-width: 0;
}

.analytics-kpi-icon {
 display: grid;
 place-items: center;
 width: 30px;
 height: 30px;
 flex-shrink: 0;
 border: 1px solid var(--kpi-tone-border);
 border-radius: 8px;
 background: var(--kpi-tone-bg);
 color: var(--kpi-tone);
}

.analytics-kpi-link .analytics-kpi-label {
 min-width: 0;
 color: var(--kpi-tone);
 overflow-wrap: anywhere;
}

.analytics-kpi-arrow {
 flex-shrink: 0;
 margin-left: auto;
 color: var(--text-soft);
 transition: transform var(--motion) ease, color var(--motion) ease;
}

.analytics-kpi-link > strong { color: var(--heading); }

.analytics-kpi-link:hover,
.analytics-kpi-link:focus-visible {
 background: var(--kpi-tone-bg);
 border-color: var(--kpi-tone-border);
 border-top-color: var(--kpi-tone);
 box-shadow: 0 8px 20px rgba(7, 27, 53, .08);
 transform: translateY(-1px);
}

.analytics-kpi-link:hover .analytics-kpi-arrow,
.analytics-kpi-link:focus-visible .analytics-kpi-arrow {
 color: var(--kpi-tone);
 transform: translateX(2px);
}

.analytics-kpi-link:focus-visible {
 outline: none;
 box-shadow: var(--focus-ring);
}

html[data-theme="dark"] .analytics-kpi-link.tone-requests {
 --kpi-tone: #72b7f4;
 --kpi-tone-bg: #14263c;
 --kpi-tone-border: #2c4c72;
}

html[data-theme="dark"] .analytics-kpi-link.tone-custody {
 --kpi-tone: #6fc9da;
 --kpi-tone-bg: #102b31;
 --kpi-tone-border: #2b5a63;
}

html[data-theme="dark"] .analytics-kpi-link.tone-followup {
 --kpi-tone: #f3c56a;
 --kpi-tone-bg: #2e2411;
 --kpi-tone-border: #6b5327;
}

html[data-theme="dark"] .analytics-kpi-link.tone-stock {
 --kpi-tone: #ff9b93;
 --kpi-tone-bg: #33191b;
 --kpi-tone-border: #6f3c40;
}

@media (prefers-reduced-motion: reduce) {
 .analytics-kpi-link,
 .analytics-kpi-arrow { transition: none; }
 .analytics-kpi-link:hover,
 .analytics-kpi-link:focus-visible { transform: none; }
}

.analytics-status {
 display: inline-block;
 padding: 3px 10px;
 border-radius: 999px;
 font-size: 10.5px;
 font-weight: 750;
 letter-spacing: .02em;
 white-space: nowrap;
}

.analytics-status.is-sufficient,
.analytics-status.is-normal { color: var(--success); background: var(--success-bg); }

.analytics-status.is-limited,
.analytics-status.is-moderate { color: var(--warning); background: var(--warning-bg); }

.analytics-status.is-possible-shortage,
.analytics-status.is-unavailable,
.analytics-status.is-high { color: var(--danger); background: var(--danger-bg); }

.analytics-tag {
 display: inline-block;
 margin-left: 7px;
 padding: 2px 7px;
 border-radius: 999px;
 background: var(--surface-muted);
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 750;
 letter-spacing: .04em;
 text-transform: uppercase;
}

.analytics-bar-row.is-forecast .analytics-bar-fill {
 background: repeating-linear-gradient(
 135deg,
 var(--interactive),
 var(--interactive) 6px,
 transparent 6px,
 transparent 12px
 ), var(--interactive);
 opacity: .85;
}

.analytics-bar-row.is-forecast .analytics-tag {
 background: var(--info-bg);
 color: var(--info);
}

.analytics-table-scroll { overflow-x: auto; }

.analytics-table {
 width: 100%;
 min-width: 480px;
 margin: 0;
 border-collapse: collapse;
}

.analytics-table th,
.analytics-table td {
 padding: 11px 12px;
 border-bottom: 1px solid var(--row-border);
 font-size: 12.5px;
 text-align: left;
 vertical-align: top;
}

.analytics-table thead th {
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 800;
 letter-spacing: .05em;
 text-transform: uppercase;
 white-space: nowrap;
}

.analytics-table tbody th { color: var(--heading); font-weight: 700; }
.analytics-table tbody th small { display: block; margin-top: 3px; color: var(--text-muted); font-size: 11px; font-weight: 500; }
.analytics-table .is-numeric { text-align: right; font-variant-numeric: tabular-nums; }
.analytics-table tbody tr:last-child th,
.analytics-table tbody tr:last-child td { border-bottom: 0; }

.analytics-metric-note {
 margin: 0 0 13px;
 color: var(--text-muted);
 font-size: 11.5px;
 line-height: 1.5;
}

.analytics-action { margin: 12px 0 0; font-size: 12.5px; font-weight: 700; }
.analytics-action a { color: var(--interactive); }

.analytics-watch {
 display: flex;
 flex-wrap: wrap;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 12px 12px;
 background: var(--surface-subtle);
 border: 1px solid var(--border);
 border-radius: 10px;
}

.analytics-watch strong { display: block; color: var(--heading); font-size: 14.5px; }
.analytics-watch span { color: var(--text-muted); font-size: 12.5px; }

.analytics-forecast-window {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 gap: 12px;
 padding: 12px 16px;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: var(--radius);
 box-shadow: var(--shadow-sm);
}

.analytics-forecast-window strong {
 display: block;
 margin-top: 4px;
 color: var(--heading);
 font-size: 14px;
}

.analytics-details { margin-top: 12px; font-size: 12px; }
.analytics-details summary { color: var(--interactive); cursor: pointer; font-weight: 700; }
.analytics-details ul { margin: 10px 0 0; padding-left: 20px; color: var(--text-secondary); line-height: 1.7; }

@media (max-width: 1050px) {
 .analytics-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 620px) {
 .analytics-kpis,
 .analytics-forecast-window { grid-template-columns: 1fr; }

 .analytics-tabs { flex-wrap: nowrap; overflow-x: auto; }
}

.analytics-page .analytics-reset-filters {
 display: none !important;
}

</style>

<style>

.analytics-notice {
 display: flex;
 align-items: flex-start;
 gap: 10px;
 margin: 0 0 12px;
 padding: 13px 12px;
 background: var(--warning-bg);
 border: 1px solid var(--warning-border);
 border-radius: var(--radius);
 color: var(--warning);
 font-size: 12.5px;
 line-height: 1.55;
}
.analytics-notice .ui-icon { flex: 0 0 auto; margin-top: 1px; }

.analytics-kpi-basis {
 display: block;
 margin-top: 6px;
 padding-top: 7px;
 border-top: 1px solid var(--row-border);
 color: var(--text-soft);
 font-size: 10.5px;
 line-height: 1.4;
}

.analytics-trend {
 display: flex;
 align-items: flex-end;
 gap: 10px;
 min-height: 190px;
 padding: 12px 2px 0;
 overflow-x: auto;
}
.analytics-trend-col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 7px; min-width: 54px; flex: 1 1 0; height: 170px; }
.analytics-trend-bar { position: relative; display: block; width: 100%; max-width: 46px; min-height: 3px; background: var(--interactive); border-radius: 4px 4px 0 0; }
.analytics-trend-count { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); color: var(--heading); font-size: 11px; font-weight: 750; font-variant-numeric: tabular-nums; }
.analytics-trend-label { color: var(--text-muted); font-size: 10.5px; text-align: center; line-height: 1.3; }

.analytics-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 12px; margin-bottom: 12px; }
.analytics-stat {
 display: grid;
 gap: 5px;
 padding: 13px 12px;
 background: var(--surface-subtle);
 border: 1px solid var(--border);
 border-radius: var(--radius-sm);
 color: inherit;
 text-decoration: none;
}
.analytics-stat > span { color: var(--text-muted); font-size: 11px; font-weight: 700; letter-spacing: .02em; }
.analytics-stat > strong { color: var(--heading); font-size: 21px; font-weight: 750; font-variant-numeric: tabular-nums; }
.analytics-stat > strong.is-text { font-size: 14px; }
a.analytics-stat:hover { border-color: var(--interactive); background: var(--surface-hover); }
a.analytics-stat:focus-visible { outline: 0; box-shadow: var(--focus-ring); }
.analytics-stat.is-static { background: var(--surface-elevated); }

.analytics-table-scroll { width: 100%; overflow-x: auto; }
.analytics-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.analytics-table th {
 padding: 8px 10px;
 text-align: left;
 color: var(--text-secondary);
 background: var(--table-heading-bg);
 border-bottom: 1px solid var(--border-strong);
 font-size: 10px;
 font-weight: 800;
 letter-spacing: .05em;
 text-transform: uppercase;
 white-space: nowrap;
}
.analytics-table td { padding: 8px 10px; border-top: 1px solid var(--row-border); color: var(--text); vertical-align: top; }
.analytics-table td.numeric, .analytics-table th.numeric { text-align: right; font-variant-numeric: tabular-nums; }
.analytics-table a { color: var(--interactive); text-decoration: none; }
.analytics-table a:hover { text-decoration: underline; }

.analytics-tag {
 display: inline-flex;
 align-items: center;
 min-height: 20px;
 padding: 2px 8px;
 border: 1px solid var(--neutral-border);
 border-radius: 999px;
 background: var(--neutral-bg);
 color: var(--neutral);
 font-size: 10.5px;
 font-weight: 700;
 white-space: nowrap;
}
.analytics-tag.is-positive { color: var(--success); background: var(--success-bg); border-color: var(--success-border); }
.analytics-tag.is-attention { color: var(--warning); background: var(--warning-bg); border-color: var(--warning-border); }
.analytics-tag.is-critical { color: var(--danger); background: var(--danger-bg); border-color: var(--danger-border); }

.analytics-explain { margin: 12px 0 0; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--surface-subtle); }
.analytics-explain > summary { padding: 10px 13px; cursor: pointer; color: var(--text-secondary); font-size: 12px; font-weight: 700; }
.analytics-explain > div { display: grid; gap: 7px; padding: 0 13px 13px; }
.analytics-explain p { margin: 0; color: var(--text-secondary); font-size: 12px; line-height: 1.55; }
.analytics-formula { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--heading) !important; font-size: 12.5px !important; }

.analytics-metric-note-spaced { margin-top: 12px; }
.analytics-bar-name small { display: block; margin-top: 2px; color: var(--text-muted); font-size: 10.5px; font-weight: 400; }

.analytics-tabs { overflow-x: auto; scrollbar-width: thin; }
.analytics-tab { white-space: nowrap; }

@media (max-width: 700px) {
 .analytics-trend-col { min-width: 44px; }
 .analytics-stat-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
}

</style>

<style>

.analytics-detail-overlay {
 position: fixed;
 inset: 0;
 z-index: 60;
 display: flex;
 justify-content: flex-end;
}

.analytics-detail-scrim {
 position: absolute;
 inset: 0;
 background: rgba(7, 27, 53, .42);
 display: block;
}

.analytics-detail {
 position: relative;
 display: flex;
 flex-direction: column;
 width: min(660px, 100%);
 max-height: 100%;
 background: var(--surface-elevated);
 border-left: 1px solid var(--border);
 box-shadow: var(--shadow);
}

.analytics-detail:focus { outline: 0; }

.analytics-detail-head {
 display: flex;
 align-items: flex-start;
 justify-content: space-between;
 gap: 12px;
 padding: 16px 16px 12px;
 border-bottom: 1px solid var(--border);
}
.analytics-detail-context { margin: 0; color: var(--text-muted); font-size: 10.5px; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
.analytics-detail-head h2 { margin: 4px 0 0; color: var(--heading); font-size: 17px; font-weight: 750; line-height: 1.3; }
.analytics-detail-head h2:focus { outline: 0; }
.analytics-detail-scope { margin: 5px 0 0; color: var(--text-muted); font-size: 11.5px; }
.analytics-detail-close { flex: 0 0 auto; }

.analytics-detail-body { display: grid; gap: 12px; padding: 16px 16px; overflow-y: auto; }

.analytics-detail-figure { display: flex; align-items: baseline; gap: 9px; margin: 0; }
.analytics-detail-figure strong { color: var(--heading); font-size: 30px; font-weight: 750; line-height: 1; font-variant-numeric: tabular-nums; }
.analytics-detail-figure span { color: var(--text-muted); font-size: 12.5px; }

.analytics-detail-note {
 margin: 0;
 padding: 11px 13px;
 background: var(--surface-subtle);
 border: 1px solid var(--border);
 border-radius: var(--radius-sm);
 color: var(--text-secondary);
 font-size: 12px;
 line-height: 1.6;
}

.analytics-detail-subhead { margin: 4px 0 0; color: var(--text-secondary); font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }

.analytics-compare { display: grid; gap: 9px; margin-top: 6px; padding-top: 12px; border-top: 1px solid var(--border); }
.analytics-compare .analytics-detail-subhead { margin: 0; }
.analytics-compare-window { margin: -5px 0 0; color: var(--text-muted); font-size: 11.5px; }
.analytics-compare-bars { gap: 8px; max-width: 420px; }
.analytics-compare-rows { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px 12px; margin: 0; }
.analytics-compare-rows > div { display: grid; gap: 2px; min-width: 0; }
.analytics-compare-rows dt { margin: 0; color: var(--text-muted); font-size: 11px; font-weight: 700; letter-spacing: .02em; }
.analytics-compare-rows dd { margin: 0; color: var(--heading); font-size: 14px; font-weight: 750; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.analytics-compare-note { margin: 0; color: var(--text-muted); font-size: 11.5px; line-height: 1.5; }

.analytics-detail-body .analytics-stat-grid { margin-bottom: 0; }
.analytics-detail-body .analytics-empty { margin: 0; }

.analytics-detail-foot {
 display: flex;
 justify-content: flex-end;
 padding: 12px 16px;
 border-top: 1px solid var(--border);
 background: var(--surface-subtle);
}
.analytics-detail-foot .button { gap: 7px; }

.analytics-section-action { margin: 12px 0 0; }
.analytics-section-action a { display: inline-flex; align-items: center; gap: 5px; color: var(--interactive); font-size: 12px; font-weight: 700; text-decoration: none; }
.analytics-section-action a:hover { text-decoration: underline; }

a.analytics-trend-col { text-decoration: none; border-radius: 6px; transition: background-color var(--motion) ease; }
a.analytics-trend-col:hover { background: var(--surface-hover); }
a.analytics-trend-col:focus-visible { outline: 0; box-shadow: var(--focus-ring); }
a.analytics-trend-col:hover .analytics-trend-bar { background: var(--interactive-hover); }

@media (max-width: 720px) {
 .analytics-detail { width: 100%; border-left: 0; }
 .analytics-detail-figure strong { font-size: 26px; }
}

@media (prefers-reduced-motion: reduce) {
 a.analytics-trend-col { transition: none; }
}

</style>

<style>

.analytics-trend { justify-content: flex-start; }
.analytics-trend-col { flex: 0 1 96px; max-width: 96px; }

.analytics-detail-body { flex: 1 1 auto; min-height: 0; }

</style>

<style>

.analytics-bar-fill { display: block; min-width: 2px; }

.analytics-bar-track { position: relative; }

.analytics-guard-note {
 margin: -4px 0 12px;
 padding: 10px 12px;
 background: var(--surface-subtle);
 border: 1px solid var(--border);
 border-radius: var(--radius-sm);
 color: var(--text-muted);
 font-size: 11.5px;
 line-height: 1.55;
}

.analytics-detail-body { align-content: start; }

.analytics-page { display: grid; gap: 13px; }

.analytics-heading { margin-bottom: 13px; }
.analytics-heading h1 { margin: 1px 0 3px; font-size: clamp(20px, 1.5vw, 24px); }
.analytics-heading p:not(.eyebrow) { font-size: 12.5px; line-height: 1.45; }
.analytics-heading .eyebrow { margin-bottom: 2px; }

.analytics-page .analytics-tabs {
 display: flex;
 gap: 3px;
 padding: 4px;
 border-radius: 11px;
 box-shadow: none;
}

.analytics-page .analytics-tab {
 flex: 1 1 0;
 min-height: 36px;
 padding: 0 10px;
 display: inline-flex;
 align-items: center;
 justify-content: center;
 border-radius: 8px;
 font-size: 12.5px;
 text-align: center;
}

.analytics-page .analytics-tab.is-active {
 color: #fff;
 background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
 box-shadow: 0 3px 10px rgba(37, 99, 235, .25);
}

html[data-theme="dark"] .analytics-page .analytics-tab.is-active {
 background: linear-gradient(135deg, #1d4ed8 0%, #4338ca 100%);
 box-shadow: 0 3px 10px rgba(0, 0, 0, .45);
}

.analytics-page .analytics-filters {
 padding: 0;
 background: transparent;
 border: 0;
 border-radius: 0;
 box-shadow: none;
 gap: 12px;
}

.analytics-page .analytics-filters label,
.analytics-page .analytics-filters .analytics-filter-field {
 display: grid;
 grid-template-columns: minmax(0, 1fr);
 grid-template-rows: auto auto;
 align-content: center;
 row-gap: 2px;
 padding: 8px 12px;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: 9px;
 box-shadow: none;
 position: relative;
}

.analytics-filter-label {
 display: block;
 min-width: 0;
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 700;
 letter-spacing: .04em;
 line-height: 1.3;
 text-transform: uppercase;
 overflow-wrap: anywhere;
}

.analytics-page .analytics-filters select {
 min-height: 32px;
 padding-top: 0;
 padding-bottom: 0;
 padding-left: 0;
 border-color: transparent;
 background-color: transparent;
 color: var(--heading);
 font-size: 12.5px;
 font-weight: 650;
}

.analytics-page .analytics-filters select:hover { background-color: var(--surface-subtle); }
.analytics-page .analytics-filters select:focus { padding-left: 9px; border-color: var(--interactive); background-color: var(--input-bg); }

.analytics-page .analytics-period-note {
 grid-column: 1 / -1;
 margin: -4px 0 0;
 padding-left: 2px;
 color: var(--text-soft);
 font-size: 10.5px;
 line-height: 1.35;
}

.analytics-page .analytics-reset-filters {
 grid-column: 1 / -1;
 justify-self: start;
 margin: -2px 0 0;
 padding-left: 2px;
 color: var(--interactive, #1769e0);
 font-size: 11.5px;
 font-weight: 700;
 text-decoration: none;
}

.analytics-page .analytics-reset-filters:hover,
.analytics-page .analytics-reset-filters:focus-visible {
 text-decoration: underline;
}

.analytics-kpis {
 display: grid;
 grid-template-columns: repeat(4, minmax(0, 1fr));
 gap: 13px;
}

.analytics-kpi-card {
 --kpi-from: #2563eb;
 --kpi-to: #38bdf8;
 --kpi-glow: rgba(37, 99, 235, .3);

 position: relative;
 display: grid;
 grid-template-columns: 38px minmax(0, 1fr) 14px;
 grid-template-rows: auto auto auto auto;
 align-content: center;
 column-gap: 11px;
 row-gap: 1px;
 min-width: 0;
 min-height: 118px;
 padding: 12px 12px;
 overflow: hidden;
 background: linear-gradient(135deg, var(--kpi-from) 0%, var(--kpi-to) 100%);
 border: 0;
 border-radius: 13px;
 box-shadow: 0 4px 14px var(--kpi-glow);
 color: #fff;
 text-decoration: none;
 transition: box-shadow var(--motion) ease, transform var(--motion) ease;
}

.analytics-kpi-card::after {
 content: '';
 position: absolute;
 top: -46px;
 right: -34px;
 width: 132px;
 height: 132px;
 border-radius: 50%;
 background: rgba(255, 255, 255, .13);
 pointer-events: none;
}

.analytics-kpi-card > * { position: relative; z-index: 1; }

.analytics-kpi-card-icon {
 display: grid;
 grid-column: 1;
 grid-row: 1 / span 2;
 place-items: center;
 width: 38px;
 height: 38px;
 color: #fff;
 background: rgba(255, 255, 255, .2);
 border: 1px solid rgba(255, 255, 255, .26);
 border-radius: 50%;
}

.analytics-kpi-card-label {
 grid-column: 2;
 grid-row: 1;
 align-self: end;
 min-width: 0;
 color: rgba(255, 255, 255, .92);
 font-size: 10px;
 font-weight: 800;
 letter-spacing: .07em;
 text-transform: uppercase;
 overflow-wrap: anywhere;
}

.analytics-kpi-card-value {
 grid-column: 2;
 grid-row: 2;
 color: #fff;
 font-size: 28px;
 font-weight: 780;
 line-height: 1.05;
 font-variant-numeric: tabular-nums;
}

.analytics-kpi-card-note {
 grid-column: 1 / -1;
 grid-row: 3;
 margin-top: 9px;
 color: rgba(255, 255, 255, .95);
 font-size: 11.5px;
 font-weight: 600;
 line-height: 1.35;
}

.analytics-kpi-card-meta {
 grid-column: 1 / -1;
 grid-row: 4;
 margin-top: 2px;
 overflow: hidden;
 color: rgba(255, 255, 255, .72);
 font-size: 10px;
 line-height: 1.35;
 text-overflow: ellipsis;
 white-space: nowrap;
}

.analytics-kpi-card-arrow {
 grid-column: 3;
 grid-row: 1 / span 2;
 align-self: center;
 color: rgba(255, 255, 255, .8);
 transition: transform var(--motion) ease;
}

.analytics-kpi-card:hover,
.analytics-kpi-card:focus-visible {
 box-shadow: 0 10px 24px var(--kpi-glow);
 transform: translateY(-2px);
}

.analytics-kpi-card:hover .analytics-kpi-card-arrow { transform: translateX(2px); }
.analytics-kpi-card:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }

.analytics-kpi-card.tone-requests { --kpi-from: #2563eb; --kpi-to: #38bdf8; --kpi-glow: rgba(37, 99, 235, .3); }
.analytics-kpi-card.tone-custody { --kpi-from: #0f766e; --kpi-to: #10b981; --kpi-glow: rgba(15, 118, 110, .28); }
.analytics-kpi-card.tone-overdue { --kpi-from: #c2410c; --kpi-to: #f59e0b; --kpi-glow: rgba(194, 65, 12, .28); }
.analytics-kpi-card.tone-stock { --kpi-from: #6d28d9; --kpi-to: #8b5cf6; --kpi-glow: rgba(109, 40, 217, .3); }

html[data-theme="dark"] .analytics-kpi-card.tone-requests { --kpi-from: #1d4ed8; --kpi-to: #0ea5e9; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-custody { --kpi-from: #115e59; --kpi-to: #059669; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-overdue { --kpi-from: #9a3412; --kpi-to: #d97706; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-stock { --kpi-from: #5b21b6; --kpi-to: #7c3aed; --kpi-glow: rgba(0, 0, 0, .45); }

.analytics-overview-main {
 display: grid;
 grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
 align-items: stretch;
 gap: 13px;
}

.analytics-overview-rankings {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 align-items: stretch;
 gap: 13px;
}

.analytics-period-comparison { overflow: hidden; }

.analytics-comparison-grid {
 display: grid;
 grid-template-columns: repeat(3, minmax(0, 1fr));
}

.analytics-comparison-metric {
 display: grid;
 align-content: center;
 gap: 5px;
 min-width: 0;
 padding: 12px 12px;
 border-left: 1px solid var(--row-border);
}

.analytics-comparison-metric:first-child { border-left: 0; }

.analytics-comparison-label {
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 750;
 letter-spacing: .045em;
 line-height: 1.3;
 text-transform: uppercase;
}

.analytics-comparison-current {
 display: flex;
 align-items: baseline;
 justify-content: space-between;
 gap: 10px;
 min-width: 0;
}

.analytics-comparison-current strong {
 min-width: 0;
 color: var(--heading);
 font-size: 22px;
 font-weight: 800;
 line-height: 1.1;
 font-variant-numeric: tabular-nums;
 overflow-wrap: anywhere;
}

.analytics-comparison-current strong.is-text {
 color: var(--text-muted);
 font-size: 13px;
 font-weight: 700;
}

.analytics-comparison-delta {
 flex-shrink: 0;
 padding: 3px 7px;
 color: var(--text-muted);
 background: var(--surface);
 border: 1px solid var(--border);
 border-radius: 999px;
 font-size: 10px;
 font-weight: 750;
 line-height: 1.2;
 white-space: nowrap;
}

.analytics-comparison-delta.is-up,
.analytics-comparison-delta.is-down {
 color: var(--interactive);
 background: var(--interactive-soft, var(--info-bg));
 border-color: var(--info-border, var(--border));
}

.analytics-comparison-previous {
 color: var(--text-muted);
 font-size: 10.5px;
 line-height: 1.35;
}

.analytics-card {
 display: flex;
 min-width: 0;
 flex-direction: column;
 background: var(--surface-elevated);
 border: 1px solid var(--border);
 border-radius: 13px;
 box-shadow: var(--shadow-sm);
}

.analytics-card-head {
 display: flex;
 align-items: center;
 gap: 10px;
 padding: 12px 12px;
 border-bottom: 1px solid var(--row-border);
}

.analytics-card-head > div { min-width: 0; flex: 1 1 auto; }

.analytics-card-mark {
 display: grid;
 place-items: center;
 width: 28px;
 height: 28px;
 flex-shrink: 0;
 color: var(--interactive);
 background: var(--blue-50);
 border-radius: 8px;
}

.analytics-card-head h2 {
 margin: 0 0 1px;
 color: var(--heading);
 font-size: 14.5px;
 font-weight: 700;
 line-height: 1.25;
}

.analytics-card-head p {
 margin: 0;
 color: var(--text-muted);
 font-size: 11px;
 line-height: 1.35;
}

.analytics-card-action {
 display: inline-flex;
 flex-shrink: 0;
 align-items: center;
 gap: 3px;
 color: var(--interactive);
 font-size: 11px;
 font-weight: 700;
 text-decoration: none;
 white-space: nowrap;
}

.analytics-card-action:hover { text-decoration: underline; }

.analytics-card-body { flex: 1 1 auto; padding: 13px 12px; }
.analytics-card-body.is-flush { padding: 0; }
.analytics-card-body.is-plot { display: flex; padding: 10px 12px 4px; }
.analytics-card-body > :last-child { margin-bottom: 0; }

.analytics-page .analytics-card:has(> .analytics-card-body > .analytics-blank:only-child):not(:has(> .analytics-card-body > .analytics-blank.is-visualization-empty:only-child)) {
 align-self: start !important;
 height: auto !important;
 min-height: 0 !important;
}
.analytics-card-body:has(> .analytics-blank:only-child):not(:has(> .analytics-blank.is-visualization-empty:only-child)) { flex: 0 0 auto; }

.analytics-card-body:has(> .analytics-blank.is-visualization-empty:only-child) {
 --analytics-empty-visualization-min-height: clamp(8.5rem, 14vw, 10rem);
 display: flex;
 min-block-size: var(--analytics-empty-visualization-min-height);
}

.analytics-card-body > .analytics-blank.is-visualization-empty:only-child {
 width: 100%;
 min-height: 0;
 flex: 1 1 auto;
}

.analytics-overview-main .analytics-card-body:has(> .analytics-blank.is-visualization-empty:only-child),
.analytics-demand-main .analytics-card-body:has(> .analytics-blank.is-visualization-empty:only-child),
.analytics-inventory-main .analytics-card-body:has(> .analytics-blank.is-visualization-empty:only-child),
.analytics-forecast-main .analytics-card-body:has(> .analytics-blank.is-visualization-empty:only-child) {
 --analytics-empty-visualization-min-height: clamp(10.5rem, 18vw, 12.5rem);
}

.analytics-card-foot {
 padding: 8px 12px;
 border-top: 1px solid var(--row-border);
 color: var(--text-soft);
 font-size: 10.5px;
 line-height: 1.4;
}

.analytics-insight-strip {
 display: flex;
 align-items: flex-start;
 gap: 8px;
 margin: 0;
 padding: 9px 12px;
 border-top: 1px solid var(--row-border);
 background: var(--blue-50);
 color: var(--heading);
 font-size: 11.5px;
 font-weight: 600;
 line-height: 1.4;
}

.analytics-insight-strip .ui-icon { flex-shrink: 0; margin-top: 1px; color: var(--interactive); }

.analytics-blank {
 display: flex;
 height: auto;
 min-height: 72px;
 flex-direction: column;
 align-items: center;
 justify-content: center;
 gap: 7px;
 margin: 0;
 padding: 6px 12px;
 color: var(--text-muted);
 font-size: 11.5px;
 line-height: 1.45;
 text-align: center;
}

.analytics-blank-mark {
 display: grid;
 place-items: center;
 width: 32px;
 height: 32px;
 color: var(--text-soft);
 background: var(--surface-subtle);
 border: 1px solid var(--border);
 border-radius: 50%;
}

.analytics-line { display: flex; width: 100%; min-width: 0; flex-direction: column; gap: 7px; }

.analytics-line-plot { position: relative; height: 150px; min-width: 0; }

.analytics-line-grid {
 position: absolute;
 inset: 7% 0 7% 0;
 background-image: repeating-linear-gradient(
 to bottom,
 var(--row-border) 0,
 var(--row-border) 1px,
 transparent 1px,
 transparent calc(50% - 0.5px)
 );
 opacity: .85;
 pointer-events: none;
}

.analytics-line-svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }

.analytics-line-area { fill: url(#analytics-line-gradient); fill-opacity: 1; }
.analytics-line-stroke { fill: none; stroke: #2563eb; stroke-width: 2.25; stroke-linecap: round; stroke-linejoin: round; }

.analytics-line-markers { position: absolute; inset: 0; }

.analytics-line-marker {
 position: absolute;
 display: grid;
 width: 26px;
 height: 26px;
 place-items: center;
 transform: translate(-50%, -50%);
 border-radius: 50%;
 text-decoration: none;
}

.analytics-line-marker:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-line-dot {
 width: 8px;
 height: 8px;
 border: 2px solid #2563eb;
 border-radius: 50%;
 background: var(--surface-elevated);
 transition: transform var(--motion-fast) ease, background-color var(--motion-fast) ease;
}

.analytics-line-marker:hover .analytics-line-dot,
.analytics-line-marker:focus-visible .analytics-line-dot { transform: scale(1.35); background: #2563eb; }

.analytics-line-tip {
 position: absolute;
 bottom: 100%;
 left: 50%;
 transform: translateX(-50%);
 color: var(--heading);
 font-size: 10.5px;
 font-weight: 750;
 font-variant-numeric: tabular-nums;
 pointer-events: none;
}

.analytics-line-axis {
 display: flex;
 justify-content: space-between;
 gap: 4px;
 min-width: 0;
 padding-top: 6px;
 border-top: 1px solid var(--row-border);
}

.analytics-line-axis span {
 min-width: 0;
 flex: 1 1 0;
 color: var(--text-muted);
 font-size: 9.5px;
 line-height: 1.3;
 text-align: center;
 overflow-wrap: anywhere;
}

.analytics-line-axis span:first-child { text-align: left; }
.analytics-line-axis span:last-child { text-align: right; }

html[data-theme="dark"] .analytics-line-stroke { stroke: #60a5fa; }
html[data-theme="dark"] .analytics-line-dot { border-color: #60a5fa; }
html[data-theme="dark"] .analytics-line-marker:hover .analytics-line-dot { background: #60a5fa; }

.analytics-trend-single { display: grid; place-items: center; padding: 16px 15px; }

.analytics-trend-single-figure {
 display: grid;
 justify-items: center;
 gap: 8px;
 padding: 6px 14px;
 border-radius: 10px;
 color: inherit;
 text-decoration: none;
 transition: background-color var(--motion-fast) ease;
}

.analytics-trend-single-figure:hover { background: var(--surface-hover); }
.analytics-trend-single-figure:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-trend-single-value { color: var(--text-muted); font-size: 12px; font-weight: 650; }

.analytics-trend-single-value strong {
 margin-right: 5px;
 color: var(--heading);
 font-size: 34px;
 font-weight: 780;
 line-height: 1;
 font-variant-numeric: tabular-nums;
}

.analytics-trend-single-bar {
 display: block;
 width: 168px;
 max-width: 100%;
 height: 10px;
 overflow: hidden;
 background: var(--surface-muted);
 border-radius: 999px;
}

.analytics-trend-single-bar > span {
 display: block;
 width: 100%;
 height: 100%;
 border-radius: inherit;
 background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
}

.analytics-trend-single-label { color: var(--text-muted); font-size: 11px; text-align: center; }

.analytics-priority { display: flex; height: 100%; flex-direction: column; justify-content: flex-start; margin: 0; padding: 0; list-style: none; }

.analytics-priority-row {
 --priority-tone: var(--neutral);
 --priority-tone-bg: var(--neutral-bg);

 display: flex;
 flex: 1 1 auto;
 flex-direction: column;
 justify-content: center;
 min-height: 46px;
 max-height: 58px;
 border-bottom: 1px solid var(--row-border);
}

.analytics-priority-row:last-child { border-bottom: 0; }

.analytics-priority-row > a,
.analytics-priority-row > div {
 display: grid;
 grid-template-columns: 26px minmax(0, 1fr) auto;
 align-items: center;
 gap: 10px;
 padding: 7px 12px;
 color: inherit;
 text-decoration: none;
}

.analytics-priority-row > a { transition: background-color var(--motion-fast) ease; }
.analytics-priority-row > a:hover { background: var(--surface-hover); }
.analytics-priority-row > a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-priority-icon {
 display: grid;
 place-items: center;
 width: 26px;
 height: 26px;
 color: var(--priority-tone);
 background: var(--priority-tone-bg);
 border-radius: 8px;
}

.analytics-priority-body { display: grid; gap: 1px; min-width: 0; }

.analytics-priority-title {
 color: var(--text-muted);
 font-size: 9.5px;
 font-weight: 800;
 letter-spacing: .06em;
 text-transform: uppercase;
}

.analytics-priority-text {
 color: var(--heading);
 font-size: 12px;
 line-height: 1.4;
 overflow-wrap: anywhere;
}

.analytics-priority-arrow { color: var(--text-soft); }
.analytics-priority-row > a:hover .analytics-priority-arrow { color: var(--interactive); }

.analytics-priority-row.tone-urgent { --priority-tone: var(--danger); --priority-tone-bg: var(--danger-bg); }
.analytics-priority-row.tone-urgent .analytics-priority-text { font-weight: 650; }
.analytics-priority-row.tone-rank { --priority-tone: #a16207; --priority-tone-bg: #fdf4e0; }
.analytics-priority-row.tone-item { --priority-tone: var(--success); --priority-tone-bg: var(--success-bg); }
.analytics-priority-row.tone-warning { --priority-tone: var(--warning); --priority-tone-bg: var(--warning-bg); }
.analytics-priority-row.tone-compliance { --priority-tone: var(--info); --priority-tone-bg: var(--info-bg); }
.analytics-priority-row.tone-neutral { --priority-tone: var(--neutral); --priority-tone-bg: var(--neutral-bg); }

html[data-theme="dark"] .analytics-priority-row.tone-rank { --priority-tone: #f0c25c; --priority-tone-bg: #2e2411; }

.analytics-rank { display: grid; align-content: start; gap: 2px; margin: 0; padding: 0; list-style: none; }

.analytics-rank-row {
 display: grid;
 grid-template-columns: 20px minmax(0, 1fr) auto;
 align-items: center;
 gap: 10px;
 padding: 6px 7px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
 transition: background-color var(--motion-fast) ease;
}

.analytics-rank-row:hover { background: var(--surface-hover); }
.analytics-rank-row:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-rank-no {
 display: grid;
 place-items: center;
 width: 20px;
 height: 20px;
 color: var(--interactive);
 background: var(--blue-50);
 border-radius: 6px;
 font-size: 10px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
}

.analytics-rank-main { display: grid; gap: 5px; min-width: 0; }

.analytics-rank-name {
 color: var(--heading);
 font-size: 12px;
 font-weight: 650;
 line-height: 1.25;
 overflow-wrap: anywhere;
}

.analytics-rank-name small {
 display: block;
 margin-top: 1px;
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 500;
}

.analytics-rank-track {
 height: 6px;
 overflow: hidden;
 background: var(--surface-muted);
 border-radius: 999px;
}

.analytics-rank-fill {
 display: block;
 height: 100%;
 border-radius: inherit;
 background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
}

.analytics-rank-value {
 color: var(--heading);
 font-size: 13px;
 font-weight: 750;
 font-variant-numeric: tabular-nums;
 text-align: right;
 white-space: nowrap;
}

.analytics-rank-value small { display: block; color: var(--text-muted); font-size: 9.5px; font-weight: 500; }

.analytics-rank-flat .analytics-rank-row { padding-top: 8px; padding-bottom: 8px; }
.analytics-rank-flat .analytics-rank-main { gap: 0; }

.analytics-rank-split .analytics-rank-main {
 grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr);
 align-items: center;
 gap: 12px;
}

.analytics-rank-split .analytics-rank-row { padding-top: 9px; padding-bottom: 9px; }
.analytics-rank-split .analytics-rank-track { height: 16px; }

@media (max-width: 560px) {
 .analytics-rank-split .analytics-rank-main {
 grid-template-columns: minmax(0, 1fr);
 align-items: stretch;
 gap: 6px;
 }

 .analytics-rank-split .analytics-rank-track { height: 10px; }
}

.analytics-snapshot { flex-direction: row; align-items: stretch; flex-wrap: wrap; }

.analytics-snapshot-head {
 display: flex;
 flex: 0 0 auto;
 flex-direction: column;
 justify-content: center;
 gap: 2px;
 width: 210px;
 padding: 12px 12px;
 border-right: 1px solid var(--row-border);
}

.analytics-snapshot-head h2 { margin: 0; color: var(--heading); font-size: 14px; font-weight: 700; line-height: 1.25; }
.analytics-snapshot-head p { margin: 0; color: var(--text-muted); font-size: 10.5px; }

.analytics-snapshot-strip {
 display: grid;
 flex: 1 1 420px;
 grid-template-columns: repeat(5, minmax(0, 1fr));
 min-width: 0;
}

.analytics-snapshot-tile {
 --snap-tone: var(--neutral);
 --snap-tone-bg: var(--neutral-bg);

 display: flex;
 align-items: center;
 gap: 10px;
 min-width: 0;
 padding: 12px 12px;
 border-left: 1px solid var(--row-border);
}

.analytics-snapshot-tile:first-child { border-left: 0; }

.analytics-snapshot-icon {
 display: grid;
 place-items: center;
 width: 28px;
 height: 28px;
 flex-shrink: 0;
 color: var(--snap-tone);
 background: var(--snap-tone-bg);
 border-radius: 8px;
}

.analytics-snapshot-body { display: grid; gap: 0; min-width: 0; }

.analytics-snapshot-label {
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 700;
 line-height: 1.3;
 overflow-wrap: anywhere;
}

.analytics-snapshot-value {
 color: var(--heading);
 font-size: 18px;
 font-weight: 780;
 line-height: 1.15;
 font-variant-numeric: tabular-nums;
}

.analytics-snapshot-tile.tone-info { --snap-tone: var(--info); --snap-tone-bg: var(--info-bg); }
.analytics-snapshot-tile.tone-good { --snap-tone: var(--success); --snap-tone-bg: var(--success-bg); }
.analytics-snapshot-tile.tone-warn { --snap-tone: var(--warning); --snap-tone-bg: var(--warning-bg); }
.analytics-snapshot-tile.tone-risk { --snap-tone: var(--danger); --snap-tone-bg: var(--danger-bg); }

@media (max-width: 1180px) {
 .analytics-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
 .analytics-page .analytics-tab { flex: 0 0 auto; }
 .analytics-page .analytics-tabs { overflow-x: auto; }
}

@media (max-width: 900px) {
 .analytics-overview-main { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 980px) {
 .analytics-comparison-grid { grid-template-columns: minmax(0, 1fr); }
 .analytics-comparison-metric { border-left: 0; border-top: 1px solid var(--row-border); }
 .analytics-comparison-metric:first-child { border-top: 0; }
 .analytics-snapshot-head { width: 100%; border-right: 0; border-bottom: 1px solid var(--row-border); }
 .analytics-snapshot-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
 .analytics-snapshot-tile:nth-child(4) { border-left: 0; }
}

@media (min-width: 821px) {
 .analytics-page .analytics-filters { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}

@media (max-width: 820px) {
 .analytics-page .analytics-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
 .analytics-overview-rankings { grid-template-columns: minmax(0, 1fr); }
 .analytics-snapshot-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
 .analytics-snapshot-tile:nth-child(odd) { border-left: 0; }
}

@media (max-width: 620px) {
 .analytics-kpis { grid-template-columns: minmax(0, 1fr); }
 .analytics-kpi-card { min-height: 0; }
 .analytics-page .analytics-filters { grid-template-columns: minmax(0, 1fr); }
 .analytics-snapshot-strip { grid-template-columns: minmax(0, 1fr); }
 .analytics-snapshot-tile { border-left: 0; border-top: 1px solid var(--row-border); }
 .analytics-snapshot-tile:first-child { border-top: 0; }
}

@media (prefers-reduced-motion: reduce) {
 .analytics-kpi-card,
 .analytics-kpi-card-arrow,
 .analytics-rank-row,
 .analytics-priority-row > a { transition: none; }
 .analytics-kpi-card:hover,
 .analytics-kpi-card:focus-visible { transform: none; }
}

.analytics-kpi-card.tone-quantity { --kpi-from: #6d28d9; --kpi-to: #8b5cf6; --kpi-glow: rgba(109, 40, 217, .3); }
.analytics-kpi-card.tone-released { --kpi-from: #0f766e; --kpi-to: #10b981; --kpi-glow: rgba(15, 118, 110, .28); }
.analytics-kpi-card.tone-units { --kpi-from: #b45309; --kpi-to: #f59e0b; --kpi-glow: rgba(180, 83, 9, .28); }

html[data-theme="dark"] .analytics-kpi-card.tone-quantity { --kpi-from: #5b21b6; --kpi-to: #7c3aed; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-released { --kpi-from: #115e59; --kpi-to: #059669; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-units { --kpi-from: #92400e; --kpi-to: #d97706; --kpi-glow: rgba(0, 0, 0, .45); }

.analytics-kpi-card.is-static { cursor: default; }
.analytics-kpi-card.is-static:hover { transform: none; box-shadow: 0 1px 2px var(--kpi-glow); }

.analytics-demand-main {
 display: grid;
 grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-demand-pair {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-donut-body {
 display: grid;
 grid-template-columns: 152px minmax(0, 1fr);
 align-items: center;
 gap: 16px;
}

.analytics-donut {
 position: relative;
 display: grid;
 place-items: center;
 width: 152px;
 height: 152px;
}

.analytics-donut svg {
 width: 100%;
 height: 100%;

 transform: rotate(-90deg);
}

.analytics-donut circle {
 fill: none;
 stroke-width: 5.2;
}

.analytics-donut-rail { stroke: var(--surface-subtle); }

.analytics-donut-arc.is-academic { stroke: #1769e0; }
.analytics-donut-arc.is-administration { stroke: #0e7c66; }
.analytics-donut-arc.is-research { stroke: #7a4bc4; }
.analytics-donut-arc.is-unspecified { stroke: var(--text-soft); }
.analytics-donut-arc.is-external { stroke: #b45309; }

.analytics-donut-centre {
 position: absolute;
 display: grid;
 justify-items: center;
 gap: 1px;
 text-align: center;
}

.analytics-donut-centre strong {
 color: var(--heading);
 font-size: 25px;
 font-weight: 800;
 line-height: 1;
}

.analytics-donut-centre small {
 max-width: 84px;
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 700;
 line-height: 1.25;
}

.analytics-donut-legend { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; }

.analytics-donut-legend a,
.analytics-donut-legend div {
 display: grid;
 grid-template-columns: 10px minmax(0, 1fr) auto;
 align-items: center;
 gap: 9px;
 padding: 6px 8px;
 border-radius: 7px;
 color: inherit;
 text-decoration: none;
}

.analytics-donut-legend a:hover { background: var(--surface-hover); }
.analytics-donut-legend a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-donut-key { width: 10px; height: 10px; border-radius: 50%; background: var(--text-soft); }
.analytics-donut-key.is-academic { background: #1769e0; }
.analytics-donut-key.is-administration { background: #0e7c66; }
.analytics-donut-key.is-research { background: #7a4bc4; }
.analytics-donut-key.is-external { background: #b45309; }

.analytics-donut-name {
 min-width: 0;
 color: var(--text-primary);
 font-size: 11.5px;
 font-weight: 600;
 line-height: 1.35;
}

.analytics-donut-figure {
 color: var(--heading);
 font-size: 12px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-donut-figure small { color: var(--text-muted); font-weight: 700; }

.analytics-rank-name small.analytics-rank-tag {
 display: inline-block;
 width: max-content;
 max-width: 100%;
 margin-top: 3px;
 padding: 1px 7px;
 border-radius: 999px;
 background: var(--surface-subtle);
 color: var(--text-muted);
 font-size: 9.5px;
 font-weight: 800;
 letter-spacing: .03em;
}

.analytics-rank-name small.analytics-rank-tag.is-academic { color: #1769e0; background: rgba(23, 105, 224, .1); }
.analytics-rank-name small.analytics-rank-tag.is-administration { color: #0e7c66; background: rgba(14, 124, 102, .1); }
.analytics-rank-name small.analytics-rank-tag.is-research { color: #7a4bc4; background: rgba(122, 75, 196, .1); }

.analytics-rank-fill.is-released { background: #0e7c66; }

.analytics-quiet { display: grid; gap: 1px; margin: 0; padding: 0; list-style: none; }

.analytics-quiet a {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 8px 9px;
 border-radius: 7px;
 color: inherit;
 text-decoration: none;
}

.analytics-quiet a:hover { background: var(--surface-hover); }
.analytics-quiet a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-quiet-name {
 min-width: 0;
 color: var(--interactive);
 font-size: 11.5px;
 font-weight: 600;
 line-height: 1.35;
}

.analytics-quiet-state {
 flex: 0 0 auto;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 700;
 white-space: nowrap;
}

.analytics-peak { display: grid; gap: 7px; margin: 0; padding: 0; list-style: none; }

.analytics-peak li {
 display: grid;
 grid-template-columns: 108px minmax(0, 1fr) 82px;
 align-items: center;
 gap: 11px;
}

.analytics-peak-label { color: var(--text-primary); font-size: 11.5px; font-weight: 600; }

.analytics-peak-track {
 height: 9px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-subtle);
}

.analytics-peak-fill { display: block; height: 100%; min-width: 2px; border-radius: 999px; background: #1769e0; }
.analytics-peak li.is-peak .analytics-peak-fill { background: #0e7c66; }

.analytics-peak-value {
 display: flex;
 align-items: center;
 justify-content: flex-end;
 gap: 6px;
 color: var(--heading);
 font-size: 12px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
}

.analytics-peak-tag {
 padding: 1px 6px;
 border-radius: 999px;
 color: #0e7c66;
 background: rgba(14, 124, 102, .12);
 font-size: 9px;
 font-weight: 800;
 letter-spacing: .04em;
 text-transform: uppercase;
}

.analytics-blank strong { display: block; margin-bottom: 2px; color: var(--heading); }

@media (max-width: 1180px) {
 .analytics-demand-main { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 900px) {
 .analytics-demand-pair { grid-template-columns: minmax(0, 1fr); }
 .analytics-peak li { grid-template-columns: 92px minmax(0, 1fr) 74px; }
}

@media (max-width: 560px) {

 .analytics-donut-body { grid-template-columns: minmax(0, 1fr); justify-items: center; }
 .analytics-donut-legend { width: 100%; }
}

.analytics-kpi-card.tone-available { --kpi-from: #047857; --kpi-to: #10b981; --kpi-glow: rgba(4, 120, 87, .28); }
.analytics-kpi-card.tone-attention { --kpi-from: #b91c1c; --kpi-to: #f97316; --kpi-glow: rgba(185, 28, 28, .28); }

html[data-theme="dark"] .analytics-kpi-card.tone-available { --kpi-from: #065f46; --kpi-to: #059669; --kpi-glow: rgba(0, 0, 0, .45); }
html[data-theme="dark"] .analytics-kpi-card.tone-attention { --kpi-from: #991b1b; --kpi-to: #ea580c; --kpi-glow: rgba(0, 0, 0, .45); }

.analytics-inventory-main {
 display: grid;
 grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-inventory-pair {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-dist-seg.is-available, .analytics-dist-key.is-available { background: #0e7c66; }
.analytics-dist-seg.is-reserved, .analytics-dist-key.is-reserved { background: #6557d9; }
.analytics-dist-seg.is-custody, .analytics-dist-key.is-custody { background: #1769e0; }
.analytics-dist-seg.is-laundry, .analytics-dist-key.is-laundry { background: #d08a16; }
.analytics-dist-seg.is-incident, .analytics-dist-key.is-incident { background: #c4493d; }

.analytics-avail { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; }

.analytics-avail a {
 display: grid;
 gap: 6px;
 padding: 8px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
}

.analytics-avail a:hover { background: var(--surface-hover); }
.analytics-avail a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-avail-name {
 min-width: 0;
 color: var(--text-primary);
 font-size: 11.5px;
 font-weight: 600;
 line-height: 1.3;
 overflow-wrap: anywhere;
}

.analytics-avail-meter {
 display: grid;
 grid-template-columns: minmax(0, 1fr) auto;
 align-items: center;
 gap: 12px;
}

.analytics-avail-figure {
 display: flex;
 align-items: baseline;
 gap: 8px;
 color: var(--text-muted);
 font-size: 10.5px;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-avail-figure strong { color: var(--heading); font-size: 12px; font-weight: 800; }
.analytics-avail-figure strong.is-low { color: #c4493d; }

.analytics-avail-tag {
 flex: 0 0 auto;
 padding: 1px 6px;
 border-radius: 999px;
 color: #0e7c66;
 background: rgba(14, 124, 102, .12);
 font-size: 9px;
 font-weight: 800;
 letter-spacing: .03em;
 text-transform: uppercase;
}

.analytics-avail-tag.is-low { color: #b3261e; background: rgba(196, 73, 61, .14); }

.analytics-avail-bar {
 display: block;
 height: 8px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-muted);
}

.analytics-avail-fill {
 display: block;
 height: 100%;
 border-radius: inherit;
 background: #0e7c66;
}

.analytics-avail-fill.is-low { background: #c4493d; }

.analytics-dist-total {
 display: flex;
 align-items: baseline;
 gap: 8px;
 margin: 0 0 10px;
}

.analytics-dist-total strong { color: var(--heading); font-size: 24px; font-weight: 800; line-height: 1; }
.analytics-dist-total span { color: var(--text-muted); font-size: 11px; line-height: 1.4; }

.analytics-dist-bar {
 display: flex;
 height: 11px;
 margin-bottom: 12px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-subtle);
}

.analytics-dist-seg { display: block; height: 100%; min-width: 2px; }

.analytics-dist-legend { display: grid; gap: 1px; margin: 0; padding: 0; list-style: none; }

.analytics-dist-legend li {
 display: grid;
 grid-template-columns: 10px minmax(0, 1fr) auto;
 align-items: center;
 gap: 9px;
 padding: 4px 2px;
}

.analytics-dist-key { width: 10px; height: 10px; border-radius: 50%; background: var(--text-soft); }
.analytics-dist-name { min-width: 0; color: var(--text-primary); font-size: 11.5px; font-weight: 600; }

.analytics-dist-figure {
 color: var(--heading);
 font-size: 12px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-dist-figure small { color: var(--text-muted); font-weight: 700; }

.analytics-laterate-body {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 align-items: start;
 gap: 12px 16px;
}

.analytics-laterate-panel { min-width: 0; }

.analytics-laterate-title { margin: 0 0 8px; color: var(--text-secondary); font-size: 11px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.analytics-laterate-title a { color: inherit; text-decoration: none; }
.analytics-laterate-title a:hover, .analytics-laterate-title a:focus-visible { color: var(--heading); text-decoration: underline; }

.analytics-laterate-rows { display: grid; gap: 4px; margin: 0; padding: 0; list-style: none; }

.analytics-laterate-rows li > a {
 display: grid;
 gap: 4px;
 padding: 6px 6px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
}

.analytics-laterate-rows li > a:hover,
.analytics-laterate-rows li > a:focus-visible { background: var(--surface-subtle); }
.analytics-laterate-rows li > a:focus-visible { outline: 2px solid var(--interactive); outline-offset: 1px; }

.analytics-laterate-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; min-width: 0; }
.analytics-laterate-name { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; min-width: 0; color: var(--text-primary); font-size: 11.5px; font-weight: 600; overflow-wrap: anywhere; }
.analytics-laterate-rate { color: var(--heading); font-size: 12.5px; font-weight: 800; font-variant-numeric: tabular-nums; white-space: nowrap; }

.analytics-laterate-track {
 display: block;
 height: 8px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-subtle);
}

.analytics-laterate-fill { display: block; height: 100%; min-width: 0; border-radius: 999px; background: #c2410c; }

.analytics-laterate-counts { color: var(--text-muted); font-size: 10.5px; font-weight: 650; font-variant-numeric: tabular-nums; }

.analytics-laterate-none { margin: 0; color: var(--text-muted); font-size: 12px; line-height: 1.5; }

.analytics-laterate-more {
 display: inline-flex;
 align-items: center;
 gap: 4px;
 margin-top: 8px;
 color: var(--interactive);
 font-size: 11px;
 font-weight: 700;
 text-decoration: none;
}

.analytics-laterate-more:hover, .analytics-laterate-more:focus-visible { text-decoration: underline; }

@media (max-width: 760px) {
 .analytics-laterate-body { grid-template-columns: minmax(0, 1fr); }
}

.analytics-aging-bands { display: grid; gap: 7px; margin: 0; padding: 0; list-style: none; }

.analytics-aging-bands li > a {
 display: grid;
 grid-template-columns: 96px minmax(0, 1fr) 84px;
 align-items: center;
 gap: 11px;
 padding: 4px 4px;
 border-radius: 7px;
 color: inherit;
 text-decoration: none;
}

.analytics-aging-bands li > a:hover,
.analytics-aging-bands li > a:focus-visible { background: var(--surface-subtle); }
.analytics-aging-bands li > a:focus-visible { outline: 2px solid var(--interactive); outline-offset: 1px; }

.analytics-aging-label { color: var(--text-primary); font-size: 11.5px; font-weight: 600; white-space: nowrap; }

.analytics-aging-track {
 height: 9px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-subtle);
}

.analytics-aging-fill { display: block; height: 100%; min-width: 0; border-radius: 999px; background: #c2410c; }

.analytics-aging-value {
 display: flex;
 align-items: baseline;
 justify-content: flex-end;
 gap: 6px;
 color: var(--heading);
 font-size: 12px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-aging-value small { color: var(--text-muted); font-weight: 700; }

.analytics-aging-bands li.is-none .analytics-aging-label,
.analytics-aging-bands li.is-none .analytics-aging-value { color: var(--text-muted); font-weight: 600; }

.analytics-aging-note { margin: 10px 0 0; color: var(--text-muted); font-size: 11.5px; line-height: 1.5; }

.analytics-outcomes-body {
 display: grid;
 grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
 align-items: start;
 gap: 10px 16px;
}

.analytics-outcomes-composition { min-width: 0; }

.analytics-outcome-bar {
 display: flex;
 height: 14px;
 margin-top: 2px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-subtle);
}

.analytics-outcome-seg {
 display: block;
 height: 100%;
 min-width: 3px;
 text-decoration: none;
 transition: filter var(--motion-fast, .12s) ease;
}

.analytics-outcome-seg:hover { filter: brightness(1.08); }
.analytics-outcome-seg:focus-visible { outline: 2px solid var(--interactive); outline-offset: 2px; }

.analytics-outcome-legend { display: grid; gap: 1px; margin: 0; padding: 0; list-style: none; }

.analytics-outcome-legend li > a,
.analytics-outcome-legend li > div {
 display: grid;
 grid-template-columns: 10px minmax(0, 1fr) auto;
 align-items: center;
 gap: 9px;
 padding: 4px 4px;
 border-radius: 7px;
 color: inherit;
 text-decoration: none;
}

.analytics-outcome-legend li > a:hover,
.analytics-outcome-legend li > a:focus-visible { background: var(--surface-subtle); }
.analytics-outcome-legend li > a:focus-visible { outline: 2px solid var(--interactive); outline-offset: 1px; }

.analytics-outcome-legend li.is-none > div { color: var(--text-muted); }
.analytics-outcome-legend li.is-none .analytics-outcome-key { opacity: .35; }
.analytics-outcome-legend li.is-none .analytics-outcome-name,
.analytics-outcome-legend li.is-none .analytics-outcome-figure { color: var(--text-muted); font-weight: 600; }

.analytics-outcome-key { width: 10px; height: 10px; border-radius: 50%; background: var(--text-soft); }
.analytics-outcome-name { min-width: 0; color: var(--text-primary); font-size: 11.5px; font-weight: 600; }

.analytics-outcome-figure {
 color: var(--heading);
 font-size: 12px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-outcome-figure small { color: var(--text-muted); font-weight: 700; }

.analytics-outcome-seg.is-approved, .analytics-outcome-key.is-approved { background: #0e7c66; }
.analytics-outcome-seg.is-in_review, .analytics-outcome-key.is-in_review { background: #1769e0; }
.analytics-outcome-seg.is-revision, .analytics-outcome-key.is-revision { background: #d08a16; }
.analytics-outcome-seg.is-rejected, .analytics-outcome-key.is-rejected { background: #c4493d; }
.analytics-outcome-seg.is-cancelled, .analytics-outcome-key.is-cancelled { background: #6b7280; }
.analytics-outcome-seg.is-expired, .analytics-outcome-key.is-expired { background: #7a4bc4; }

@media (max-width: 760px) {
 .analytics-outcomes-body { grid-template-columns: minmax(0, 1fr); }
}

.analytics-dist-aside {
 display: grid;
 gap: 7px;
 margin: 12px 0 0;
 padding-top: 10px;
 border-top: 1px solid var(--border);
}

.analytics-dist-aside div { display: grid; gap: 1px; }
.analytics-dist-aside dt { color: var(--text-muted); font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.analytics-dist-aside dd { margin: 0; color: var(--text-secondary); font-size: 11px; line-height: 1.5; }

.analytics-count-pill {
 flex: 0 0 auto;
 min-width: 22px;
 padding: 2px 8px;
 border-radius: 999px;
 color: #b3461c;
 background: rgba(208, 138, 22, .14);
 font-size: 11.5px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 text-align: center;
}

.analytics-watch { display: grid; gap: 1px; margin: 0; padding: 0; list-style: none; }

.analytics-watch a {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 8px 9px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
}

.analytics-watch a:hover { background: var(--surface-hover); }
.analytics-watch a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-watch-main { min-width: 0; display: grid; gap: 1px; }
.analytics-watch-name { color: var(--interactive); font-size: 11.5px; font-weight: 700; }
.analytics-watch-sub { color: var(--text-muted); font-size: 10.5px; }

.analytics-watch-figure {
 display: flex;
 flex: 0 0 auto;
 align-items: center;
 gap: 8px;
}

.analytics-watch-figure strong { color: #c4493d; font-size: 13px; font-weight: 800; font-variant-numeric: tabular-nums; }

.analytics-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 9px; }

.analytics-tile {
 display: grid;
 gap: 2px;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface-subtle);
}

.analytics-tile-icon {
 display: inline-grid;
 width: 26px;
 height: 26px;
 place-items: center;
 margin-bottom: 3px;
 border-radius: 8px;
 color: var(--text-muted);
 background: var(--surface);
}

.analytics-tile-label { color: var(--text-muted); font-size: 9.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.analytics-tile-value { color: var(--heading); font-size: 20px; font-weight: 800; line-height: 1.1; }
.analytics-tile-note { color: var(--text-muted); font-size: 10px; line-height: 1.4; }

.analytics-tile.is-flagged.is-laundry { border-color: rgba(208, 138, 22, .35); background: rgba(208, 138, 22, .08); }
.analytics-tile.is-flagged.is-laundry .analytics-tile-icon { color: #b3461c; }
.analytics-tile.is-flagged.is-maintenance,
.analytics-tile.is-flagged.is-incident { border-color: rgba(196, 73, 61, .35); background: rgba(196, 73, 61, .08); }
.analytics-tile.is-flagged.is-maintenance .analytics-tile-icon,
.analytics-tile.is-flagged.is-incident .analytics-tile-icon { color: #c4493d; }

.analytics-coverage { display: grid; gap: 1px; margin: 0; padding: 0; list-style: none; }

.analytics-coverage a {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 8px 9px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
}

.analytics-coverage a:hover { background: var(--surface-hover); }
.analytics-coverage a:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-coverage-main { min-width: 0; display: grid; gap: 1px; }
.analytics-coverage-name { color: var(--interactive); font-size: 11.5px; font-weight: 700; }
.analytics-coverage-sub { color: var(--text-muted); font-size: 10.5px; font-variant-numeric: tabular-nums; }

.analytics-coverage-figure {
 display: flex;
 flex: 0 0 auto;
 align-items: center;
 gap: 8px;
}

.analytics-coverage-figure strong { color: var(--heading); font-size: 14px; font-weight: 800; font-variant-numeric: tabular-nums; }
.analytics-coverage-figure strong small { color: var(--text-muted); font-size: 10px; font-weight: 700; }

.analytics-blank.is-positive { border-color: rgba(14, 124, 102, .3); background: rgba(14, 124, 102, .06); }
.analytics-blank.is-positive .analytics-blank-mark { color: #0e7c66; }

@media (max-width: 1180px) {
 .analytics-inventory-main { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 900px) {
 .analytics-inventory-pair { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 560px) {
 .analytics-tiles { grid-template-columns: minmax(0, 1fr); }

 .analytics-avail-meter { grid-template-columns: minmax(0, 1fr); gap: 6px; }
 .analytics-avail-meter .analytics-avail-figure { order: -1; }
 .analytics-avail-figure { flex-wrap: wrap; white-space: normal; }
}

.analytics-kpi-card.tone-ontime { --kpi-from: #047857; --kpi-to: #34d399; --kpi-glow: rgba(4, 120, 87, .28); }
html[data-theme="dark"] .analytics-kpi-card.tone-ontime { --kpi-from: #065f46; --kpi-to: #10b981; --kpi-glow: rgba(0, 0, 0, .45); }

.analytics-kpi-card.is-static { cursor: default; }
.analytics-kpi-card.is-static:hover { transform: none; box-shadow: 0 4px 14px var(--kpi-glow); }

.analytics-line--dual { gap: 5px; }

.analytics-line-legend { display: flex; gap: 12px; padding: 0 2px; }

.analytics-line-key {
 display: inline-flex;
 align-items: center;
 gap: 6px;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 700;
}

.analytics-line-key::before {
 content: '';
 width: 14px;
 height: 3px;
 border-radius: 999px;
 background: currentColor;
}

.analytics-line-key.is-ontime { color: var(--success); }
.analytics-line-key.is-late { color: var(--warning); }

.analytics-line-stroke.is-ontime { stroke: #047857; }
.analytics-line-stroke.is-late { stroke: #c2410c; }

.analytics-line-marker.is-static { cursor: default; }
.analytics-line-marker.is-ontime .analytics-line-dot { border-color: #047857; }
.analytics-line-marker.is-late .analytics-line-dot { border-color: #c2410c; }

html[data-theme="dark"] .analytics-line-stroke.is-ontime { stroke: #34d399; }
html[data-theme="dark"] .analytics-line-stroke.is-late { stroke: #f59e0b; }
html[data-theme="dark"] .analytics-line-marker.is-ontime .analytics-line-dot { border-color: #34d399; }
html[data-theme="dark"] .analytics-line-marker.is-late .analytics-line-dot { border-color: #f59e0b; }

.analytics-return-split { display: flex; gap: 12px; font-size: 11.5px; font-weight: 700; }
.analytics-return-split .is-ontime { color: var(--success); }
.analytics-return-split .is-late { color: var(--warning); }

.analytics-return-outcome .analytics-donut-body {
 display: grid;
 grid-template-columns: 128px minmax(160px, 1fr);
 align-items: center;
 column-gap: 32px;
 row-gap: 16px;
}

.analytics-donut { position: relative; width: 128px; height: 128px; flex: 0 0 auto; }
.analytics-donut svg { width: 100%; height: 100%; transform: rotate(-90deg); }

.analytics-donut-track { fill: none; stroke: var(--surface-muted); stroke-width: 13; }
.analytics-donut-arc { fill: none; stroke-width: 13; stroke-linecap: butt; }
.analytics-donut-arc.is-ontime { stroke: #047857; }
.analytics-donut-arc.is-late { stroke: #c2410c; }

html[data-theme="dark"] .analytics-donut-arc.is-ontime { stroke: #34d399; }
html[data-theme="dark"] .analytics-donut-arc.is-late { stroke: #f59e0b; }

.analytics-donut-centre {
 position: absolute;
 inset: 0;
 display: grid;
 place-content: center;
 gap: 1px;
 text-align: center;
}

.analytics-donut-centre strong { color: var(--heading); font-size: 26px; font-weight: 780; line-height: 1; font-variant-numeric: tabular-nums; }
.analytics-donut-centre small { color: var(--text-muted); font-size: 9.5px; font-weight: 700; }

.analytics-return-outcome .analytics-donut-legend {
 display: grid;
 gap: 12px;
 width: 100%;
 min-width: 0;
 margin: 0;
}

.analytics-return-outcome .analytics-donut-legend > div {
 display: grid;
 grid-template-columns: minmax(72px, 1fr) auto;
 align-items: baseline;
 column-gap: 16px;
 row-gap: 0;
 padding: 7px 0 7px 12px;
 border-left: 3px solid currentColor;
}

.analytics-return-outcome .analytics-donut-legend > div.is-ontime { color: #047857; }
.analytics-return-outcome .analytics-donut-legend > div.is-late { color: #c2410c; }

.analytics-return-outcome .analytics-donut-legend dt {
 min-width: 0;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 700;
 line-height: 1.3;
 white-space: nowrap;
}

.analytics-return-outcome .analytics-donut-legend dd {
 display: grid;
 grid-template-columns: minmax(24px, auto) minmax(40px, auto);
 align-items: baseline;
 justify-content: end;
 gap: 8px;
 margin: 0;
 color: var(--heading);
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-return-outcome .analytics-outcome-count {
 color: var(--heading);
 font-size: 15px;
 font-weight: 750;
 line-height: 1.2;
 text-align: right;
}

.analytics-return-outcome .analytics-outcome-rate {
 color: var(--text-muted);
 font-size: 11.5px;
 font-weight: 650;
 line-height: 1.2;
 text-align: right;
}

html[data-theme="dark"] .analytics-return-outcome .analytics-donut-legend > div.is-ontime { color: #34d399; }
html[data-theme="dark"] .analytics-return-outcome .analytics-donut-legend > div.is-late { color: #f59e0b; }

.analytics-lifecycle-strip {
 display: flex;
 align-items: stretch;
 gap: 2px;
 padding: 13px 12px 12px;
}

.analytics-lifecycle-stage {
 display: grid;
 align-content: start;
 gap: 2px;
 min-width: 0;
 flex: 1 1 0;
 padding: 10px 12px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface);
}

.analytics-lifecycle-label { color: var(--text-muted); font-size: 10px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; overflow-wrap: anywhere; }
.analytics-lifecycle-value { color: var(--heading); font-size: 22px; font-weight: 780; line-height: 1.1; font-variant-numeric: tabular-nums; }
.analytics-lifecycle-note { color: var(--text-muted); font-size: 10px; line-height: 1.35; overflow-wrap: anywhere; }

.analytics-lifecycle-bar {
 display: block;
 height: 4px;
 min-width: 0;
 margin-top: 7px;
 border-radius: 999px;
 background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
}

.analytics-lifecycle-link { display: grid; place-items: center; flex: 0 0 auto; color: var(--text-soft); }

.analytics-followup { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; }

.analytics-followup-row {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 8px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
 transition: background-color var(--motion-fast) ease;
}

.analytics-followup-row:hover { background: var(--surface-hover); }
.analytics-followup-row:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-followup-main { display: grid; gap: 1px; min-width: 0; }
.analytics-followup-name { color: var(--heading); font-size: 12.5px; font-weight: 650; overflow-wrap: anywhere; }
.analytics-followup-ref { color: var(--text-muted); font-size: 10.5px; overflow-wrap: anywhere; }

.analytics-followup-due { display: grid; gap: 1px; flex-shrink: 0; text-align: right; }
.analytics-followup-due span { color: var(--text-muted); font-size: 10.5px; }
.analytics-followup-due strong { color: var(--danger); font-size: 12px; font-weight: 750; white-space: nowrap; }

.analytics-rank-row.is-static { cursor: default; }
.analytics-rank-row.is-static:hover { background: transparent; }
.analytics-rank-fill.is-good { background: linear-gradient(90deg, #047857 0%, #34d399 100%); }
.analytics-rank-fill.is-issue { background: linear-gradient(90deg, #c2410c 0%, #f59e0b 100%); }

.analytics-blank-mark.is-good { color: var(--success); background: var(--success-bg); border-color: var(--success-border); }

.analytics-ministats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }

.analytics-ministat {
 display: grid;
 align-content: center;
 gap: 3px;
 min-width: 0;
 padding: 11px 13px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface);
}

.analytics-ministat span { color: var(--text-muted); font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; overflow-wrap: anywhere; }
.analytics-ministat strong { color: var(--heading); font-size: 19px; font-weight: 780; line-height: 1.15; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }

.analytics-ministat.is-unmeasured strong { color: var(--text-muted); font-size: 12.5px; font-weight: 650; }

.analytics-delta {
 display: block;
 min-width: 0;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 650;
 line-height: 1.35;
 font-variant-numeric: tabular-nums;
 overflow-wrap: anywhere;
}

.analytics-kpi-card .analytics-delta { font-size: 10.5px; white-space: normal; }

.analytics-ministat .analytics-delta { margin-top: 1px; font-size: 10px; }

.analytics-issues { display: grid; align-content: start; gap: 0; margin: 0; padding: 0; list-style: none; }

.analytics-issues li {
 --issue-tone: var(--neutral);

 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 9px 2px;
 border-bottom: 1px solid var(--row-border);
}

.analytics-issues li:last-child { border-bottom: 0; }
.analytics-issues li.tone-risk { --issue-tone: var(--danger); }
.analytics-issues li.tone-warn { --issue-tone: var(--warning); }
.analytics-issues li.tone-good { --issue-tone: var(--success); }

.analytics-issues-label { display: grid; gap: 1px; min-width: 0; color: var(--heading); font-size: 12.5px; overflow-wrap: anywhere; }
.analytics-issues-label small { color: var(--text-muted); font-size: 10.5px; }
.analytics-issues li strong { flex-shrink: 0; color: var(--issue-tone); font-size: 17px; font-weight: 780; font-variant-numeric: tabular-nums; }

@media (max-width: 1180px) {
 .analytics-lifecycle-strip { flex-wrap: wrap; }
 .analytics-lifecycle-stage { flex: 1 1 40%; }
 .analytics-lifecycle-link { display: none; }
}

@media (max-width: 620px) {
 .analytics-lifecycle-stage { flex: 1 1 100%; }
 .analytics-ministats { grid-template-columns: minmax(0, 1fr); }
 .analytics-return-outcome .analytics-donut-body {
 grid-template-columns: minmax(0, 1fr);
 justify-items: center;
 }

 .analytics-return-outcome .analytics-donut-legend {
 width: min(100%, 280px);
 }
}

.analytics-forecast-main {
 display: grid;
 grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-forecast-pair {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 align-items: stretch;
 gap: 13px;
 margin-bottom: 13px;
}

.analytics-forecast-equipment { margin-bottom: 13px; }

.analytics-kpi-card-value.is-text { font-size: 19px; line-height: 1.2; }

.analytics-outlook-legend {
 display: flex;
 flex-wrap: wrap;
 gap: 4px 12px;
 margin: 0 0 10px;
 padding: 0;
 list-style: none;
}

.analytics-outlook-legend li {
 display: inline-flex;
 align-items: center;
 gap: 7px;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 700;
}

.analytics-outlook-key { width: 16px; height: 0; border-top: 2px solid var(--text-soft); }
.analytics-outlook-key.is-observed { border-top-color: #2563eb; }
.analytics-outlook-key.is-forecast { border-top-style: dashed; border-top-color: #7a4bc4; }

.analytics-outlook { display: grid; gap: 6px; }
.analytics-outlook-plot { position: relative; height: 150px; }

.analytics-outlook-plot svg {
 position: absolute;
 inset: 0;
 width: 100%;
 height: 100%;
 overflow: visible;
}

.analytics-outlook-solid { fill: none; stroke: #2563eb; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.analytics-outlook-dashed { fill: none; stroke: #7a4bc4; stroke-width: 2; stroke-dasharray: 4 4; stroke-linecap: round; }

.analytics-outlook-markers { position: absolute; inset: 0; }

.analytics-outlook-marker {
 position: absolute;
 display: grid;
 justify-items: center;
 transform: translate(-50%, -50%);
}

.analytics-outlook-dot {
 width: 9px;
 height: 9px;
 border: 2px solid var(--surface);
 border-radius: 50%;
 background: #2563eb;
}

.analytics-outlook-marker.is-forecast .analytics-outlook-dot {
 border-color: #7a4bc4;
 background: var(--surface);
}

.analytics-outlook-tip {
 position: absolute;
 bottom: 11px;
 color: var(--heading);
 font-size: 10px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
}

.analytics-outlook-marker.is-forecast .analytics-outlook-tip { color: #7a4bc4; }

.analytics-outlook-axis {
 display: flex;
 justify-content: space-between;
 gap: 6px;
 padding-top: 6px;
 border-top: 1px solid var(--border);
}

.analytics-outlook-axis span {
 flex: 1 1 0;
 min-width: 0;
 color: var(--text-muted);
 font-size: 9.5px;
 line-height: 1.3;
 text-align: center;
}

.analytics-outlook-axis span:first-child { text-align: left; }
.analytics-outlook-axis span:last-child { text-align: right; }
.analytics-outlook-axis span.is-forecast { color: #7a4bc4; font-weight: 800; }

.analytics-outlook-note {
 display: flex;
 align-items: flex-start;
 gap: 9px;
 margin: 12px 0 0;
 padding: 10px 12px;
 border: 1px solid var(--border);
 border-radius: 9px;
 background: var(--surface-subtle);
 color: var(--text-secondary);
 font-size: 11px;
 line-height: 1.5;
}

.analytics-outlook-note .ui-icon { flex: 0 0 auto; margin-top: 1px; color: var(--text-soft); }
.analytics-outlook-note strong { display: block; color: var(--heading); font-size: 11.5px; }

.analytics-outlook-note.is-scheduled { border-color: rgba(14, 124, 102, .3); background: rgba(14, 124, 102, .06); }
.analytics-outlook-note.is-scheduled .ui-icon { color: #0e7c66; }

.analytics-check { display: grid; gap: 3px; margin: 0; padding: 0; list-style: none; }

.analytics-check li {
 display: grid;
 grid-template-columns: 22px minmax(0, 1fr) auto;
 align-items: center;
 gap: 9px;
 padding: 8px 9px;
 border-radius: 8px;
 background: var(--surface-subtle);
}

.analytics-check-mark { display: inline-grid; place-items: center; }
.analytics-check li.is-met .analytics-check-mark { color: #0e7c66; }
.analytics-check li.is-unmet .analytics-check-mark { color: #d08a16; }
.analytics-check li.is-unmet { background: rgba(208, 138, 22, .08); }

.analytics-check-label { min-width: 0; color: var(--text-primary); font-size: 11.5px; font-weight: 600; }

.analytics-check-value {
 color: var(--heading);
 font-size: 11.5px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 white-space: nowrap;
}

.analytics-check-state {
 display: grid;
 gap: 2px;
 margin: 11px 0 0;
 padding: 11px 12px;
 border: 1px solid rgba(208, 138, 22, .32);
 border-radius: 9px;
 background: rgba(208, 138, 22, .07);
}

.analytics-check-state strong { color: var(--heading); font-size: 12px; }
.analytics-check-state span { color: var(--text-secondary); font-size: 11px; line-height: 1.5; }
.analytics-check-state.is-ready { border-color: rgba(14, 124, 102, .32); background: rgba(14, 124, 102, .07); }

.analytics-coverbars {
 display: grid;
 grid-template-columns: repeat(2, minmax(0, 1fr));
 gap: 9px;
 margin: 0;
 padding: 0;
 list-style: none;
}

.analytics-coverbars a {
 display: grid;
 gap: 6px;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface-subtle);
 color: inherit;
 text-decoration: none;
}

.analytics-coverbars a:hover { border-color: var(--border-strong); }
.analytics-coverbars a:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.analytics-coverbars li.is-risk a { border-color: rgba(196, 73, 61, .32); background: rgba(196, 73, 61, .06); }

.analytics-coverbars-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }

.analytics-coverbars-name {
 min-width: 0;
 overflow: hidden;
 color: var(--heading);
 font-size: 11.5px;
 font-weight: 700;
 text-overflow: ellipsis;
 white-space: nowrap;
}

.analytics-coverbars-row {
 display: grid;
 grid-template-columns: 62px minmax(0, 1fr) 46px;
 align-items: center;
 gap: 8px;
}

.analytics-coverbars-key { color: var(--text-muted); font-size: 10px; font-weight: 700; }

.analytics-coverbars-track {
 height: 7px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface);
}

.analytics-coverbars-fill { display: block; height: 100%; min-width: 2px; border-radius: 999px; }
.analytics-coverbars-fill.is-demand { background: #7a4bc4; }
.analytics-coverbars-fill.is-available { background: #0e7c66; }

.analytics-coverbars-value {
 color: var(--heading);
 font-size: 11px;
 font-weight: 800;
 font-variant-numeric: tabular-nums;
 text-align: right;
}

.analytics-notes { display: grid; margin: 0; padding: 0; list-style: none; }

.analytics-notes li {
 display: flex;
 align-items: flex-start;
 gap: 10px;
 padding: 11px 12px;
 border-bottom: 1px solid var(--border);
 color: var(--text-secondary);
 font-size: 11.5px;
 line-height: 1.55;
}

.analytics-notes li:last-child { border-bottom: 0; }

.analytics-notes-icon {
 display: inline-grid;
 width: 26px;
 height: 26px;
 flex: 0 0 auto;
 place-items: center;
 border-radius: 8px;
 color: var(--text-muted);
 background: var(--surface-subtle);
}

.analytics-notes li.tone-urgent .analytics-notes-icon { color: #c4493d; background: rgba(196, 73, 61, .1); }
.analytics-notes li.tone-steady .analytics-notes-icon { color: #0e7c66; background: rgba(14, 124, 102, .1); }
.analytics-notes li.tone-info .analytics-notes-icon { color: #1769e0; background: rgba(23, 105, 224, .1); }

.analytics-method { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 9px; }

.analytics-method > div {
 display: grid;
 gap: 3px;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface-subtle);
}

.analytics-method span { color: var(--text-muted); font-size: 9.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
.analytics-method strong { color: var(--heading); font-size: 12.5px; font-weight: 800; }

.analytics-method-summary { margin: 0 0 8px; color: var(--text-secondary); font-size: 11px; line-height: 1.55; }
.analytics-method-list { display: grid; gap: 5px; margin: 0; padding-left: 17px; color: var(--text-secondary); font-size: 11px; line-height: 1.55; }

.analytics-fold { border-top: 1px solid var(--border); }

.analytics-fold > summary {
 display: flex;
 align-items: center;
 gap: 7px;
 padding: 10px 12px;
 color: var(--interactive);
 font-size: 11px;
 font-weight: 700;
 cursor: pointer;
 list-style: none;
}

.analytics-fold > summary::-webkit-details-marker { display: none; }

.analytics-fold > summary::after {
 content: '';
 width: 6px;
 height: 6px;
 border-right: 1.6px solid currentColor;
 border-bottom: 1.6px solid currentColor;
 transform: translateY(-2px) rotate(45deg);
 transition: transform var(--motion) ease;
}

.analytics-fold[open] > summary::after { transform: translateY(1px) rotate(-135deg); }
.analytics-fold > summary:hover { text-decoration: underline; }
.analytics-fold > summary:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.analytics-fold-body { padding: 0 12px 12px; }

.analytics-mini-table { width: 100%; border-collapse: collapse; }
.analytics-mini-table th {
 padding: 6px 8px;
 border-bottom: 1px solid var(--border);
 color: var(--text-muted);
 font-size: 9.5px;
 font-weight: 800;
 letter-spacing: .04em;
 text-align: left;
 text-transform: uppercase;
}
.analytics-mini-table td { padding: 7px 8px; border-bottom: 1px solid var(--row-border); font-size: 11.5px; }
.analytics-mini-table tr:last-child td { border-bottom: 0; }
.analytics-mini-table .numeric { text-align: right; font-variant-numeric: tabular-nums; }

@media (max-width: 1180px) {
 .analytics-forecast-main { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 900px) {
 .analytics-forecast-pair { grid-template-columns: minmax(0, 1fr); }
 .analytics-coverbars { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 560px) {
 .analytics-method { grid-template-columns: minmax(0, 1fr); }
 .analytics-outlook-axis span { font-size: 9px; }
}

@media (prefers-reduced-motion: reduce) {
 .analytics-fold > summary::after { transition: none; }
}

.analytics-detail {
 width: min(520px, 40vw);
 max-width: 560px;
}

@media (max-width: 1180px) {
 .analytics-detail { width: 60vw; max-width: none; }
}

@media (max-width: 900px) {
 .analytics-detail { width: 72vw; max-width: none; }
}

@media (max-width: 720px) {
 .analytics-detail { width: 100%; max-width: none; border-left: 0; }
}

.analytics-detail-scrim { background: rgba(7, 27, 53, .28); }

.analytics-detail-head { padding: 12px 12px 12px; gap: 12px; }
.analytics-detail-context { font-size: 9.5px; letter-spacing: .06em; }
.analytics-detail-head h2 { margin: 3px 0 0; font-size: 15.5px; }
.analytics-detail-scope { margin: 3px 0 0; font-size: 11px; }

.analytics-detail-close { width: 32px; height: 32px; flex: 0 0 32px; }
.analytics-detail-close:hover { color: var(--interactive); background: var(--surface-hover); border-color: var(--border); }
.analytics-detail-close:focus-visible { outline: 0; box-shadow: var(--focus-ring); }

.analytics-detail-body { gap: 12px; padding: 12px 12px; }

.analytics-detail-figure { gap: 8px; }
.analytics-detail-figure strong { font-size: 25px; }
.analytics-detail-figure span { font-size: 12px; }

.analytics-detail-note { padding: 9px 11px; font-size: 11.5px; line-height: 1.55; }
.analytics-detail-subhead { margin: 2px 0 0; font-size: 10px; }

.analytics-detail-body .analytics-stat-grid {
 grid-template-columns: repeat(2, minmax(0, 1fr));
 gap: 9px;
}

.analytics-detail-body .analytics-stat { gap: 3px; padding: 10px 11px; border-radius: 10px; }
.analytics-detail-body .analytics-stat > span { font-size: 10px; line-height: 1.35; }
.analytics-detail-body .analytics-stat > strong { font-size: 18px; }
.analytics-detail-body .analytics-stat > strong.is-text { font-size: 12.5px; line-height: 1.35; }

.analytics-detail-body .analytics-bars { gap: 9px; }
.analytics-detail-body .analytics-bar-row { gap: 4px; }
.analytics-detail-body .analytics-bar-head { gap: 10px; }
.analytics-detail-body .analytics-bar-name { font-size: 11.5px; }
.analytics-detail-body .analytics-bar-value { font-size: 11px; }
.analytics-detail-body .analytics-bar-track { height: 7px; }

.analytics-detail-body .analytics-table th,
.analytics-detail-body .analytics-table td { padding: 6px 9px; font-size: 11px; }

.analytics-detail-body .analytics-explain summary { font-size: 11.5px; }
.analytics-detail-body .analytics-explain p { font-size: 11px; line-height: 1.55; }

.analytics-detail-foot { padding: 11px 12px; }
.analytics-detail-foot .button { min-height: 34px; padding: 0 13px; font-size: 11.5px; }

@media (max-width: 720px) {
 .analytics-detail-figure strong { font-size: 23px; }
 .analytics-detail-body .analytics-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

body.analytics-detail-open { overflow: hidden; }

.analytics-line--trend { gap: 0; }

.analytics-line--trend .analytics-line-plot { height: 168px; }

.analytics-line--trend .analytics-line-grid { display: none; }

.analytics-line--trend .analytics-line-rule {
 stroke: var(--row-border);
 stroke-width: 1;
}

.analytics-line--trend .analytics-line-rule.is-base { stroke: var(--border); }

.analytics-line--trend .analytics-line-stroke { stroke-width: 2; }

.analytics-line--trend .analytics-line-axis {
 position: relative;
 display: block;

 height: 34px;
 padding: 7px 0 0;
 border-top: 0;
}

.analytics-line--trend .analytics-line-axis span {
 position: absolute;
 top: 7px;

 width: max-content;
 max-width: 96px;
 transform: translateX(-50%);
 color: var(--text-muted);
 font-size: 9.5px;
 line-height: 1.25;
 text-align: center;

 white-space: normal;
 overflow-wrap: break-word;
 display: -webkit-box;
 -webkit-line-clamp: 2;
 -webkit-box-orient: vertical;
 overflow: hidden;
}

.analytics-line--trend .analytics-line-axis span:first-child {
 transform: translateX(0);
 text-align: left;
}

.analytics-line--trend .analytics-line-axis span:last-child {
 transform: translateX(-100%);
 text-align: right;
}

.analytics-line--trend .analytics-line-tip { bottom: calc(100% - 3px); font-size: 10px; }

@media (max-width: 720px) {
 .analytics-line--trend .analytics-line-plot { height: 140px; }
 .analytics-line--trend .analytics-line-axis span { max-width: 74px; font-size: 9px; }
}

.analytics-trend-single { display: block; place-items: initial; padding: 14px 15px; }

.analytics-trend-single-figure {
 display: grid;
 grid-template-columns: minmax(0, 1fr);
 justify-items: stretch;
 gap: 8px;
 width: 100%;
 padding: 14px 16px;
 border: 1px solid var(--border);
 border-radius: 11px;
 background: linear-gradient(180deg, rgba(37, 99, 235, .05), transparent 70%);
 color: inherit;
 text-decoration: none;
}

.analytics-trend-single-figure:hover { border-color: var(--interactive); }
.analytics-trend-single-figure:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-trend-single-value {
 display: flex;
 align-items: baseline;
 justify-content: flex-start;
 gap: 8px;
 color: var(--text-muted);
 font-size: 12px;
 font-weight: 650;
}

.analytics-trend-single-value strong {
 color: var(--heading);
 font-size: 32px;
 font-weight: 750;
 line-height: 1;
 font-variant-numeric: tabular-nums;
}

.analytics-trend-single-bar {
 display: block;
 width: 100%;
 height: 8px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-muted);
}

.analytics-trend-single-bar > span {
 display: block;
 width: 100%;
 height: 100%;
 border-radius: 999px;
 background: #2563eb;
}

.analytics-trend-single-label {
 color: var(--text-muted);
 font-size: 11px;
 text-align: left;
}

.analytics-card[data-card-detail] {
 cursor: pointer;
 transition: border-color var(--motion) ease, box-shadow var(--motion) ease, transform var(--motion) ease;
}

.analytics-card[data-card-detail]:hover {
 border-color: var(--border-strong);
 box-shadow: 0 6px 18px rgba(7, 27, 53, .09);
 transform: translateY(-1px);
}

.analytics-card[data-card-detail]:focus-within {
 border-color: var(--interactive);
 box-shadow: var(--focus-ring);
}

.analytics-card-open {
 display: grid;
 place-items: center;
 width: 26px;
 height: 26px;
 flex-shrink: 0;
 margin-left: auto;
 color: var(--text-soft);
 border-radius: 7px;
 text-decoration: none;
 transition: color var(--motion) ease, background-color var(--motion) ease, transform var(--motion) ease;
}

.analytics-card[data-card-detail]:hover .analytics-card-open {
 color: var(--interactive);
 background: var(--blue-50);
 transform: translateX(2px);
}

.analytics-card-open:focus-visible { outline: none; color: var(--interactive); background: var(--blue-50); }

.analytics-card-head .analytics-card-action { margin-left: auto; }
.analytics-card-head .analytics-card-action + .analytics-card-open { margin-left: 4px; }

@media (prefers-reduced-motion: reduce) {
 .analytics-card[data-card-detail],
 .analytics-card-open { transition: none; }
 .analytics-card[data-card-detail]:hover { transform: none; }
 .analytics-card[data-card-detail]:hover .analytics-card-open { transform: none; }
}

.analytics-bars-chart { display: flex; width: 100%; min-width: 0; flex-direction: column; }

.analytics-bars-plot {
 position: relative;
 display: flex;
 align-items: stretch;
 justify-content: center;
 gap: 10px;
 min-width: 0;
 padding: 4px 2px 0;
}

.analytics-bars-grid {
 position: absolute;
 inset: 22px 0 34px 0;
 background-image: repeating-linear-gradient(
 to bottom,
 var(--row-border) 0,
 var(--row-border) 1px,
 transparent 1px,
 transparent calc(25% - 0.25px)
 );
 opacity: .85;
 pointer-events: none;
}

.analytics-bars-col {

 position: relative;
 display: flex;
 min-width: 0;
 flex: 1 1 0;
 max-width: 92px;
 flex-direction: column;
 align-items: center;
 padding: 0 2px;
 border-radius: 8px;
 color: inherit;
 text-decoration: none;
 transition: background-color var(--motion-fast) ease;
}

.analytics-bars-col:hover { background: var(--surface-hover); }
.analytics-bars-col:focus-visible { outline: none; box-shadow: var(--focus-ring); }

.analytics-bars-track {
 display: flex;
 width: 100%;
 height: 150px;
 flex-direction: column;
 align-items: center;
 justify-content: flex-end;
}

.analytics-bars-value {
 margin-bottom: 5px;
 color: var(--heading);
 font-size: 11px;
 font-weight: 750;
 line-height: 1;
 font-variant-numeric: tabular-nums;
}

.analytics-bars-bar {
 display: block;
 width: 100%;
 max-width: 44px;
 min-height: 3px;
 border-radius: 5px 5px 0 0;
 background: linear-gradient(180deg, #2563eb 0%, #60a5fa 100%);
 transition: filter var(--motion-fast) ease;
}

.analytics-bars-col:hover .analytics-bars-bar { filter: brightness(1.08); }

.analytics-bars-bar.is-zero { background: var(--border-strong); }

.analytics-bars-label {
 display: -webkit-box;
 width: 100%;
 margin-top: 8px;
 padding-top: 7px;
 overflow: hidden;
 border-top: 1px solid var(--row-border);
 color: var(--text-muted);
 font-size: 9.5px;
 line-height: 1.25;
 text-align: center;
 overflow-wrap: break-word;
 -webkit-line-clamp: 2;
 -webkit-box-orient: vertical;
}

.analytics-bars-chart[data-points="1"] .analytics-bars-col { flex: 0 0 auto; width: 132px; max-width: 132px; }
.analytics-bars-chart[data-points="1"] .analytics-bars-bar { max-width: 64px; }

.analytics-bars-chart[data-points="2"] .analytics-bars-col,
.analytics-bars-chart[data-points="3"] .analytics-bars-col { max-width: 118px; }

@media (max-width: 720px) {
 .analytics-bars-plot { gap: 6px; }
 .analytics-bars-track { height: 124px; }
 .analytics-bars-bar { max-width: 34px; }
 .analytics-bars-label { font-size: 9px; }
 .analytics-bars-chart[data-points="1"] .analytics-bars-col { width: 108px; max-width: 108px; }
}

@media (prefers-reduced-motion: reduce) {
 .analytics-bars-col, .analytics-bars-bar { transition: none; }
}

.analytics-page .analytics-filters {
 gap: 12px !important;
}
.analytics-page .analytics-filter-label {
 font-size: 10.5px !important;
 letter-spacing: .045em !important;
}
.analytics-page .analytics-filters select {
 min-height: 44px !important;
 font-size: 12.5px !important;
 font-weight: 650 !important;
 border-radius: 9px !important;
}

.analytics-page .analytics-kpi-card {
 --kpi-accent: #1769e0;
 min-height: 124px !important;
 color: var(--heading) !important;
 background: var(--surface-elevated) !important;
 border: 1px solid var(--border) !important;
 border-top: 3px solid var(--kpi-accent) !important;
 box-shadow: 0 7px 18px rgba(15, 35, 58, .06) !important;
 overflow: hidden;
 transform: none !important;
}
.analytics-page .analytics-kpi-card::before {
 content: "";
 position: absolute;
 inset: 0 auto 0 0;
 width: 4px;
 background: color-mix(in srgb, var(--kpi-accent) 72%, transparent);
 opacity: .55;
}
.analytics-page .analytics-kpi-card::after {
 width: 84px !important;
 height: 84px !important;
 right: -24px !important;
 top: -28px !important;
 background: color-mix(in srgb, var(--kpi-accent) 8%, transparent) !important;
}
.analytics-page .analytics-kpi-card-icon {
 color: var(--kpi-accent) !important;
 background: color-mix(in srgb, var(--kpi-accent) 10%, var(--surface-elevated)) !important;
 border: 1px solid color-mix(in srgb, var(--kpi-accent) 18%, var(--border)) !important;
}
.analytics-page .analytics-kpi-card-label,
.analytics-page .analytics-kpi-card-value,
.analytics-page .analytics-kpi-card-note,
.analytics-page .analytics-kpi-card-meta,
.analytics-page .analytics-kpi-card-arrow {
 color: inherit !important;
}
.analytics-page .analytics-kpi-card-label { color: var(--text-secondary) !important; }
.analytics-page .analytics-kpi-card-note,
.analytics-page .analytics-kpi-card-meta { color: var(--text-muted) !important; }
.analytics-page .analytics-kpi-card-arrow { color: var(--text-soft) !important; }
.analytics-page .analytics-kpi-card:hover,
.analytics-page .analytics-kpi-card:focus-visible {
 border-color: color-mix(in srgb, var(--kpi-accent) 38%, var(--border)) !important;
 box-shadow: 0 10px 24px rgba(15, 35, 58, .10) !important;
 transform: none !important;
}
.analytics-page .analytics-kpi-card:hover .analytics-kpi-card-arrow,
.analytics-page .analytics-kpi-card:focus-visible .analytics-kpi-card-arrow {
 color: var(--kpi-accent) !important;
 transform: none !important;
}

.analytics-kpi-card.tone-requests,
.analytics-kpi-card.tone-released { --kpi-accent: #1769e0; }
.analytics-kpi-card.tone-custody,
.analytics-kpi-card.tone-quantity { --kpi-accent: #6557d9; }
.analytics-kpi-card.tone-available,
.analytics-kpi-card.tone-ontime { --kpi-accent: #0e8a72; }
.analytics-kpi-card.tone-overdue,
.analytics-kpi-card.tone-units { --kpi-accent: #c87912; }
.analytics-kpi-card.tone-stock,
.analytics-kpi-card.tone-attention { --kpi-accent: #d34b4b; }

.analytics-detail-inline {
 margin: 0 0 12px;
 border: 1px solid color-mix(in srgb, var(--interactive) 22%, var(--border));
 border-left: 4px solid var(--interactive);
 border-radius: 12px;
 background: var(--surface-elevated);
 box-shadow: 0 7px 20px rgba(15, 35, 58, .055);
 overflow: hidden;
}
.analytics-detail-inline-head {
 display: flex;
 align-items: flex-start;
 justify-content: space-between;
 gap: 12px;
 padding: 12px 12px 10px;
 border-bottom: 1px solid var(--border);
}
.analytics-detail-inline-head h2 {
 margin: 2px 0 0;
 color: var(--heading);
 font-size: 15.5px;
 line-height: 1.25;
}
.analytics-detail-inline-body {
 display: grid;
 gap: 10px;
 padding: 12px 12px;
}

.analytics-detail-lead {
 display: flex;
 align-items: center;
 gap: 12px;
 min-width: 0;
 padding: 10px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: color-mix(in srgb, var(--interactive) 3%, var(--surface-subtle));
}
.analytics-detail-lead .analytics-detail-figure {
 flex: 0 0 auto;
 min-width: 66px;
}
.analytics-detail-lead .analytics-detail-figure strong { font-size: 27px; }
.analytics-detail-lead .analytics-detail-note {
 flex: 1 1 auto;
 min-width: 0;
 margin: 0;
 padding: 0;
 border: 0;
 border-radius: 0;
 background: transparent;
 font-size: 11.5px;
 line-height: 1.5;
}

.analytics-detail-inline-body .analytics-stat-grid,
.analytics-detail-inline-body .analytics-detail-stats {
 grid-template-columns: repeat(auto-fit, minmax(170px, 240px));
 justify-content: start;
 gap: 10px;
 margin: 0;
}
.analytics-detail-inline-body .analytics-stat {
 min-height: 66px;
 padding: 9px 11px;
}

.analytics-detail-support {
 display: grid;
 grid-template-columns: minmax(0, 1fr);
 gap: 10px;
 min-width: 0;
}
.analytics-detail-support.is-split {
 grid-template-columns: repeat(2, minmax(0, 1fr));
}
.analytics-detail-block {
 min-width: 0;
 padding: 11px 12px;
 border: 1px solid var(--border);
 border-radius: 10px;
 background: var(--surface-elevated);
}
.analytics-detail-block .analytics-detail-subhead {
 margin: 0 0 8px;
}
.analytics-detail-block .analytics-bars {
 gap: 8px;
}

.analytics-detail-inline .analytics-compare {
 display: grid;
 gap: 8px;
 margin: 0;
 padding-top: 11px;
 border-top: 0;
}
.analytics-detail-inline .analytics-compare-window {
 margin: -4px 0 2px;
 font-size: 10.5px;
}
.analytics-detail-inline .analytics-compare-rows {
 display: grid;
 grid-template-columns: repeat(3, minmax(0, 1fr));
 gap: 0;
 margin: 0;
}
.analytics-detail-inline .analytics-compare-rows > div {
 gap: 3px;
 padding: 2px 10px;
 border-left: 1px solid var(--border);
}
.analytics-detail-inline .analytics-compare-rows > div:first-child {
 padding-left: 0;
 border-left: 0;
}
.analytics-detail-inline .analytics-compare-rows dt {
 font-size: 10px;
}
.analytics-detail-inline .analytics-compare-rows dd {
 font-size: 13px;
 line-height: 1.35;
}

.analytics-detail-table {
 border: 1px solid var(--border);
 border-radius: 10px;
}
.analytics-detail-empty { margin: 0 !important; }
.analytics-detail-explain { margin: 0; }

.analytics-detail-inline-foot {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 12px;
 padding: 9px 12px;
 border-top: 1px solid var(--border);
 background: var(--surface-subtle);
 color: var(--text-muted);
 font-size: 11px;
}
.analytics-detail-footnote {
 display: flex;
 align-items: flex-start;
 gap: 7px;
 min-width: 0;
 max-width: 760px;
 margin: 0;
 line-height: 1.45;
}
.analytics-detail-footnote .ui-icon {
 flex: 0 0 auto;
 margin-top: 1px;
 color: var(--interactive);
}
.analytics-detail-foot-spacer { min-width: 1px; }
.analytics-detail-inline-foot .button {
 flex: 0 0 auto;
 min-height: 34px;
 padding-inline: 12px;
 font-size: 11.5px;
}

.analytics-overview-main .analytics-line--trend .analytics-line-stroke {
 stroke-width: 2.2px !important;
}
.analytics-overview-main .analytics-line--trend .analytics-line-area {
 opacity: .55;
}

.analytics-peak-heat {
 display: grid;
 grid-template-columns: repeat(7, minmax(0, 1fr));
 gap: 8px;
}
.analytics-peak-cell {
 display: grid;
 place-items: center;
 min-height: 88px;
 padding: 9px 6px;
 border: 1px solid var(--border);
 border-radius: 9px;
 color: var(--heading);
 background: var(--surface-subtle);
 cursor: default;
}
.analytics-peak-cell-day {
 color: var(--text-muted);
 font-size: 10px;
 font-weight: 800;
 letter-spacing: .045em;
 text-transform: uppercase;
}
.analytics-peak-cell strong {
 font-size: 20px;
 line-height: 1;
 font-variant-numeric: tabular-nums;
}
.analytics-peak-cell small {
 color: var(--interactive);
 font-size: 9px;
 font-weight: 800;
 text-transform: uppercase;
}
.analytics-peak-cell.heat-1 { background: color-mix(in srgb, var(--interactive) 5%, var(--surface-elevated)); }
.analytics-peak-cell.heat-2 { background: color-mix(in srgb, var(--interactive) 9%, var(--surface-elevated)); }
.analytics-peak-cell.heat-3 { background: color-mix(in srgb, var(--interactive) 14%, var(--surface-elevated)); }
.analytics-peak-cell.heat-4 { background: color-mix(in srgb, var(--interactive) 20%, var(--surface-elevated)); }
.analytics-peak-cell.heat-5 { background: color-mix(in srgb, var(--interactive) 27%, var(--surface-elevated)); }
.analytics-peak-cell.is-peak {
 border-color: color-mix(in srgb, var(--interactive) 55%, var(--border));
 box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--interactive) 18%, transparent);
}

.analytics-condition-composition {
 display: grid;
 gap: 12px;
}
.analytics-condition-bar {
 display: flex;
 width: 100%;
 height: 18px;
 overflow: hidden;
 border-radius: 999px;
 background: var(--surface-muted);
}
.analytics-condition-segment {
 min-width: 2px;
 outline: none;
}
.analytics-condition-segment.is-good,
.analytics-condition-key.is-good { background: #0e8a72; }
.analytics-condition-segment.is-issue,
.analytics-condition-key.is-issue { background: #d97706; }
.analytics-condition-legend {
 display: grid;
 gap: 8px;
 margin: 0;
 padding: 0;
 list-style: none;
}
.analytics-condition-legend li {
 display: grid;
 grid-template-columns: 10px minmax(0, 1fr) auto auto;
 align-items: center;
 gap: 8px;
 color: var(--text-secondary);
 font-size: 11.5px;
}
.analytics-condition-key {
 width: 8px;
 height: 8px;
 border-radius: 999px;
}
.analytics-condition-legend strong {
 color: var(--heading);
 font-variant-numeric: tabular-nums;
}
.analytics-condition-legend small { color: var(--text-muted); }

.analytics-forecast-pair.is-waiting .analytics-card {
 background: color-mix(in srgb, var(--surface-subtle) 72%, var(--surface-elevated));
 box-shadow: none;
}
.analytics-forecast-pair.is-waiting .analytics-card-head {
 opacity: .78;
}

@media (max-width: 980px) {
 .analytics-detail-support.is-split {
 grid-template-columns: minmax(0, 1fr);
 }
 .analytics-detail-inline-body .analytics-stat-grid,
 .analytics-detail-inline-body .analytics-detail-stats {
 grid-template-columns: repeat(2, minmax(0, 1fr));
 }
 .analytics-peak-heat {
 grid-template-columns: repeat(4, minmax(0, 1fr));
 }
}
@media (max-width: 620px) {
 .analytics-detail-lead {
 align-items: flex-start;
 flex-direction: column;
 gap: 7px;
 }
 .analytics-detail-lead .analytics-detail-figure { min-width: 0; }
 .analytics-detail-inline .analytics-compare-rows {
 grid-template-columns: minmax(0, 1fr);
 }
 .analytics-detail-inline .analytics-compare-rows > div,
 .analytics-detail-inline .analytics-compare-rows > div:first-child {
 padding: 7px 0;
 border-left: 0;
 border-top: 1px solid var(--border);
 }
 .analytics-detail-inline .analytics-compare-rows > div:first-child { border-top: 0; }
 .analytics-detail-inline-foot {
 align-items: stretch;
 flex-direction: column;
 }
 .analytics-detail-inline-body .analytics-stat-grid,
 .analytics-detail-inline-body .analytics-detail-stats {
 grid-template-columns: minmax(0, 1fr);
 }
 .analytics-detail-inline-foot .button { justify-content: center; }
 .analytics-peak-heat {
 grid-template-columns: repeat(2, minmax(0, 1fr));
 }
}

</style>

<style>

.analytics-page .analytics-tabs {
 display:grid !important;
 grid-auto-flow:column !important;
 grid-auto-columns:minmax(max-content,1fr) !important;
 gap:4px !important;
 padding:4px !important;
 overflow-x:auto !important;
 border:1px solid var(--border) !important;
 border-radius:11px !important;
 background:#fff !important;
 box-shadow:none !important;
}
.analytics-page .analytics-tab {
 position:relative !important;
 display:inline-flex !important;
 min-height:36px !important;
 align-items:center !important;
 justify-content:center !important;
 padding:0 12px !important;
 border:0 !important;
 border-radius:8px !important;
 background:transparent !important;
 color:#475569 !important;
 font-size:11px !important;
 font-weight:800 !important;
 box-shadow:none !important;
 white-space:nowrap !important;
}
.analytics-page .analytics-tab:hover,
.analytics-page .analytics-tab:focus-visible {
 color:#1d4ed8 !important;
 background:#eff6ff !important;
}
.analytics-page .analytics-tab:focus-visible {
 outline:2px solid #2563eb !important;
 outline-offset:-2px !important;
}
.analytics-page .analytics-tab.is-active {
 color:#fff !important;
 background:linear-gradient(135deg, #2563eb, #4f46e5) !important;
 box-shadow:none !important;
}

.analytics-page .analytics-kpi-card {
 --kpi-accent:#1769e0;
 position:relative !important;
 display:grid !important;
 grid-template-columns:34px minmax(0,1fr) 16px !important;
 grid-template-rows:auto auto auto auto !important;
 column-gap:12px !important;
 row-gap:2px !important;
 min-height:122px !important;
 padding:12px 16px !important;
 border:1px solid transparent !important;
 border-radius:12px !important;
 color:#fff !important;
 background:linear-gradient(135deg, var(--kpi-from), var(--kpi-to)) !important;
 box-shadow:0 1px 2px rgba(7,27,53,.05) !important;
 transform:none !important;
}
.analytics-page .analytics-kpi-card.tone-requests { --kpi-from:#1d4ed8; --kpi-to:#2563eb; }
.analytics-page .analytics-kpi-card.tone-custody { --kpi-from:#0f766e; --kpi-to:#047857; }
.analytics-page .analytics-kpi-card.tone-overdue { --kpi-from:#9a3412; --kpi-to:#c2410c; }
.analytics-page .analytics-kpi-card.tone-stock { --kpi-from:#6d28d9; --kpi-to:#7c3aed; }
.analytics-page .analytics-kpi-card::before,
.analytics-page .analytics-kpi-card::after { content:none !important; display:none !important; }
.analytics-page .analytics-kpi-card-icon {
 grid-column:1 !important;
 grid-row:1 / span 2 !important;
 display:grid !important;
 width:34px !important;
 height:34px !important;
 place-items:center !important;
 align-self:center !important;
 margin:0 !important;
 border-radius:50% !important;
 color:#fff !important;
 background:rgba(255,255,255,.16) !important;
 border:1px solid rgba(255,255,255,.24) !important;
}
.analytics-page .analytics-kpi-card-label {
 grid-column:2 !important;
 grid-row:1 !important;
 align-self:end !important;
 font-size:11px !important;
 font-weight:800 !important;
 color:#fff !important;
}
.analytics-page .analytics-kpi-card-value {
 grid-column:2 !important;
 grid-row:2 !important;
 margin:0 !important;
 color:#fff !important;
 font-size:25px !important;
 line-height:1.15 !important;
}
.analytics-page .analytics-kpi-card-note {
 grid-column:2 / -1 !important;
 grid-row:3 !important;
 margin-top:4px !important;
 color:#fff !important;
 font-size:10px !important;
 line-height:1.35 !important;
}
.analytics-page .analytics-kpi-card-meta {
 grid-column:2 / -1;
 color:#fff !important;
 white-space:normal;
 overflow-wrap:anywhere;
}
.analytics-page .analytics-kpi-card-arrow {
 position:relative !important;
 grid-column:3 !important;
 grid-row:1 !important;
 align-self:start !important;
 color:rgba(255,255,255,.8) !important;
}
.analytics-page .analytics-kpi-card:hover,
.analytics-page .analytics-kpi-card:focus-visible {
 transform:none !important;
 border-color:rgba(255,255,255,.5) !important;
 box-shadow:0 10px 24px rgba(7,27,53,.08) !important;
}
.analytics-page .analytics-kpi-card:hover .analytics-kpi-card-arrow,
.analytics-page .analytics-kpi-card:focus-visible .analytics-kpi-card-arrow { color:#fff !important; }
.analytics-page .analytics-kpi-card:focus-visible { outline:2px solid #2563eb; }

.analytics-page .analytics-filters > label,
.analytics-page .analytics-filters > .analytics-filter-field {
 min-height:66px !important;
 min-width:0;
 padding:8px 12px !important;
 border:1px solid #dbe2ea;
 border-radius:10px !important;
 background:#fff;
 box-shadow:none;
}
.analytics-page .analytics-filter-label { color:#64748b; }
.analytics-page .analytics-borrower-trigger > [data-borrower-current] {
 overflow:hidden;
 text-overflow:ellipsis;
 white-space:nowrap;
}
.analytics-page .analytics-filters select,
.analytics-page .analytics-borrower-trigger {
 min-height:32px !important;
 color:#1e293b !important;
 background-color:transparent !important;
}

.analytics-inventory-scope-note {
 display:flex;
 align-items:flex-start;
 gap:7px;
 margin:0;
 padding:9px 11px;
 border:1px solid var(--border);
 border-radius:10px;
 background:var(--surface-subtle);
 color:var(--text-muted);
 font-size:10.5px;
 line-height:1.45;
}
.analytics-inventory-scope-note .ui-icon { flex:0 0 auto; margin-top:1px; color:var(--interactive); }
.analytics-inventory-scope-note strong { color:var(--text-secondary); }

</style>

<style>

.analytics-detail-inline {
 border-color: color-mix(in srgb, var(--interactive) 24%, var(--border));
 border-left: 4px solid var(--interactive);
 box-shadow: 0 6px 18px rgba(15, 35, 58, .05);
}

.analytics-detail-inline-head {
 padding: 12px 16px 12px;
}

.analytics-detail-inline-head h2 {
 margin-top: 3px;
 font-size: 16px;
 line-height: 1.3;
}

.analytics-detail-inline-body {
 gap: 12px;
 padding: 12px 16px;
}

.analytics-detail-lead {
 display: flex;
 align-items: center;
 justify-content: flex-start;
 gap: 12px;
 padding: 13px 12px;
 background: color-mix(
 in srgb,
 var(--interactive) 4%,
 var(--surface-subtle)
 );
 border: 1px solid color-mix(
 in srgb,
 var(--interactive) 15%,
 var(--border)
 );
 border-radius: 10px;
}

.analytics-detail-lead .analytics-detail-figure {
 flex: 0 0 auto;
 min-width: 0;
 margin: 0;
 padding-right: 14px;
 border-right: 1px solid color-mix(
 in srgb,
 var(--interactive) 18%,
 var(--border)
 );
}

.analytics-detail-lead .analytics-detail-figure strong {
 font-size: 28px;
 line-height: 1;
}

.analytics-detail-lead .analytics-detail-note {
 flex: 0 1 auto;
 width: auto;
 max-width: 760px;
 margin: 0;
 padding: 0;
 color: var(--text-secondary);
 background: transparent;
 border: 0;
 font-size: 12px;
 line-height: 1.5;
}

.analytics-detail-support {
 gap: 0;
}

.analytics-detail-block {
 padding: 8px 0 2px;
 border: 0;
 border-radius: 0;
 background: transparent;
}

.analytics-detail-support.is-split {
 grid-template-columns: repeat(2, minmax(0, 1fr));
 column-gap: 16px;
}

.analytics-detail-support.is-split > .analytics-detail-block + .analytics-detail-block {
 padding-left: 18px;
 border-left: 1px solid var(--border);
}

.analytics-detail-block .analytics-detail-subhead {
 margin: 0 0 9px;
}

.analytics-detail-inline .analytics-compare {
 gap: 9px;
 margin: 0;
 padding-top: 8px;
 border: 0;
}

.analytics-detail-inline .analytics-compare-window {
 margin: -3px 0 4px;
 color: var(--text-muted);
 font-size: 11px;
}

.analytics-detail-inline .analytics-compare-rows {
 display: grid;
 grid-template-columns: repeat(3, minmax(0, 1fr));
 gap: 0;
 margin: 0;
}

.analytics-detail-inline .analytics-compare-rows > div {
 min-width: 0;
 padding: 3px 12px;
 border-left: 1px solid var(--border);
}

.analytics-detail-inline .analytics-compare-rows > div:first-child {
 padding-left: 0;
 border-left: 0;
}

.analytics-detail-inline .analytics-compare-rows dt {
 margin: 0 0 4px;
 color: var(--text-muted);
 font-size: 10.5px;
 font-weight: 700;
}

.analytics-detail-inline .analytics-compare-rows dd {
 margin: 0;
 color: var(--heading);
 font-size: 13.5px;
 font-weight: 750;
 line-height: 1.4;
}

.analytics-detail-empty {
 display: flex;
 align-items: center;
 gap: 11px;
 margin: 0 !important;
 padding: 11px 13px !important;
 color: var(--text-secondary) !important;
 background: var(--surface-subtle) !important;
 border: 0 !important;
 border-radius: 9px !important;
 font-size: 12px !important;
 line-height: 1.5 !important;
}

.analytics-detail-empty-icon {
 display: grid;
 place-items: center;
 width: 30px;
 height: 30px;
 flex: 0 0 30px;
 color: var(--text-muted);
 background: var(--surface-muted);
 border-radius: 50%;
}

.analytics-detail-table {
 border: 1px solid var(--border);
 border-radius: 10px;
}

.analytics-detail-inline-foot {
 padding: 10px 16px;
 background: color-mix(
 in srgb,
 var(--surface-subtle) 75%,
 var(--surface-elevated)
 );
}

@media (max-width: 620px) {
 .analytics-detail-lead {
 align-items: flex-start;
 flex-direction: column;
 gap: 7px;
 }

 .analytics-detail-lead .analytics-detail-figure {
 padding-right: 0;
 padding-bottom: 8px;
 border-right: 0;
 border-bottom: 1px solid var(--border);
 }

 .analytics-detail-support.is-split {
 grid-template-columns: minmax(0, 1fr);
 row-gap: 10px;
 }

 .analytics-detail-support.is-split > .analytics-detail-block + .analytics-detail-block {
 padding-left: 0;
 padding-top: 12px;
 border-left: 0;
 border-top: 1px solid var(--border);
 }

 .analytics-detail-inline .analytics-compare-rows {
 grid-template-columns: minmax(0, 1fr);
 }

 .analytics-detail-inline .analytics-compare-rows > div,
 .analytics-detail-inline .analytics-compare-rows > div:first-child {
 padding: 8px 0;
 border-left: 0;
 border-top: 1px solid var(--border);
 }

 .analytics-detail-inline .analytics-compare-rows > div:first-child {
 border-top: 0;
 }
}

.analytics-page .analytics-kpis {
 display: grid !important;
 grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
 gap: 12px !important;
 width: 100% !important;
}

.analytics-page .analytics-kpi-card {
 --card-from: #2588ef;
 --card-to: #1769de;
 --card-shadow: rgba(23, 105, 224, .22);

 position: relative !important;

 display: grid !important;
 grid-template-columns: 58px minmax(0, 1fr) 30px !important;
 grid-template-rows: auto auto auto auto !important;

 align-content: center !important;
 align-items: center !important;

 column-gap: 11px !important;
 row-gap: 2px !important;

 width: 100% !important;
 min-width: 0 !important;
 min-height: 138px !important;

 padding: 12px 12px 12px 19px !important;

 overflow: hidden !important;

 border: 0 !important;
 border-radius: 12px !important;

 background:
 linear-gradient(
 135deg,
 var(--card-from) 0%,
 var(--card-to) 100%
 ) !important;

 box-shadow:
 0 7px 18px var(--card-shadow) !important;

 color: #fff !important;
 text-decoration: none !important;

 cursor: pointer !important;

 transform: translateY(0) !important;

 transition:
 transform 160ms ease,
 box-shadow 160ms ease,
 filter 160ms ease !important;
}

.analytics-page .analytics-kpi-card::before {
 content: "" !important;

 position: absolute !important;

 right: -28px !important;
 bottom: -56px !important;

 display: block !important;

 width: 170px !important;
 height: 78px !important;

 border-radius: 50% !important;

 background: rgba(255, 255, 255, .075) !important;

 pointer-events: none !important;
}

.analytics-page .analytics-kpi-card::after {
 content: "" !important;

 position: absolute !important;

 left: -70px !important;
 top: -80px !important;

 display: block !important;

 width: 180px !important;
 height: 180px !important;

 border-radius: 50% !important;

 background: rgba(255, 255, 255, .055) !important;

 pointer-events: none !important;
}

.analytics-page .analytics-kpi-card > * {
 position: relative !important;
 z-index: 1 !important;
}

.analytics-page .analytics-kpi-card-icon {
 grid-column: 1 !important;
 grid-row: 1 / span 4 !important;

 display: grid !important;
 place-items: center !important;

 align-self: center !important;

 width: 54px !important;
 height: 54px !important;

 margin: 0 !important;

 border: 1px solid rgba(255, 255, 255, .40) !important;
 border-radius: 50% !important;

 background: rgba(255, 255, 255, .09) !important;

 color: #fff !important;

 box-shadow:
 inset 0 0 0 1px rgba(255, 255, 255, .04) !important;
}

.analytics-page .analytics-kpi-card-label {
 grid-column: 2 !important;
 grid-row: 1 !important;

 align-self: end !important;

 min-width: 0 !important;

 margin: 0 0 1px !important;

 color: #fff !important;

 font-size: 11px !important;
 font-weight: 800 !important;
 line-height: 1.22 !important;
 letter-spacing: 0 !important;

 text-transform: none !important;

 display: -webkit-box !important;
 -webkit-box-orient: vertical !important;
 -webkit-line-clamp: 2 !important;

 overflow: hidden !important;
}

.analytics-page .analytics-kpi-card-value {
 grid-column: 2 !important;
 grid-row: 2 !important;

 margin: 0 !important;

 color: #fff !important;

 font-size: 28px !important;
 font-weight: 800 !important;
 line-height: 1.05 !important;

 font-variant-numeric: tabular-nums !important;
}

.analytics-page .analytics-kpi-card-value.is-text {
 font-size: 19px !important;
 line-height: 1.12 !important;
}

.analytics-page .analytics-kpi-card-note {
 grid-column: 2 / span 2 !important;
 grid-row: 3 !important;

 min-width: 0 !important;

 margin: 4px 0 0 !important;

 color: rgba(255, 255, 255, .94) !important;

 font-size: 10px !important;
 font-weight: 600 !important;
 line-height: 1.35 !important;

 display: -webkit-box !important;
 -webkit-box-orient: vertical !important;
 -webkit-line-clamp: 2 !important;

 overflow: hidden !important;
}

.analytics-page .analytics-kpi-card-meta {
 grid-column: 2 / span 2 !important;
 grid-row: 4 !important;

 min-width: 0 !important;

 margin: 3px 0 0 !important;

 color: rgba(255, 255, 255, .78) !important;

 font-size: 9.5px !important;
 font-weight: 600 !important;
 line-height: 1.25 !important;

 overflow: hidden !important;
 text-overflow: ellipsis !important;
 white-space: nowrap !important;
}

.analytics-page .analytics-kpi-card-arrow {
 position: absolute !important;

 top: 14px !important;
 right: 14px !important;

 display: grid !important;
 place-items: center !important;

 width: 29px !important;
 height: 29px !important;

 padding: 7px !important;

 border: 1px solid rgba(255, 255, 255, .18) !important;
 border-radius: 50% !important;

 background: rgba(255, 255, 255, .10) !important;

 color: #fff !important;

 opacity: .92 !important;

 box-sizing: border-box !important;

 transform: none !important;

 transition:
 background 160ms ease,
 transform 160ms ease !important;
}

.analytics-page .analytics-kpi-card:hover {
 color: #fff !important;

 filter: brightness(1.025) !important;

 box-shadow:
 0 10px 22px var(--card-shadow) !important;

 transform: translateY(-2px) !important;
}

.analytics-page .analytics-kpi-card:hover .analytics-kpi-card-arrow {
 background: rgba(255, 255, 255, .17) !important;
 transform: translateX(1px) !important;
}

.analytics-page .analytics-kpi-card:focus-visible {
 outline: 3px solid rgba(255, 255, 255, .75) !important;
 outline-offset: 2px !important;
}

.analytics-page .analytics-kpi-card.tone-requests,
.analytics-page .analytics-kpi-card.tone-released,
.analytics-page .analytics-kpi-card.tone-quantity {
 --card-from: #36a4f4;
 --card-to: #1768de;
 --card-shadow: rgba(23, 105, 224, .24);
}

.analytics-page .analytics-kpi-card.tone-custody,
.analytics-page .analytics-kpi-card.tone-available,
.analytics-page .analytics-kpi-card.tone-ontime {
 --card-from: #2dc6a4;
 --card-to: #0a987b;
 --card-shadow: rgba(10, 152, 123, .22);
}

.analytics-page .analytics-kpi-card.tone-overdue,
.analytics-page .analytics-kpi-card.tone-attention {
 --card-from: #ff6064;
 --card-to: #df292f;
 --card-shadow: rgba(223, 41, 47, .22);
}

.analytics-page .analytics-kpi-card.tone-stock,
.analytics-page .analytics-kpi-card.tone-units {
 --card-from: #ffbc45;
 --card-to: #ed8b00;
 --card-shadow: rgba(237, 139, 0, .22);
}

.analytics-page .analytics-forecast-kpis .analytics-kpi-card {
 grid-template-columns: 58px minmax(0, 1fr) 30px !important;
 grid-template-rows: auto auto auto auto !important;

 min-height: 138px !important;

 padding: 12px 12px 12px 19px !important;
}

.analytics-page .analytics-forecast-kpis .analytics-kpi-card-icon {
 grid-column: 1 !important;
 grid-row: 1 / span 4 !important;
}

.analytics-page .analytics-forecast-kpis .analytics-kpi-card-label {
 grid-column: 2 !important;
 grid-row: 1 !important;
}

.analytics-page .analytics-forecast-kpis .analytics-kpi-card-value {
 grid-column: 2 !important;
 grid-row: 2 !important;
}

.analytics-page .analytics-forecast-kpis .analytics-kpi-card-note {
 grid-column: 2 / span 2 !important;
 grid-row: 3 !important;
}

@media (max-width: 1050px) {
 .analytics-page .analytics-kpis {
 grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
 }
}

@media (max-width: 620px) {
 .analytics-page .analytics-kpis {
 grid-template-columns: minmax(0, 1fr) !important;
 }

 .analytics-page .analytics-kpi-card {
 min-height: 130px !important;
 }
}

@media (prefers-reduced-motion: reduce) {
 .analytics-page .analytics-kpi-card,
 .analytics-page .analytics-kpi-card-arrow {
 transition: none !important;
 }

 .analytics-page .analytics-kpi-card:hover,
 .analytics-page .analytics-kpi-card:hover .analytics-kpi-card-arrow {
 transform: none !important;
 }
}

.analytics-page .analytics-kpi-card-label {
 font-size: 12px !important;
 font-weight: 800 !important;
 line-height: 1.25 !important;
 letter-spacing: 0 !important;
}

.analytics-page .analytics-kpi-card-value {
 font-size: 30px !important;
 font-weight: 800 !important;
 line-height: 1.05 !important;
}

.analytics-page .analytics-kpi-card-value.is-text {
 font-size: 19px !important;
 font-weight: 800 !important;
 line-height: 1.15 !important;
}

.analytics-page .analytics-kpi-card-note {
 margin-top: 5px !important;
 font-size: 11px !important;
 font-weight: 600 !important;
 line-height: 1.4 !important;
}

.analytics-page .analytics-kpi-card-meta {
 margin-top: 4px !important;
 font-size: 10px !important;
 font-weight: 600 !important;
 line-height: 1.35 !important;
}

.analytics-page .analytics-kpi-card-label {
 -webkit-line-clamp: 2 !important;
}

.analytics-page .analytics-kpi-card-note {
 -webkit-line-clamp: 2 !important;
}

.analytics-page .analytics-kpi-card-meta {
 display: none !important;
}

.analytics-page .analytics-kpi-card-note {
 margin-bottom: 0 !important;
}

.analytics-page .analytics-kpi-card {
 row-gap: 2px !important;
}

.analytics-page .analytics-kpi-card.tone-requests {
 --card-from: #39a7f4 !important;
 --card-to: #176bdd !important;
 --card-shadow: rgba(23, 107, 221, .24) !important;

 --kpi-from: #39a7f4 !important;
 --kpi-to: #176bdd !important;
 --kpi-glow: rgba(23, 107, 221, .24) !important;
}

.analytics-page .analytics-kpi-card.tone-quantity,
.analytics-page .analytics-kpi-card.tone-accountability {
 --card-from: #8868f4 !important;
 --card-to: #5c3edb !important;
 --card-shadow: rgba(92, 62, 219, .23) !important;

 --kpi-from: #8868f4 !important;
 --kpi-to: #5c3edb !important;
 --kpi-glow: rgba(92, 62, 219, .23) !important;
}

.analytics-page .analytics-kpi-card.tone-released,
.analytics-page .analytics-kpi-card.tone-custody,
.analytics-page .analytics-kpi-card.tone-available,
.analytics-page .analytics-kpi-card.tone-ontime {
 --card-from: #35c7a4 !important;
 --card-to: #0c987b !important;
 --card-shadow: rgba(12, 152, 123, .22) !important;

 --kpi-from: #35c7a4 !important;
 --kpi-to: #0c987b !important;
 --kpi-glow: rgba(12, 152, 123, .22) !important;
}

.analytics-page .analytics-kpi-card.tone-units,
.analytics-page .analytics-kpi-card.tone-stock {
 --card-from: #ffbd49 !important;
 --card-to: #ed8d00 !important;
 --card-shadow: rgba(237, 141, 0, .22) !important;

 --kpi-from: #ffbd49 !important;
 --kpi-to: #ed8d00 !important;
 --kpi-glow: rgba(237, 141, 0, .22) !important;
}

.analytics-page .analytics-kpi-card.tone-overdue,
.analytics-page .analytics-kpi-card.tone-attention {
 --card-from: #ff6268 !important;
 --card-to: #df2d36 !important;
 --card-shadow: rgba(223, 45, 54, .22) !important;

 --kpi-from: #ff6268 !important;
 --kpi-to: #df2d36 !important;
 --kpi-glow: rgba(223, 45, 54, .22) !important;
}

.analytics-page .analytics-kpis-overview > .analytics-kpi-card:nth-child(1) {
 background: linear-gradient(135deg, #39a7f4 0%, #176bdd 100%) !important;
 --card-shadow: rgba(23,107,221,.24) !important;
}

.analytics-page .analytics-kpis-overview > .analytics-kpi-card:nth-child(2) {
 background: linear-gradient(135deg, #35c7a4 0%, #0c987b 100%) !important;
 --card-shadow: rgba(12,152,123,.23) !important;
}

.analytics-page .analytics-kpis-overview > .analytics-kpi-card:nth-child(3) {
 background: linear-gradient(135deg, #ff6268 0%, #df2d36 100%) !important;
 --card-shadow: rgba(223,45,54,.23) !important;
}

.analytics-page .analytics-kpis-overview > .analytics-kpi-card:nth-child(4) {
 background: linear-gradient(135deg, #ffbd49 0%, #ed8d00 100%) !important;
 --card-shadow: rgba(237,141,0,.23) !important;
}

.analytics-page .analytics-kpis-demand > .analytics-kpi-card:nth-child(1) {
 background: linear-gradient(135deg, #39a7f4 0%, #176bdd 100%) !important;
 --card-shadow: rgba(23,107,221,.24) !important;
}

.analytics-page .analytics-kpis-demand > .analytics-kpi-card:nth-child(2) {
 background: linear-gradient(135deg, #8c70f6 0%, #5f43df 100%) !important;
 --card-shadow: rgba(95,67,223,.23) !important;
}

.analytics-page .analytics-kpis-demand > .analytics-kpi-card:nth-child(3) {
 background: linear-gradient(135deg, #35c7a4 0%, #0c987b 100%) !important;
 --card-shadow: rgba(12,152,123,.23) !important;
}

.analytics-page .analytics-kpis-demand > .analytics-kpi-card:nth-child(4) {
 background: linear-gradient(135deg, #ffbd49 0%, #ed8d00 100%) !important;
 --card-shadow: rgba(237,141,0,.23) !important;
}

.analytics-page .analytics-kpis-inventory > .analytics-kpi-card:nth-child(1) {
 background: linear-gradient(135deg, #35c7a4 0%, #0c987b 100%) !important;
 --card-shadow: rgba(12,152,123,.23) !important;
}

.analytics-page .analytics-kpis-inventory > .analytics-kpi-card:nth-child(2) {
 background: linear-gradient(135deg, #8c70f6 0%, #5f43df 100%) !important;
 --card-shadow: rgba(95,67,223,.23) !important;
}

.analytics-page .analytics-kpis-inventory > .analytics-kpi-card:nth-child(3) {
 background: linear-gradient(135deg, #39a7f4 0%, #176bdd 100%) !important;
 --card-shadow: rgba(23,107,221,.24) !important;
}

.analytics-page .analytics-kpis-inventory > .analytics-kpi-card:nth-child(4) {
 background: linear-gradient(135deg, #ff6268 0%, #df2d36 100%) !important;
 --card-shadow: rgba(223,45,54,.23) !important;
}

.analytics-page .analytics-kpis-returns > .analytics-kpi-card:nth-child(1) {
 background: linear-gradient(135deg, #35c7a4 0%, #0c987b 100%) !important;
 --card-shadow: rgba(12,152,123,.23) !important;
}

.analytics-page .analytics-kpis-returns > .analytics-kpi-card:nth-child(2) {
 background: linear-gradient(135deg, #ff6268 0%, #df2d36 100%) !important;
 --card-shadow: rgba(223,45,54,.23) !important;
}

.analytics-page .analytics-kpis-returns > .analytics-kpi-card:nth-child(3) {
 background: linear-gradient(135deg, #ffbd49 0%, #ed8d00 100%) !important;
 --card-shadow: rgba(237,141,0,.23) !important;
}

.analytics-page .analytics-kpis-returns > .analytics-kpi-card:nth-child(4) {
 background: linear-gradient(135deg, #8c70f6 0%, #5f43df 100%) !important;
 --card-shadow: rgba(95,67,223,.23) !important;
}

.analytics-page .analytics-kpis-forecast > .analytics-kpi-card:nth-child(1) {
 background: linear-gradient(135deg, #8c70f6 0%, #5f43df 100%) !important;
 --card-shadow: rgba(95,67,223,.23) !important;
}

.analytics-page .analytics-kpis-forecast > .analytics-kpi-card:nth-child(2) {
 background: linear-gradient(135deg, #39a7f4 0%, #176bdd 100%) !important;
 --card-shadow: rgba(23,107,221,.24) !important;
}

.analytics-page .analytics-kpis-forecast > .analytics-kpi-card:nth-child(3) {
 background: linear-gradient(135deg, #35c7a4 0%, #0c987b 100%) !important;
 --card-shadow: rgba(12,152,123,.23) !important;
}

.analytics-page .analytics-kpis-forecast > .analytics-kpi-card:nth-child(4) {
 background: linear-gradient(135deg, #ff6268 0%, #df2d36 100%) !important;
 --card-shadow: rgba(223,45,54,.23) !important;
}

.analytics-page
.analytics-kpis-overview > .analytics-kpi-card,
.analytics-page
.analytics-kpis-demand > .analytics-kpi-card,
.analytics-page
.analytics-kpis-inventory > .analytics-kpi-card,
.analytics-page
.analytics-kpis-returns > .analytics-kpi-card,
.analytics-page
.analytics-kpis-forecast > .analytics-kpi-card {
 color: #fff !important;
}

.analytics-page
.analytics-kpis-overview > .analytics-kpi-card .analytics-kpi-card-label,
.analytics-page
.analytics-kpis-overview > .analytics-kpi-card .analytics-kpi-card-value,
.analytics-page
.analytics-kpis-overview > .analytics-kpi-card .analytics-kpi-card-note,

.analytics-page
.analytics-kpis-demand > .analytics-kpi-card .analytics-kpi-card-label,
.analytics-page
.analytics-kpis-demand > .analytics-kpi-card .analytics-kpi-card-value,
.analytics-page
.analytics-kpis-demand > .analytics-kpi-card .analytics-kpi-card-note,

.analytics-page
.analytics-kpis-inventory > .analytics-kpi-card .analytics-kpi-card-label,
.analytics-page
.analytics-kpis-inventory > .analytics-kpi-card .analytics-kpi-card-value,
.analytics-page
.analytics-kpis-inventory > .analytics-kpi-card .analytics-kpi-card-note,

.analytics-page
.analytics-kpis-returns > .analytics-kpi-card .analytics-kpi-card-label,
.analytics-page
.analytics-kpis-returns > .analytics-kpi-card .analytics-kpi-card-value,
.analytics-page
.analytics-kpis-returns > .analytics-kpi-card .analytics-kpi-card-note,

.analytics-page
.analytics-kpis-forecast > .analytics-kpi-card .analytics-kpi-card-label,
.analytics-page
.analytics-kpis-forecast > .analytics-kpi-card .analytics-kpi-card-value,
.analytics-page
.analytics-kpis-forecast > .analytics-kpi-card .analytics-kpi-card-note {
 color: #fff !important;
}

</style>
