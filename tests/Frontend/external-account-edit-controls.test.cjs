const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/administration/users/form.blade.php'), 'utf8');
const script = source.match(/<script>\s*(document\.addEventListener\('DOMContentLoaded', \(\) => \{[\s\S]*?)<\/script>/)?.[1];
assert.ok(script, 'Run the actual administration controls script.');

function externalControls(confirmResult) {
    const input = { type: 'password' };
    const eye = { hidden: false };
    const eyeOff = { hidden: true };
    const toggle = {
        dataset: { passwordTarget: 'ictu-new-password' }, attributes: {}, events: {},
        classList: { toggle() {} },
        querySelector(selector) { return selector === '.ictu-password-eye' ? eye : eyeOff; },
        addEventListener(name, handler) { this.events[name] = handler; },
        setAttribute(name, value) { this.attributes[name] = value; },
    };
    const deleteButton = { events: {}, addEventListener(name, handler) { this.events[name] = handler; } };
    const nodes = { 'ictu-new-password': input, 'ictu-delete-pending-registration-button': deleteButton };
    let prompts = 0;
    vm.runInNewContext(script, {
        document: {
            getElementById(id) { return nodes[id] || null; },
            querySelectorAll(selector) { return selector === '[data-password-toggle]' ? [toggle] : []; },
            addEventListener(name, handler) { if (name === 'DOMContentLoaded') handler(); },
        },
        window: { confirm() { prompts++; return confirmResult; } },
    });
    return { input, toggle, eye, eyeOff, deleteButton, prompts: () => prompts };
}

test('external password visibility still works when employee division/unit controls are absent', () => {
    const controls = externalControls(false);
    assert.equal(typeof controls.toggle.events.click, 'function');
    controls.toggle.events.click();
    assert.equal(controls.input.type, 'text');
    assert.equal(controls.toggle.attributes['aria-label'], 'Hide password');
    assert.equal(controls.eye.hidden, true);
    assert.equal(controls.eyeOff.hidden, false);
    controls.toggle.events.click();
    assert.equal(controls.input.type, 'password');
    assert.equal(controls.toggle.attributes['aria-label'], 'Show password');
});

test('existing pending-delete confirmation remains cancellable without employee controls', () => {
    const controls = externalControls(false);
    let prevented = false;
    assert.equal(typeof controls.deleteButton.events.click, 'function');
    controls.deleteButton.events.click({ preventDefault() { prevented = true; } });
    assert.equal(controls.prompts(), 1);
    assert.equal(prevented, true);
});

test('existing pending-delete confirmation can proceed after confirmation', () => {
    const controls = externalControls(true);
    let prevented = false;
    assert.equal(typeof controls.deleteButton.events.click, 'function');
    controls.deleteButton.events.click({ preventDefault() { prevented = true; } });
    assert.equal(controls.prompts(), 1);
    assert.equal(prevented, false);
});

const rulesScript = source.match(/    const rules = \{[\s\S]*?\n    \};/)?.[0];
assert.ok(rulesScript, 'Run the actual password guidance rules.');
const passwordAccepted = vm.runInNewContext(`${rulesScript}\n(value) => Object.values(rules).every(rule => rule.valid(value))`);

test('password guidance accepts the existing server minimum without inventing mixed-case requirements', () => {
    assert.equal(passwordAccepted('abcdefghij1!'), true);
});

test('password guidance rejects eleven characters even when all character groups exist', () => {
    assert.equal(passwordAccepted('Abcdefghi1!'), false);
});

test('long server-valid passwords are accepted and inputs do not impose an invented maximum', () => {
    assert.equal(passwordAccepted('Long-External-Password-2026!'), true);
    assert.doesNotMatch(source, /(?:passwordInput|confirmInput)\.maxLength\s*=\s*12/);
});

test('Unicode letters follow the existing server letter rule', () => {
    assert.equal(passwordAccepted('éééééééééé1!'), true);
});
