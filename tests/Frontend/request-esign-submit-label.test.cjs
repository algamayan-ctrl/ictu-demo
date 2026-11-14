const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const viewPath = path.resolve(__dirname, '../../resources/views/requests/form.blade.php');
const view = fs.readFileSync(viewPath, 'utf8');

test('request E-sign submit keeps its original label after confirmation', () => {
    assert.match(view, /id="request-submit-button"[\s\S]*?E-sign &amp; Submit to SPMU/);

    const confirmHandler = view.match(
        /confirmSubmitButton\?\.addEventListener\('click',[\s\S]*?\n\s*}\);\n\s*submitDialog\?\.addEventListener/
    );

    assert.ok(confirmHandler, 'confirmation handler should exist');
    assert.match(confirmHandler[0], /submitButton\.disabled = true;/);
    assert.doesNotMatch(confirmHandler[0], /submitButton\.textContent\s*=\s*['"]Processing\.\.\.['"]/);
});

test('pre-confirmation submit still opts out of the global duplicate-submit lock', () => {
    assert.match(view, /form\.dataset\.allowRepeatedSubmit = 'true';/);
    assert.match(view, /delete form\.dataset\.allowRepeatedSubmit;/);
});

test('acknowledgement is required only for final submission, not for saving a draft', () => {
    const acknowledgement = view.match(
        /id="final-confirmation"[\s\S]*?>/
    );

    assert.ok(acknowledgement, 'acknowledgement checkbox should exist');
    assert.doesNotMatch(acknowledgement[0], /\srequired(?:\s|>)/);
    assert.match(view, /if \(!finalConfirmation\?\.checked\) \{/);
    assert.match(view, /if \(intent !== 'submit'\) \{\s*return;/);
});

test('a returned request requires fresh signed documents and does not preview the old version', () => {
    assert.match(
        view,
        /Upload a new corrected, signed borrowing request letter to submit this revision\./
    );
    assert.match(
        view,
        /@if\(\$requestLetter && ! \$isReturned\)/
    );
    assert.match(
        view,
        /@if\(\$ptc && ! \$isReturned\)/
    );
    assert.match(
        view,
        /\$requestLetterForSubmission = \$isReturned \? null : \$requestLetter;/
    );
});
