const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('the report preview closes its Blade content section so HTML starts with the layout doctype', () => {
    const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/reports/detailed.blade.php'), 'utf8');
    assert.equal((source.match(/@section\('content'\)/g) || []).length, 1);
    assert.equal((source.match(/@endsection\b/g) || []).length, 1);
    assert.ok(source.trimEnd().endsWith('@endsection'), 'all page styles and content must stay inside the layout section');
});
