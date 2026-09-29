const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../app/Services/DocumentService.php'), 'utf8');
const section = source.slice(source.indexOf('private function laundryFormHtml('), source.indexOf('public function replaceConditionalForm('));
const header = section.match(/\$pageHeader = <<<HTML([\s\S]*?)HTML;/)?.[1] || '';
const css = section.match(/\$css = <<<CSS([\s\S]*?)CSS;/)?.[1] || '';

test('Laundry header locks logo, school and form code in a proportional fixed grid', () => {
  assert.match(header, /<colgroup><col style="width: 8%;"><col style="width: 72%;"><col style="width: 20%;"><\/colgroup>/);
  assert.match(header, /<td class="lf-logo" style="width: 8%;">/);
  assert.match(header, /<td class="lf-school" style="width: 72%;">/);
  assert.match(header, /<td class="lf-form-code" style="width: 20%;">/);
  assert.match(css, /\.lf-logo \{ width: 8%;/);
  assert.match(css, /\.lf-school \{ width: 72%;/);
  assert.match(css, /\.lf-form-code \{ width: 20%;/);
  assert.doesNotMatch(css, /\.lf-form-code \{ width: 110pt;/);
});

test('Standalone laundry title reserves clearance before Requesting Office without double spacing packet pages', () => {
  assert.match(section, /\$requestMetaTopMargin = \$documentShell \? '13pt' : '1pt';/);
  assert.match(css, /\.lf-request-meta \{[^}]*margin: \{\$requestMetaTopMargin\} 0 9pt;/);
  assert.match(section, /\$inlineHeader = \$documentShell \? '' : '<div class="lf-header-inline">'\.\$pageHeader/);
});

test('Laundry item and signature rows keep the original fields', () => {
  assert.match(section, /\$requestingOfficeLabel/);
  assert.match(section, /\$requestedByLabel/);
  assert.match(section, /\$approvedByLabel/);
  assert.match(section, /\$issuedByLabel/);
  assert.match(section, /\$receivedByLabel/);
});
