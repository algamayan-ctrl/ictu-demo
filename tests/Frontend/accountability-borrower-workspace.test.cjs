const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const workspace = fs.readFileSync(path.join(root, 'resources/views/accountability/borrower-workspace.blade.php'), 'utf8');
const resolvedHistory = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/resolved-history.blade.php'), 'utf8');

test('borrower Accountability workspace stays grouped and does not route normal case actions back to the old expandable table', () => {
  assert.match(workspace, /groupBy\(fn \(\$record\) => \$record->custody_transaction_id/);
  assert.match(workspace, /Waiting On/);
  assert.match(workspace, /Next Required Step/);
  assert.doesNotMatch(workspace, /data-case-toggle/);
  assert.doesNotMatch(workspace, /route\('accountability\.index', \['borrower'/);
  assert.doesNotMatch(workspace, /View Case/);
});

test('role-owned Accountability actions use focused controls instead of forward-arrow navigation', () => {
  assert.match(workspace, /data-accountability-dialog/);
  assert.match(workspace, />Review Assessment</);
  assert.match(workspace, /Confirm \/ Clear/);
  assert.match(workspace, />Record Verification</);
  assert.match(workspace, />Record Receipt</);
  assert.match(workspace, />Resolve Case</);
  assert.doesNotMatch(workspace, /→/);
});

test('workspace uses compact readable sizing and wraps long status/action text', () => {
  assert.match(workspace, /font-size: clamp\(24px, 1\.9vw, 29px\)/);
  assert.match(workspace, /min-height: 108px/);
  assert.match(workspace, /overflow-wrap: anywhere/);
  assert.match(workspace, /white-space: normal/);
});


test('documents tab uses its own left-aligned compact list instead of dashboard queue alignment', () => {
  assert.match(workspace, /accountability-document-list/);
  assert.match(workspace, /accountability-document-row/);
  assert.match(workspace, /accountability-document-copy/);
  assert.match(workspace, /text-align: left/);
  assert.doesNotMatch(workspace, /class="dashboard-primary-queue-row" style="grid-template-columns: 34px/);
});

test('embedded borrower history suppresses the duplicate resolved-accountability heading', () => {
  assert.match(workspace, /'embeddedInBorrowerWorkspace' => true/);
  assert.match(resolvedHistory, /@unless\(\$embeddedInBorrowerWorkspace\)/);
  assert.match(resolvedHistory, /accountability-history-embedded/);
});


test('staff borrower History loads the shared resolved-row interaction script', () => {
  assert.match(workspace, /@include\('accountability\.partials\.cases-interactions'\)/);
});


test('staff borrower accountability summary cards are clickable and Active Cases includes standalone open matters', () => {
  assert.equal((workspace.match(/class="card stat-card kpi-card dashboard-kpi-card stat-card-link ui-pressable/g) || []).length, 3);
  assert.match(workspace, /id="accountability-active-cases"/);
  assert.match(workspace, /\$standaloneRows = collect\(\$rows\)[\s\S]*?whereIn\('category', \['billing', 'restriction'\]\)/);
  assert.match(workspace, /\$custodyGroups->isEmpty\(\) && \$standaloneRows->isEmpty\(\)/);
  assert.match(workspace, /accountability-standalone-groups/);
  assert.match(workspace, /Borrower action pending/);
  assert.match(workspace, /SPMU \/ policy processing/);
});


test('standalone accountability disclosures stay anchored and use a compact separate detail panel', () => {
  assert.match(workspace, /data-accountability-standalone-toggle/);
  assert.match(workspace, /aria-controls="\{\{ \$standaloneDetailId \}\}"/);
  assert.match(workspace, /class="accountability-standalone-detail-body" hidden/);
  assert.match(workspace, /accountability-standalone-toggle \{ justify-self: end; min-width: 118px; \}/);
  assert.match(workspace, /accountability-standalone-detail-body\[hidden\] \{ display: none; \}/);
  assert.doesNotMatch(workspace, /<details class="accountability-standalone-details">/);
  assert.match(workspace, /class="accountability-custody-groups accountability-standalone-groups"[\s\S]*?class="ob-case-card accountability-custody-case accountability-standalone-case"/);
});

test('resolved accountability expansion stays compact and omits unavailable late-return facts', () => {
  const detail = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/resolved-history-detail.blade.php'), 'utf8');
  assert.match(detail, /class="resolved-history-facts"/);
  assert.match(detail, /@if\(\$case->actual_return_date\)/);
  assert.match(detail, /@if\(\$case->late_days !== null\)/);
  assert.doesNotMatch(detail, /class="accountability-case-facts top-gap"/);
  assert.match(resolvedHistory, /\.resolved-history-facts\s*\{[\s\S]*?grid-template-columns:\s*repeat\(auto-fit, minmax\(170px, 1fr\)\)/);
  assert.match(resolvedHistory, /\.accountability-history-embedded \.accountability-case-detail-row > td\s*\{[\s\S]*?padding:\s*10px 16px 12px/);
});
