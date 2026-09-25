const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const layout = read('resources/views/layouts/app.blade.php');
const appCss = read('public/css/app.css');
const requestShow = read('resources/views/requests/show.blade.php');
const cancelControl = read('resources/views/requests/partials/borrower-cancel-control.blade.php');

test('global submit lock never swaps the button label to a transient "Processing..." message', () => {
    assert.doesNotMatch(layout, /Processing\.\.\./);
    assert.doesNotMatch(layout, /originalSubmitHtml|originalSubmitValue/);
});

test('global submit lock disables submit controls immediately while keeping their label', () => {
    const lockForm = layout.match(/function lockForm\(form\) \{[\s\S]*?\n {8}\}/);
    assert.ok(lockForm, 'lockForm() should exist');

    assert.match(lockForm[0], /form\.dataset\.submitLocked = '1';/);
    assert.match(
        lockForm[0],
        /control\.style\.pointerEvents = 'none';[\s\S]*?control\.setAttribute\('aria-disabled', 'true'\);/
    );
});

test('a second real submit while the form is locked is blocked (double-submit prevention)', () => {
    const listener = layout.match(
        /document\.addEventListener\('submit', \(event\) => \{[\s\S]*?\n {8}\}, true\);/
    );
    assert.ok(listener, 'capture-phase submit listener should exist');

    assert.match(
        listener[0],
        /if \(form\.dataset\.submitLocked === '1'\) \{\s*event\.preventDefault\(\);\s*event\.stopImmediatePropagation\(\);/
    );
});

test('a lock that never navigates away (e.g. a dropped connection) auto-releases so the user can retry', () => {
    assert.match(layout, /STUCK_SUBMIT_TIMEOUT_MS/);
    assert.match(
        layout,
        /form\.dataset\.submitLockTimer = String\(window\.setTimeout\(\(\) => \{\s*unlockForm\(form\);\s*\}, STUCK_SUBMIT_TIMEOUT_MS\)\);/
    );
});

test('unlockForm fully restores the locked controls and clears the stuck-submit timer', () => {
    const unlockForm = layout.match(/function unlockForm\(form\) \{[\s\S]*?\n {8}\}/);
    assert.ok(unlockForm, 'unlockForm() should exist');

    assert.match(unlockForm[0], /delete form\.dataset\.submitLocked;/);
    assert.match(unlockForm[0], /window\.clearTimeout\(Number\(form\.dataset\.submitLockTimer\)\);/);
    assert.match(unlockForm[0], /control\.style\.pointerEvents = '';/);
    assert.match(unlockForm[0], /control\.removeAttribute\('aria-disabled'\);/);
});

test('locked (aria-disabled) buttons get the same dimmed look as real :disabled buttons', () => {
    assert.match(
        appCss,
        /\.button:disabled, button:disabled,\s*\n\.button\[aria-disabled="true"\], button\[aria-disabled="true"\] \{ cursor: not-allowed; opacity: \.55; transform: none; \}/
    );
});

test('the AO verification / Head decision dialog re-enables its Confirm button if the request never navigates away', () => {
    const confirmHandler = requestShow.match(
        /confirmSubmit\?\.addEventListener\('click', \(\) => \{[\s\S]*?\n {4}\}\);/
    );
    assert.ok(confirmHandler, 'confirm handler should exist');
    assert.match(confirmHandler[0], /confirmSubmit\.disabled = true;/);
    assert.match(confirmHandler[0], /window\.setTimeout\(\(\) => \{ confirmSubmit\.disabled = false; \}, \d+\);/);
});

test('the borrower cancel-request dialog re-enables its Confirm button if the request never navigates away', () => {
    const submitCancellation = cancelControl.match(
        /const submitCancellation = \(reason\) => \{[\s\S]*?\n {12}\};/
    );
    assert.ok(submitCancellation, 'submitCancellation() should exist');
    assert.match(submitCancellation[0], /confirm\.disabled = true;/);
    assert.match(submitCancellation[0], /window\.setTimeout\(\(\) => \{ confirm\.disabled = false; \}, \d+\);/);
});
