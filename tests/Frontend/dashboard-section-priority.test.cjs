const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const dashboard = fs.readFileSync(path.join(root, 'resources/views/dashboard.blade.php'), 'utf8');

test('staff dashboard moves populated operational watch ahead of an empty action queue', () => {
  assert.match(dashboard, /\$operationalWatchFirst\s*=\s*\$showOperationalWatch\s*&&\s*!\s*\$primaryActionHasRecords/);
  assert.match(dashboard, /dashboard-order-secondary'\s*:\s*'dashboard-order-primary'/);
  assert.match(dashboard, /dashboard-order-primary'\s*:\s*'dashboard-order-secondary'/);
  assert.match(dashboard, /data-dashboard-section="primary-action"/);
  assert.match(dashboard, /data-dashboard-section="operational-watch"/);
});

test('borrower dashboard moves current transactions ahead only when the action panel is empty', () => {
  assert.match(dashboard, /\$borrowerCurrentFirst\s*=\s*\$dashboardMode\s*===\s*'BORROWER'[\s\S]*!\s*\$primaryActionHasRecords[\s\S]*&&\s*\$borrowerCurrentHasRecords/);
  assert.match(dashboard, /borrower-dashboard-priority-stack/);
  assert.match(dashboard, /borrower-current-first/);
  assert.match(dashboard, /data-dashboard-section="borrower-current"/);
});

test('actionable populated content keeps the normal business-priority order', () => {
  assert.match(dashboard, /\$primaryActionHasRecords\s*=\s*\$queue->isNotEmpty\(\)/);
  assert.match(dashboard, /\$borrowerRestrictionActions->isNotEmpty\(\)/);
  assert.match(dashboard, /> \.dashboard-order-primary \{ order:1; \}/);
  assert.match(dashboard, /> \.dashboard-order-secondary \{ order:2; \}/);
});


test('AO and Head operational transaction rows stay top-aligned inside tall paired panels', () => {
  assert.match(dashboard, /> \.queue-card > \.queue-list,\s*\n\s*\.dashboard-balanced-grid\.dashboard-two-panel > \.queue-card > \.dashboard-watch-list \{/);
  assert.match(dashboard, /align-content:start;/);
  assert.match(dashboard, /grid-auto-rows:max-content;/);
});
