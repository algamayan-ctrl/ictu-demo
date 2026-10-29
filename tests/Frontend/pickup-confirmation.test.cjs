const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/custody/partials/pickup-confirmation.blade.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];
function element() {
    const handlers = new Map();
    return {disabled: false, focused: false, handlers,
        addEventListener(event, fn) { handlers.set(event, [...(handlers.get(event) || []), fn]); },
        dispatch(event, payload = {}) { for (const fn of handlers.get(event) || []) fn(payload); },
        focus() { this.focused = true; },
    };
}
function mount() {
    const open = element(), back = element(), save = element(), form = element(), dialog = element(), window = element();
    const confirmation = {value: '0'};
    const posts = [];
    dialog.open = false;
    dialog.showModal = () => { dialog.open = true; };
    dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
    form.elements = {namedItem: () => confirmation};
    form.requestSubmit = () => {
        let prevented = false;
        form.dispatch('submit', {preventDefault() { prevented = true; }});
        if (!prevented) posts.push({confirm_pickup: confirmation.value});
    };
    const controls = {'[data-pickup-confirmation-form]': form, '[data-pickup-confirmation-dialog]': dialog,
        '[data-pickup-confirmation-open]': open, '[data-pickup-confirmation-back]': back, '[data-pickup-confirmation-save]': save};
    const workspace = {dataset: {}, querySelector: selector => controls[selector]};
    const context = {document: {querySelector: () => workspace}, window};
    vm.runInNewContext(script, context);
    return {open, back, save, form, dialog, window, confirmation, posts, context};
}

test('opening the pickup dialog performs no submission and focuses Go Back', () => {
    const ui = mount();
    ui.open.dispatch('click');
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.back.focused, true);
    assert.equal(ui.confirmation.value, '0');
    assert.equal(ui.posts.length, 0);
});
test('going back or closing with Escape clears confirmation and returns focus without a POST', () => {
    const ui = mount();
    ui.open.dispatch('click'); ui.back.dispatch('click');
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.open.focused, true);
    ui.open.dispatch('click'); ui.dialog.close();
    assert.equal(ui.confirmation.value, '0');
    assert.equal(ui.posts.length, 0);
});
test('form submission without confirmation opens the dialog instead of sending a request', () => {
    const ui = mount();
    ui.form.requestSubmit();
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.posts.length, 0);
});
test('the final confirmation sends one explicit pickup confirmation and blocks duplicate clicks', () => {
    const ui = mount();
    ui.open.dispatch('click'); ui.save.dispatch('click'); ui.save.dispatch('click');
    assert.deepEqual(ui.posts, [{confirm_pickup: '1'}]);
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.open.disabled, true);
    assert.equal(ui.save.disabled, true);
});
test('back-forward restoration resets consent and initialization remains idempotent', () => {
    const ui = mount();
    ui.open.dispatch('click'); ui.save.dispatch('click');
    ui.window.dispatch('pageshow');
    assert.equal(ui.confirmation.value, '0');
    assert.equal(ui.open.disabled, false);
    assert.equal(ui.save.disabled, false);
    vm.runInNewContext(script, ui.context);
    assert.equal(ui.open.handlers.get('click').length, 1);
    ui.form.requestSubmit();
    assert.equal(ui.posts.length, 1);
    assert.equal(ui.dialog.open, true);
});
