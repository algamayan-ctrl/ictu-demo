const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../app/Services/DocumentService.php'), 'utf8');
const gatePass = source.slice(source.indexOf('private function gatePassHtml('), source.indexOf('private function laundryFormHtml('));
const header = gatePass.match(/\$pageHeader = <<<HTML([\s\S]*?)HTML;/)?.[1];
const css = gatePass.match(/\$css = <<<CSS([\s\S]*?)CSS;/)?.[1];

test('Gate Pass header uses one fixed, proportional three-column grid', () => {
    assert.ok(header, 'Gate Pass header must be present');
    assert.match(header, /<table class="gp-header-table">\s*<!--[\s\S]*?-->\s*<colgroup><col style="width: 8%;"><col style="width: 72%;"><col style="width: 20%;"><\/colgroup>/);
    assert.match(header, /<td class="gp-logo" style="width: 8%;">\{\$logo\}<\/td>/);
    assert.match(header, /<td class="gp-school" style="width: 72%;">/);
    assert.match(header, /<td class="gp-form-code" style="width: 20%;">\{\$formCode\}<\/td>/);
});

test('Gate Pass CSS locks widths without independently stretching the header', () => {
    assert.ok(css, 'Gate Pass stylesheet must be present');
    assert.match(css, /\.gp-header-table\s*\{[^}]*width:\s*100%;[^}]*table-layout:\s*fixed;/);
    assert.match(css, /\.gp-logo\s*\{[^}]*width:\s*8%;/);
    assert.match(css, /\.gp-school\s*\{[^}]*width:\s*72%;/);
    assert.match(css, /\.gp-form-code\s*\{[^}]*width:\s*20%;/);
    assert.doesNotMatch(css, /\.gp-logo\s*\{[^}]*width:\s*40pt;/);
    assert.doesNotMatch(css, /\.gp-form-code\s*\{[^}]*width:\s*110pt;/);
});

test('Gate Pass continues to use the same header for standalone and inline packet output', () => {
    assert.match(gatePass, /\$inlineHeader = \$documentShell \? '' : '<div class="gp-header-inline">'\.\$pageHeader/);
    assert.match(gatePass, /'<div class="gp-page-header">'\.\$pageHeader/);
});
