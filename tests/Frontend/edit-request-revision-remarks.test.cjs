const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(
    path.join(__dirname, '../../resources/views/requests/form.blade.php'),
    'utf8'
);

function cssRule(selector) {
    const match = source.match(
        new RegExp(`${selector.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&')}\\s*\\{([\\s\\S]*?)\\n    \\}`)
    );

    assert.ok(match, `Missing ${selector} styles`);

    return match[1];
}

test('returned revision remarks are compact read-only content rather than a field-like box', () => {
    const remarks = cssRule('.returned-for-revision-panel .revision-remarks');
    const copy = cssRule('.returned-for-revision-panel .revision-remarks-copy');

    assert.doesNotMatch(remarks, /(?:background|border|padding)\s*:/);
    assert.doesNotMatch(remarks, /min-height\s*:/);
    assert.match(copy, /white-space:\s*pre-wrap/);
    assert.match(copy, /overflow-wrap:\s*anywhere/);
});

test('the return metadata follows the compact remarks block directly', () => {
    assert.match(
        source,
        /<section class="revision-remarks"[\s\S]*?<\/section>\s*@if\(\$returnedByRole && \$returnedAt\)\s*<p class="revision-return-meta">Returned by:/
    );
});

test('remark text does not preserve Blade indentation as empty space', () => {
    assert.match(
        source,
        /<p class="revision-remarks-copy">\{\{ \$returnRemarks !== ''/
    );
});
