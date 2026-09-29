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

test('Dompdf first-row percent hints lock both grids instead of equalizing absolute widths', () => {
  const header = (grid) => method.match(new RegExp(`<table class="${grid}">[\\s\\S]*?<\\/thead>`))?.[0] || '';
  const values = html => [...html.matchAll(/<th(?: class="[^"]+")? style="width: (\d+\.\d+)%;">/g)]
    .map(m => Number(m[1]));
  const item = values(header('borrower-items'));
  const signature = values(header('borrower-signatures'));
  assert.equal(item.length, 10, 'all item-grid first-row cells must have proportional widths');
  assert.equal(signature.length, 9, 'all signature-grid first-row cells must have proportional widths');
  const near = (a, b) => assert.ok(Math.abs(a - b) < 0.00001, `${a} should match ${b}`);
  near(item.reduce((sum, val) => sum + val, 0), 100);
  near(signature.reduce((sum, val) => sum + val, 0), 100);
  const sections = arr => [arr.slice(0, 5).reduce((a, b) => a + b, 0), arr[5], arr.slice(6).reduce((a, b) => a + b, 0)];
  const signatureSections = [signature.slice(0,5).reduce((a,b)=>a+b,0), signature[5], signature.slice(6).reduce((a,b)=>a+b,0)];
  sections(item).forEach((v, i) => near(v, signatureSections[i]));
  [453, 32, 324].forEach((pt, i) => near(sections(item)[i], pt / 809 * 100));
  assert.doesNotMatch(header('borrower-items'), /style="width: \d+pt;/);
  assert.doesNotMatch(header('borrower-signatures'), /style="width: \d+pt;/);
});

test('CSS first-row width rules use percentages and break long text within both fixed grids', () => {
  const getRules = grid => {
    const start = method.indexOf(`/* Dompdf can ignore absolute point widths`);
    const end = method.indexOf(`.borrower-items .c-qty`, start);
    const sigStart = method.indexOf(`.borrower-signatures thead th:nth-child(1)`, end);
    const sigEnd = method.indexOf(`.borrower-signatures .sig-row-label`, sigStart);
    return grid === 'borrower-items' ? method.slice(start, end) : method.slice(sigStart, sigEnd);
  };
  for (const grid of ['borrower-items', 'borrower-signatures']) {
    const rules = getRules(grid);
    assert.match(rules, /thead th:nth-child\(1\).*?\{ width: [\d.]+%; \}/);
    assert.doesNotMatch(rules, /width: [\d.]+%pt;/);
    assert.doesNotMatch(rules, /thead th:nth-child\(\d+\).*?\{ width: [\d.]+pt; \}/);
    const cellRule = method.slice(method.indexOf(`.${grid} th, .${grid} td {`), method.indexOf("word-break:", method.indexOf(`.${grid} th, .${grid} td {`)) + 30);
    assert.match(cellRule, /word-break: break-word;/);
  }
});
