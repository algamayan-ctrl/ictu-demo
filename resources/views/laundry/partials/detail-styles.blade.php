<style>
.laundry-detail { width: 100%; max-width: none; min-width: 0; }
.laundry-detail .laundry-record-card { width: 100%; max-width: none; min-width: 0; margin: 0; padding: 20px; }
.laundry-record-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 20px; padding: 0; border: 0; background: transparent; }
.laundry-record-header h2 { margin: 0; font-size: 16px; font-weight: 750; line-height: 1.4; }
.laundry-record-facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 22px 28px; margin: 0; }
.laundry-record-facts > div { min-width: 0; }
.laundry-record-facts dt { margin: 0 0 5px; color: var(--text-muted); font-size: 12px; line-height: 1.4; }
.laundry-record-facts dd { margin: 0; color: var(--heading); font-size: 13px; font-weight: 650; line-height: 1.5; overflow-wrap: anywhere; }
.laundry-record-items { width: 100%; min-width: 0; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); }
.laundry-record-items h3 { margin: 0 0 14px; color: var(--heading); font-size: 14px; font-weight: 750; }
.laundry-record-table-wrap { width: 100%; min-width: 0; border: 1px solid var(--border); border-radius: 8px; overflow-x: auto; }
.laundry-record-table { width: 100%; min-width: 0; table-layout: fixed; border-collapse: collapse; margin: 0; }
.laundry-record-table th { padding: 12px 14px; color: var(--text-secondary); background: var(--surface-subtle); font-size: 11px; font-weight: 750; text-transform: uppercase; letter-spacing: .02em; }
.laundry-record-table td { padding: 14px; border-top: 1px solid var(--border); color: var(--heading); font-size: 13px; line-height: 1.5; vertical-align: top; overflow-wrap: anywhere; }
.laundry-record-table .is-numeric { text-align: center; font-variant-numeric: tabular-nums; }
.laundry-record-table td strong { font-weight: 650; }
.laundry-record-table td small { display: block; margin-top: 3px; color: var(--text-muted); font-size: 12px; }
.laundry-condition-finding { display: block; }
.laundry-record-form { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); }
.laundry-form-label { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1 1 250px; }
.laundry-form-label > .ui-icon { flex-shrink: 0; color: var(--interactive); }
.laundry-form-label h3 { margin: 0 0 4px; color: var(--heading); font-size: 13px; font-weight: 750; }
.laundry-form-label p { margin: 0; color: var(--text-muted); font-size: 12px; line-height: 1.5; }
.laundry-record-actions { flex-shrink: 0; margin: 0; }
.laundry-record-actions .button { font-size: 12px; }
.laundry-record-note { margin: 18px 0 0; }
.laundry-record-findings { margin-top: 20px; padding: 14px 16px; border: 1px solid var(--warning-border); border-radius: 8px; color: var(--warning); background: var(--warning-bg); font-size: 12px; line-height: 1.5; }
.laundry-record-findings strong { display: block; }
.laundry-record-findings p { margin: 6px 0; }
.laundry-record-findings a { display: inline-block; margin-top: 6px; }
@media (max-width: 900px) {
    .laundry-record-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .laundry-detail .laundry-record-card { padding: 16px; }
    .laundry-record-header { align-items: flex-start; flex-wrap: wrap; }
    .laundry-record-facts { grid-template-columns: minmax(0, 1fr); gap: 18px; }
    .laundry-record-actions { flex-wrap: wrap; }
    .laundry-record-table th, .laundry-record-table td { padding: 10px 8px; font-size: 11px; }
}
</style>
