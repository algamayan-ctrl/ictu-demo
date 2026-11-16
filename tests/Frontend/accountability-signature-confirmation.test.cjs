const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
let JSDOM;
try { ({ JSDOM } = require(process.env.ACCOUNTABILITY_SIGNATURE_JSDOM || 'jsdom')); } catch {}
const domTest = (title, body) => test(title, { skip: JSDOM ? false : 'Set ACCOUNTABILITY_SIGNATURE_JSDOM to the installed jsdom module to run interaction checks.' }, body);

const root = path.resolve(__dirname, '../..');
const script = fs.readFileSync(path.join(root, 'public/js/accountability-signature-confirmation.js'), 'utf8');
const partial = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/signature-confirmation.blade.php'), 'utf8');
const layout = fs.readFileSync(path.join(root, 'resources/views/layouts/app.blade.php'), 'utf8');
const lockScript = [...layout.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(code => code.includes('STUCK_SUBMIT_TIMEOUT_MS'));

function fixture() {
    const modal = partial.replace(/\{\{[\s\S]*?\}\}/g, 'Fixture Head').replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/<script[\s\S]*?<\/script>/g, '');
    const form = (id, type, choice = '') => `<form id="${id}" method="post" action="/${id}" data-accountability-signature="${type}" data-signature-case="CASE-${id}" data-signature-amount="PHP 225.00">
        <input type="hidden" name="confirm_e_signature" value="">
        <textarea name="${type === 'billing' ? 'basis' : 'resolution_remarks'}" required>Entered remarks</textarea>${choice}
        <button type="submit">Original action</button></form>`;
    const dom = new JSDOM(form('billing', 'billing') + form('billing-two', 'billing')
        + form('decision', 'decision', '<select name="decision" required><option value="">Select decision</option><option value="CONFIRM">Confirm Accountability</option><option value="CLEAR">Clear Finding</option></select>')
        + form('radio-decision', 'decision', '<input type="radio" name="decision" value="CONFIRM" required checked><input type="radio" name="decision" value="CLEAR" required>')
        + '<form id="physical" method="post"><button type="submit">Verify Compliance</button></form>' + modal,
        { url: 'http://127.0.0.1:8001/accountability', runScripts: 'outside-only' });
    const { window } = dom;
    const { document } = window;
    const submissions = [];
    window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    window.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new window.Event('close')); };
    window.HTMLFormElement.prototype.requestSubmit = function (submitter) {
        if (!this.reportValidity()) return;
        const event = new window.SubmitEvent('submit', { bubbles: true, cancelable: true, submitter });
        if (this.dispatchEvent(event)) submissions.push({ id: this.id, values: Object.fromEntries(new window.FormData(this)) });
    };
    assert.ok(lockScript, 'exercise the existing application submission lock');
    window.eval(lockScript);
    window.eval(script);
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    const dialog = document.querySelector('dialog');
    const consent = document.querySelector('[data-signature-consent]');
    const confirm = document.querySelector('[data-signature-confirm]');
    const cancel = document.querySelector('[data-signature-cancel]');
    const submit = id => {
        const current = document.getElementById(id);
        const button = current.querySelector('button');
        button.focus(); current.requestSubmit(button);
        return current;
    };
    const approve = () => { consent.checked = true; consent.dispatchEvent(new window.Event('change')); confirm.click(); };
    return { dom, window, document, submissions, dialog, consent, confirm, cancel, submit, approve };
}

domTest('billing shows the actual case and amount with fresh unchecked consent and no early submission lock', () => {
    const f = fixture();
    try {
        const form = f.submit('billing');
        assert.equal(f.dialog.open, true);
        assert.equal(f.dialog.querySelector('[data-signature-case]').textContent, 'CASE-billing');
        assert.equal(f.dialog.querySelector('[data-signature-amount]').textContent, 'PHP 225.00');
        assert.match(f.dialog.querySelector('[data-signature-consent-copy]').textContent, /authorize.*Billing Statement/);
        assert.equal(f.consent.checked, false);
        assert.equal(f.confirm.disabled, true);
        assert.equal(form.dataset.submitLocked, undefined);
        f.confirm.click();
        assert.equal(f.submissions.length, 0);
        f.approve();
        assert.equal(f.submissions.length, 1);
        assert.equal(f.submissions[0].values.confirm_e_signature, '1');
        assert.equal(f.submissions[0].values.basis, 'Entered remarks');
        assert.equal(form.dataset.submitLocked, '1');
        form.requestSubmit(form.querySelector('button'));
        assert.equal(f.submissions.length, 1, 'repeated clicks cannot submit twice');
    } finally { f.dom.window.close(); }
});

