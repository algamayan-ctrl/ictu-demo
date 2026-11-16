const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const template = fs.readFileSync(
    path.resolve(__dirname, '../../resources/views/custody/index.blade.php'),
    'utf8',
);

test('SAO return records provide the same usable pagination controls as operational lists', () => {
    assert.match(template, /@if\(\$mode === 'return' && \$custodies->isNotEmpty\(\)\)/);
    assert.match(template, /data-operational-pagination/);
    assert.match(template, /data-operational-page-size/);
    assert.match(template, /data-operational-previous/);
    assert.match(template, /data-operational-next/);
    assert.match(template, /Showing \$\{firstIndex \+ 1\}–\$\{lastIndex\} of \$\{matches\.length\}/);
});

test('return filters and sorting reset to the first page before records are shown', () => {
    assert.match(template, /search\.addEventListener\('input', \(\) => \{ currentPage = 1; render\(\); \}\)/);
    assert.match(template, /status\.addEventListener\('change', \(\) => \{ currentPage = 1; render\(\); \}\)/);
    assert.match(template, /sort\.addEventListener\('change', \(\) => \{ currentPage = 1; render\(\); \}\)/);
    assert.match(template, /pageSize\?\.addEventListener\('change', \(\) => \{ currentPage = 1; render\(\); \}\)/);
});
