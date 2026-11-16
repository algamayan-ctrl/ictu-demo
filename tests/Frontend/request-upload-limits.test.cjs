const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const uploadSource = fs.readFileSync(path.resolve(__dirname, '../../public/js/request-upload-limits.js'), 'utf8');
const context = {module: {exports: {}}};
vm.runInNewContext(uploadSource, context);
const {uploadMessage} = context.module.exports;
const MB = 1024 * 1024;
let JSDOM;
try { ({JSDOM} = require(process.env.REQUEST_UPLOAD_JSDOM || 'jsdom')); } catch {}

test('configured file limits accept the exact boundary and reject the next byte', () => {
    for (const limit of [1, 5, 10, 64]) {
        assert.equal(uploadMessage([{size: limit * MB}], limit * MB, 'Request Letter'), '');
        assert.match(uploadMessage([{size: limit * MB + 1}], limit * MB, 'Request Letter'), new RegExp(`${limit} MB`));
    }
    assert.equal(uploadMessage([], 5 * MB, 'Request Letter'), '');
});

test('limits apply per file rather than imposing the old eight MB total', () => {
    assert.equal(uploadMessage([{size: 8 * MB}], 10 * MB, 'Request Letter'), '');
    assert.equal(uploadMessage([{size: 8 * MB}], 10 * MB, 'PTC'), '');
    assert.match(uploadMessage([{size: 5 * MB + 1}], 5 * MB, 'PTC'), /PTC must be 5 MB or smaller/);
});

function page(t, limit = 5) {
    const dom = new JSDOM(`<form id="request-form"><textarea name="event_details">Keep my event details</textarea>
        <input type="file" name="approved_request_letter" data-request-upload-limit="${limit * MB}" data-upload-label="Request Letter" aria-describedby="letter-error">
        <small id="letter-error" hidden></small>
        <input type="file" name="permission_to_conduct_letter" data-request-upload-limit="${limit * MB}" data-upload-label="PTC" aria-describedby="ptc-error">
        <small id="ptc-error" hidden></small><button type="submit">Submit</button></form>`, {runScripts: 'outside-only', pretendToBeVisual: true});
    t.after(() => dom.window.close());
    const w = dom.window, d = w.document;
    w.eval(fs.readFileSync(path.resolve(__dirname, '../../public/js/request-upload-limits.js'), 'utf8'));
    d.dispatchEvent(new w.Event('DOMContentLoaded'));
    const inputs = [...d.querySelectorAll('input[type=file]')];
    const choose = (input, size, notify = true) => {
        Object.defineProperty(input, 'files', {configurable: true, value: size === null ? [] : [{name: 'synthetic.pdf', size}]});
        if (notify) input.dispatchEvent(new w.Event('change', {bubbles: true}));
    };
    return {w, d, inputs, choose, form: d.querySelector('form')};
}

test('an oversized selection shows inline guidance and preserves entered details and files', {skip: !JSDOM}, t => {
    const {d, inputs, choose} = page(t);
    choose(inputs[0], 16562834);
    assert.equal(inputs[0].validity.customError, true);
    assert.equal(inputs[0].getAttribute('aria-invalid'), 'true');
    assert.equal(d.getElementById('letter-error').hidden, false);
    assert.match(d.getElementById('letter-error').textContent, /Request Letter must be 5 MB or smaller/);
    assert.equal(d.querySelector('textarea').value, 'Keep my event details');
    assert.equal(inputs[0].files.length, 1);
});

test('replacing or clearing an oversized file clears its error without affecting the other attachment', {skip: !JSDOM}, t => {
    const {d, inputs, choose} = page(t);
    choose(inputs[0], 6 * MB);
    choose(inputs[1], 6 * MB);
    choose(inputs[0], 5 * MB);
    assert.equal(inputs[0].validity.valid, true);
    assert.equal(d.getElementById('letter-error').hidden, true);
    assert.equal(inputs[1].validity.customError, true);
    choose(inputs[1], null);
    assert.equal(inputs[1].validity.valid, true);
    assert.equal(d.getElementById('ptc-error').textContent, '');
});

test('a direct submit with oversized files is blocked before the signing handler', {skip: !JSDOM}, t => {
    const {w, inputs, choose, form} = page(t);
    choose(inputs[1], 6 * MB, false);
    let signingCalls = 0;
    form.addEventListener('submit', () => signingCalls++);
    const event = new w.Event('submit', {bubbles: true, cancelable: true});
    form.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(signingCalls, 0);
    assert.equal(w.document.activeElement, inputs[1]);
});

test('two allowed attachments reach the existing submit handler without automatic submission', {skip: !JSDOM}, t => {
    const {w, inputs, choose, form} = page(t, 10);
    let submissions = 0;
    form.addEventListener('submit', () => submissions++);
    choose(inputs[0], 8 * MB);
    choose(inputs[1], 8 * MB);
    assert.equal(submissions, 0);
    const event = new w.Event('submit', {bubbles: true, cancelable: true});
    form.dispatchEvent(event);
    assert.equal(event.defaultPrevented, false);
    assert.equal(submissions, 1);
});

test('turning student activity off excludes its hidden attachment and restores validation when re-enabled', {skip: !JSDOM}, t => {
    const {w, d, inputs, choose, form} = page(t);
    const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/requests/form.blade.php'), 'utf8');
    const syncFunction = source.match(/function syncStudentFields\(\) \{[\s\S]*?\n    \}/);
    assert.ok(syncFunction);
    const toggle = d.createElement('input');
    toggle.type = 'checkbox';
    const panel = d.createElement('div');
    const sync = w.Function('studentToggle', 'ptcDocumentBox', 'ptcInput', `${syncFunction[0]}; syncStudentFields();`);
    choose(inputs[0], 4 * MB);
    choose(inputs[1], 6 * MB);
    sync(toggle, panel, inputs[1]);
    assert.equal(panel.hidden, true);
    assert.equal(inputs[1].disabled, true);
    assert.equal(inputs[1].validity.valid, true);
    assert.equal(inputs[1].files.length, 1);
    assert.equal(new w.FormData(form).has('permission_to_conduct_letter'), false);
    toggle.checked = true;
    sync(toggle, panel, inputs[1]);
    assert.equal(panel.hidden, false);
    assert.equal(inputs[1].disabled, false);
    assert.equal(inputs[1].validity.customError, true);
});
