<style>
.registration-page { width: min(calc(100% - 24px), 760px); margin: 0 auto; padding: 8px 0 32px; }
.registration-heading { margin-bottom: 22px; text-align: center; }
.registration-heading h1 { margin: 5px 0 8px; color: var(--heading); font-size: clamp(22px, 3vw, 28px); font-weight: 750; }
.registration-heading p:not(.eyebrow), .registration-form-heading p { margin: 0; color: var(--text-muted); font-size: 13px; }
.registration-card { padding: 26px; background: var(--surface-elevated); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.registration-account-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.registration-type-label { text-align: center; font-weight: 700; color: var(--heading); margin: 0 0 12px; }
.registration-input-wrap input[readonly] { background: var(--blue-50); }
#borrower-registration-form .registration-account-options button { display: flex; align-items: center; gap: 12px; padding: 14px; text-align: left; color: var(--heading); background: var(--surface-elevated); border: 1px solid var(--border); border-radius: var(--radius); }
#borrower-registration-form .registration-account-options button[aria-pressed="true"] { border-color: var(--interactive); background: var(--blue-50); }
.registration-account-options button > span:nth-child(2) { flex: 1; min-width: 0; }
.registration-account-options strong { display: block; font-size: 13px; }
.registration-account-options small { display: block; margin-top: 4px; color: var(--text-muted); font-size: 11.5px; line-height: 1.4; }
.registration-type-icon { display: inline-grid; place-items: center; flex: none; width: 44px; height: 44px; border-radius: 50%; color: var(--interactive); background: var(--blue-50); }
.registration-type-selector { display: grid; grid-template-columns: 168px minmax(0, 1fr); align-items: center; gap: 16px; margin-bottom: 24px; }
.registration-type-selector label, .registration-field > label { margin: 0; color: var(--heading); font-size: 13px; font-weight: 700; }
.registration-form-heading { display: flex; align-items: center; gap: 14px; padding-top: 22px; margin-bottom: 24px; border-top: 1px solid var(--border); }
.registration-form-heading h2 { margin: 0 0 5px; color: var(--heading); font-size: 17px; font-weight: 750; }
.registration-fields, .registration-fields fieldset { display: grid; gap: 16px; min-width: 0; }
.registration-fields fieldset { border: 0; padding: 0; margin: 0; }
.registration-fields fieldset[hidden] { display: none !important; }
.registration-field { display: grid; grid-template-columns: 168px minmax(0, 1fr); align-items: start; gap: 16px; }
.registration-field > label { padding-top: 14px; line-height: 1.45; }
.registration-field-body { min-width: 0; }
.registration-input-wrap { position: relative; }
.registration-input-wrap > .ui-icon { position: absolute; top: 24px; left: 13px; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.registration-card :is(input, select) { min-width: 0; width: 100%; min-height: 48px; margin: 0; padding-left: 40px; border-radius: 9px; color: var(--text); font-size: 13.5px; }
.registration-type-selector select { padding-left: 13px; }
.registration-input-wrap input[type="file"] { padding-top: 11px; padding-bottom: 11px; font-size: 12px; }
.registration-input-wrap.has-password-toggle input { padding-right: 48px; }
#borrower-registration-form .registration-password-toggle { position: absolute; right: 4px; top: 4px; display: grid; place-items: center; width: 40px; height: 40px; padding: 0; color: var(--text-muted); border: 0; background: transparent; border-radius: 6px; }
.registration-field-body small { display: block; margin-top: 6px; color: var(--text-muted); font-size: 11.5px; line-height: 1.5; }
.registration-field-body .registration-error { color: var(--danger); }
.registration-field-body small[hidden] { display: none; }
.registration-status-note { display: flex; align-items: flex-start; gap: 12px; padding: 16px; margin: 24px 0 18px; border-radius: var(--radius); background: var(--warning-bg); color: var(--warning); }
.registration-status-note > .ui-icon { flex: none; margin-top: 2px; }
.registration-status-note strong { font-size: 12px; }
.registration-status-note strong span { display: block; margin-top: 4px; font-weight: 500; }
.registration-status-note p { margin: 8px 0 0; color: var(--text-secondary); font-size: 12px; line-height: 1.5; }
#borrower-registration-form .registration-submit { width: 100%; min-height: 48px; justify-content: center; gap: 8px; color: #fff; background: var(--primary-action); border-color: var(--primary-action); }
#borrower-registration-form .registration-submit:hover { background: var(--primary-action-hover); border-color: var(--primary-action-hover); }
.registration-signin { margin: 18px 0 0; text-align: center; color: var(--text-muted); font-size: 13px; }
.registration-page :is(button, input, select, a):focus-visible { outline: 2px solid var(--interactive); outline-offset: 3px; }
@media (max-width: 600px) {
    .registration-card { padding: 18px; }
    .registration-account-options { grid-template-columns: minmax(0, 1fr); }
    .registration-field, .registration-type-selector { grid-template-columns: minmax(0, 1fr); gap: 7px; }
    .registration-field > label { padding-top: 0; }
    .registration-fields, .registration-fields fieldset { gap: 18px; }
}
</style>
