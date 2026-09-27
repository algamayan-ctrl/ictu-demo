const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../..');
const layout = fs.readFileSync(path.join(root, 'resources/views/layouts/app.blade.php'), 'utf8');

test('shared operational detail typography is defined after page content', () => {
  const contentYield = layout.lastIndexOf("@yield('content')");
  const typography = layout.indexOf('id="universal-detail-typography"');
  assert.ok(typography > contentYield, 'typography override must load after page-specific inline styles');
});

test('transaction detail text uses the compact return-workspace scale', () => {
  assert.match(layout, /--detail-section-title-size:\s*17px/);
  assert.match(layout, /--detail-label-size:\s*12px/);
  assert.match(layout, /--detail-value-size:\s*13px/);
  assert.match(layout, /--detail-meta-size:\s*12px/);

  for (const selector of [
    '.release-flow-page .release-card-title h2',
    '.request-operational-page .request-section-title',
    '.borrower-custody-stack .card-header h2',
    '.laundry-detail h2',
    '.gate-pass-detail-grid .card-header h2',
    '.dashboard-balanced-grid > .queue-card > .card-header h2',
  ]) {
    assert.ok(layout.includes(selector), `missing shared typography selector: ${selector}`);
  }
});

test('KPI figures are not flattened into transaction detail typography', () => {
  const blockStart = layout.indexOf('id="universal-detail-typography"');
  const blockEnd = layout.indexOf('</style>', blockStart);
  const block = layout.slice(blockStart, blockEnd);
  assert.equal(block.includes('.dashboard-kpi-card .kpi-value'), false);
  assert.equal(block.includes('.accountability-overview-value'), false);
});
