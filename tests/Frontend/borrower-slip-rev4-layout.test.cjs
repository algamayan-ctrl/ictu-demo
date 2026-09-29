const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../app/Services/DocumentService.php'), 'utf8');
const start = source.indexOf('private function borrowerSlipHtml(');
const end = source.indexOf('public function conditionalForm(', start);
const method = source.slice(start, end);

test('Borrower Slip Rev. 4 uses fixed repeating header and footer in A4 landscape', () => {
  assert.match(method, /@page \{ size: A4 landscape;/);
  assert.match(method, /\.borrower-page-header\s*\{[\s\S]*?position:\s*fixed;/);
  assert.match(method, /\.borrower-page-footer\s*\{[\s\S]*?position:\s*fixed;/);
  assert.match(method, /borrower-blue-rule/);
  assert.match(method, /Effectivity Date/);
  assert.match(method, /Rev\. 4/);
});

test('borrower and supply tables are visually separated by a borderless gap', () => {
  assert.match(method, /class=\\?"split-gap\\?"/);
  assert.match(method, /\.borrower-items \.col-gap, \.borrower-items \.split-gap/);
  assert.doesNotMatch(method, /borrower-items-shell/);
});

test('signature groups remain visually separated without nested signature tables', () => {
  assert.match(method, /signature-gap/);
  assert.match(method, /sig-gap-col/);
  assert.doesNotMatch(method, /borrower-signature-shell/);
});

test('blank rows scale down from five for one item as request size grows', () => {
  assert.match(method, /\$baseFillerRows = match \(true\)/);
  assert.match(method, /\$itemCount === 1 => 5/);
  assert.match(method, /\$itemCount <= 6 => 3/);
  assert.match(method, /\$itemCount <= 12 => 1/);
  assert.match(method, /\$wrapPenalty/);
  assert.match(method, /\$fillerRowCount/);
  assert.doesNotMatch(method, /\$targetVisibleRows/);
});

test('NOTHING FOLLOWS is merged across the full borrower-side terminal row', () => {
  const filler = method.indexOf('for ($i = 0; $i < $fillerRowCount; $i++)');
  const mergedMarker = method.indexOf('<td colspan="5" class="nothing-follows-cell">');
  assert.ok(filler >= 0);
  assert.ok(mergedMarker > filler);
  assert.match(method, /letter-spacing: \.45pt;/);
});

test('continuation pages repeat item headings and do not split closing signatures casually', () => {
  assert.match(method, /\.borrower-items thead \{ display: table-header-group; \}/);
  assert.match(method, /\.borrower-closing-block \{ page-break-inside: avoid; \}/);
});

test('only borrower and approver signatures are digitally prefilled', () => {
  const esignCount = (method.match(/class=\\?"esign\\?"/g) || []).length;
  assert.equal(esignCount, 2);
  assert.doesNotMatch(method, /\$custody->released_at\?->format/);
  assert.doesNotMatch(method, /returnInspectionData\(\$custody\)/);
});

test('item and signature grids retain identical fixed widths and aligned section boundaries', () => {
  const getWidth = (grid, cls) => {
    const match = method.match(new RegExp(`\\.${grid} col\\.${cls} \\{ width: (\\d+)pt; \\}`));
    assert.ok(match, `${grid}/${cls} should have an explicit point width`);
    return Number(match[1]);
  };
  assert.match(method, /\.borrower-items \{ width: 809pt;[^}]*table-layout: fixed;/);
  assert.match(method, /\.borrower-signatures \{ width: 809pt;[^}]*table-layout: fixed;/);
  const upperLeft = ['qty', 'unit', 'desc', 'purpose', 'expected']
    .reduce((sum, cls) => sum + getWidth('borrower-items', `col-${cls}`), 0);
  const lowerLeft = getWidth('borrower-signatures', 'sig-label-col')
    + 4 * getWidth('borrower-signatures', 'sig-left-col');
  const upperGap = getWidth('borrower-items', 'col-gap');
  const lowerGap = getWidth('borrower-signatures', 'sig-gap-col');
  const upperRight = ['release-date', 'release-time', 'return-date', 'remarks']
    .reduce((sum, cls) => sum + getWidth('borrower-items', `col-${cls}`), 0);
  const lowerRight = 3 * getWidth('borrower-signatures', 'sig-right-col');
  assert.deepEqual([upperLeft, upperGap, upperRight], [453, 32, 324]);
  assert.deepEqual([lowerLeft, lowerGap, lowerRight], [upperLeft, upperGap, upperRight]);
  assert.equal(upperLeft + upperGap + upperRight, 809);
});

test('Dompdf first-row cells explicitly lock both grids, not only colgroup', () => {
  const itemHeader = method.match(/<table class="borrower-items">[\s\S]*?<\/thead>/)?.[0] || '';
  const signatureHeader = method.match(/<table class="borrower-signatures">[\s\S]*?<\/thead>/)?.[0] || '';
  const values = html => [...html.matchAll(/<th(?: class="[^"]+")? style="width: (\d+)pt;">/g)].map(m => Number(m[1]));
  assert.deepEqual(values(itemHeader), [43,54,146,125,85,32,77,77,77,93]);
  assert.deepEqual(values(signatureHeader), [89,91,91,91,91,32,108,108,108]);
});
