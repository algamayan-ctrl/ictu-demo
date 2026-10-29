<style>
.sao-release-browser { display: flex; flex-direction: column; gap: 14px; width: 100%; max-width: none; min-width: 0; }
.sao-release-browser [hidden] { display: none !important; }
.sao-release-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) minmax(170px, 230px) minmax(150px, 190px); flex: 0 0 auto; gap: 12px; align-items: end; padding: 14px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface-elevated); }
.sao-release-toolbar label { display: grid; gap: 6px; margin: 0; color: var(--text-muted); font-size: 12px; font-weight: 750; }
.sao-release-toolbar input, .sao-release-toolbar select { width: 100%; min-height: 42px; margin: 0; }
.sao-release-browser .sao-release-records { display: flex; flex-direction: column; flex: 1 0 auto; width: 100%; max-width: none; min-width: 0; min-height: 320px; margin: 0; padding: 0; overflow: hidden; }
.app-shell .app-main .content-area.sao-release-browser > .card.sao-release-records { padding: 0 !important; }
.sao-release-table-wrap { flex: 0 0 auto; width: 100%; min-width: 0; border: 0; border-radius: 0; overflow-x: auto; }
.sao-release-table { width: 100%; min-width: 860px; margin: 0; table-layout: fixed; border-collapse: collapse; }
.sao-release-table th { padding: 13px 14px; color: var(--text-secondary); background: var(--surface-subtle); font-size: 11px; font-weight: 750; letter-spacing: .02em; text-transform: uppercase; text-align: left; }
.sao-release-table td { padding: 16px 14px; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); color: var(--heading); font-size: 13px; line-height: 1.5; vertical-align: middle; overflow-wrap: anywhere; }
.sao-release-table td strong { display: block; font-weight: 650; }
.sao-release-table td small { display: block; margin-top: 4px; color: var(--text-muted); font-size: 12px; }
.sao-release-table td:last-child { padding-left: 8px; padding-right: 8px; }
.sao-release-table td .button { padding: 8px 10px; font-size: 12px; white-space: nowrap; text-align: center; }
.sao-release-footer { display: flex; align-items: center; justify-content: space-between; flex: 0 0 auto; flex-wrap: wrap; gap: 14px; margin-top: auto; padding: 16px 20px; border-top: 1px solid var(--border); }
.sao-release-footer p { margin: 0; }
.sao-release-pagination, .sao-release-pagination nav { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
.sao-release-pagination select { width: auto; min-height: 40px; margin: 0; }
.sao-release-pagination .button:disabled { opacity: .5; cursor: default; }
.sao-release-empty { display: flex; flex: 1 1 auto; flex-direction: column; align-items: center; justify-content: center; min-height: 280px; padding: 36px 20px; text-align: center; }
.sao-release-empty-icon { display: grid; place-items: center; width: 72px; height: 72px; margin-bottom: 18px; border-radius: 50%; background: var(--info-bg); color: var(--interactive); }
.sao-release-empty h2 { margin: 0 0 8px; color: var(--heading); font-size: 16px; line-height: 1.4; }
.sao-release-empty p { max-width: 42ch; margin: 0; color: var(--text-secondary); font-size: 13px; line-height: 1.6; }
@media (max-width: 760px) { .sao-release-toolbar { grid-template-columns: minmax(0, 1fr); } .sao-release-footer { padding: 14px; } }
</style>