for (const exit of ['cancel', 'escape', 'backdrop']) {
    domTest(`${exit} saves nothing, keeps form values and restores the original button focus`, () => {
        const f = fixture();
        try {
            const form = f.submit('billing');
            f.consent.checked = true;
            f.consent.dispatchEvent(new f.window.Event('change'));
            if (exit === 'cancel') f.cancel.click();
            if (exit === 'escape') f.dialog.dispatchEvent(new f.window.Event('cancel', { cancelable: true }));
            if (exit === 'backdrop') f.dialog.click();
            assert.equal(f.dialog.open, false);
            assert.equal(f.submissions.length, 0);
            assert.equal(form.elements.basis.value, 'Entered remarks');
            assert.equal(form.elements.confirm_e_signature.value, '');
            assert.equal(f.document.activeElement, form.querySelector('button'));
            f.submit('billing');
            assert.equal(f.consent.checked, false);
            assert.equal(f.confirm.disabled, true);
        } finally { f.dom.window.close(); }
    });
}

for (const decision of ['CONFIRM', 'CLEAR']) {
    domTest(`${decision} needs a selected decision and confirms the matching action before submission`, () => {
        const f = fixture();
        try {
            f.submit('decision');
            assert.equal(f.dialog.open, false, 'original required validation comes first');
            const form = f.document.getElementById('decision');
            form.elements.decision.value = decision;
            f.submit('decision');
            assert.equal(f.dialog.querySelector('[data-signature-decision]').textContent, decision === 'CLEAR' ? 'Clear Finding' : 'Confirm Accountability');
            assert.equal(f.dialog.querySelector('[data-signature-amount-row]').hidden, true);
            assert.equal(f.confirm.textContent, 'Confirm E-signature & Record');
            f.approve();
            assert.equal(f.submissions.length, 1);
            assert.equal(f.submissions[0].values.decision, decision);
            assert.equal(f.submissions[0].values.confirm_e_signature, '1');
        } finally { f.dom.window.close(); }
    });
}

domTest('legacy radio decision controls retain their original value and use the same confirmation', () => {
    const f = fixture();
    try {
        f.submit('radio-decision');
        assert.equal(f.dialog.querySelector('[data-signature-decision]').textContent, 'Confirm Accountability');
        f.approve();
        assert.equal(f.submissions[0].values.decision, 'CONFIRM');
    } finally { f.dom.window.close(); }
});

domTest('changes to the pending form or another case require fresh consent', () => {
    const f = fixture();
    try {
        const form = f.submit('billing');
        f.consent.checked = true;
        f.consent.dispatchEvent(new f.window.Event('change'));
        form.elements.basis.value = 'Changed assessment';
        f.confirm.click();
        assert.equal(f.submissions.length, 0);
        assert.equal(f.consent.checked, false, 'silent form changes reopen with unchecked consent');
        form.elements.basis.dispatchEvent(new f.window.Event('input', { bubbles: true }));
        assert.equal(f.dialog.open, false);
        f.submit('billing-two');
        assert.equal(f.consent.checked, false);
        assert.equal(f.confirm.disabled, true);
        assert.equal(f.dialog.querySelector('[data-signature-case]').textContent, 'CASE-billing-two');
        f.approve();
        assert.equal(f.submissions[0].id, 'billing-two');
        assert.equal(form.elements.confirm_e_signature.value, '');
    } finally { f.dom.window.close(); }
});

domTest('Tab and Shift+Tab stay inside the modal while consent is unchecked or checked', () => {
    const f = fixture();
    try {
        f.submit('billing');
        f.consent.focus();
        f.consent.dispatchEvent(new f.window.KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
        assert.equal(f.document.activeElement, f.cancel);
        f.cancel.dispatchEvent(new f.window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
        assert.equal(f.document.activeElement, f.consent);
        f.consent.checked = true;
        f.consent.dispatchEvent(new f.window.Event('change'));
        f.confirm.focus();
        f.confirm.dispatchEvent(new f.window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
        assert.equal(f.document.activeElement, f.consent);
    } finally { f.dom.window.close(); }
});

domTest('an unavailable dialog fails closed with a visible retry message', () => {
    const f = fixture();
    try {
        f.dialog.showModal = () => { throw new Error('Unavailable'); };
        f.submit('billing');
        assert.equal(f.submissions.length, 0);
        assert.equal(f.document.querySelector('[data-signature-unavailable]').hidden, false);
        assert.equal(f.document.getElementById('billing').dataset.submitLocked, undefined);
    } finally { f.dom.window.close(); }
});

domTest('physical compliance forms bypass this dialog and retain the existing submission behavior', () => {
    const f = fixture();
    try {
        f.submit('physical');
        assert.equal(f.dialog.open, false);
        assert.equal(f.submissions.length, 1);
        assert.equal(f.submissions[0].id, 'physical');
        assert.equal(f.submissions[0].values.confirm_e_signature, undefined);
    } finally { f.dom.window.close(); }
});

domTest('browser history restoration clears consent and permits a newly confirmed action', () => {
    const f = fixture();
    try {
        f.submit('billing'); f.approve();
        f.window.dispatchEvent(new f.window.PageTransitionEvent('pageshow', { persisted: true }));
        f.submit('billing');
        assert.equal(f.dialog.open, true);
        assert.equal(f.consent.checked, false);
        assert.equal(f.document.getElementById('billing').elements.confirm_e_signature.value, '');
    } finally { f.dom.window.close(); }
});
