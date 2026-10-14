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

test('role-owned Accountability actions preserve focused processing and explicit Head review navigation', () => {
  assert.match(workspace, /accountability-action-button/);
  assert.match(workspace, /\$shouldAutoOpenFocusedAction/);
  assert.match(workspace, /class="accountability-action-panel"/);
  assert.match(workspace, /Review Assessment/);
  assert.match(workspace, /Review Finding/);
  assert.match(workspace, /Record Verification/);
  assert.match(workspace, /Record Receipt/);
  assert.match(workspace, /Finalize Case/);
  assert.match(workspace, /Resolve Accountability/);
  assert.match(workspace, /name="f2f_processing_completed"/);
  assert.doesNotMatch(workspace, /route\('incidents\.rslddp\.upload'/);
  assert.doesNotMatch(workspace, /route\('incidents\.disposition\.record'/);
  assert.match(workspace, /\$statusKey === 'OPEN' && \$isHead \? 'Open Decision →' : \$actionLabel/);
  assert.equal((workspace.match(/→/g) || []).length, 2, 'only View All Cases and Head Open Decision use forward navigation');
});

test('workspace uses compact readable sizing and wraps long status/action text', () => {
  assert.match(workspace, /font-size: clamp\(24px, 1\.9vw, 29px\)/);
  assert.match(workspace, /min-height: 122px/);
  assert.match(workspace, /overflow-wrap: anywhere/);
  assert.match(workspace, /white-space: normal/);
});


test('documents are attached to their accountability case instead of using a standalone Documents tab', () => {
  const relatedDocuments = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/case-documents.blade.php'), 'utf8');
  assert.doesNotMatch(workspace, />Documents<\/a>/);
  assert.doesNotMatch(workspace, /activeTab === 'documents'/);
  assert.match(workspace, /@include\('accountability\.partials\.case-documents'/);
  assert.match(relatedDocuments, /Related Documents/);
  assert.match(relatedDocuments, /route\('documents\.preview', \$document\)/);
  assert.match(relatedDocuments, /accountability-case-record__row/);
});

test('embedded borrower history suppresses the duplicate resolved-accountability heading', () => {
  assert.match(workspace, /'embeddedInBorrowerWorkspace' => true/);
  assert.match(resolvedHistory, /@unless\(\$embeddedInBorrowerWorkspace\)/);
  assert.match(resolvedHistory, /accountability-history-embedded/);
});


test('staff borrower History loads the shared resolved-row interaction script', () => {
  assert.match(workspace, /@include\('accountability\.partials\.cases-interactions'\)/);
});


// The current workspace groups live matters rather than duplicating staff overview KPIs.
test('staff borrower workspace scopes cases and keeps restrictions separate from property matters', () => {
  assert.match(workspace, /id="accountability-active-cases"/);
  assert.match(workspace, /\$standaloneRows = collect\(\$rows\)[\s\S]*?whereIn\('category', \['billing', 'restriction'\]\)/);
  assert.match(workspace, /'status' => collect\(\$rows\)->where\('restricted', true\)/);
  assert.match(workspace, /'open' => collect\(\$rows\)->whereIn\('category', \['property', 'overdue', 'billing'\]\)/);
  assert.match(workspace, /accountability-standalone-groups/);
  assert.match(workspace, /\$verified = \(float\) \$billing->payments->where\('status', 'VERIFIED'\)->sum\('amount'\)/);
});


test('simple standalone details stay visible while complex evidence retains compact controls', () => {
  assert.match(workspace, /data-accountability-standalone-toggle/);
  assert.match(workspace, /aria-controls="\{\{ \$standaloneDetailId \}\}"/);
  assert.match(workspace, /class="accountability-standalone-detail-body" hidden/);
  assert.match(workspace, /accountability-standalone-toggle \{ justify-self: end; min-width: 118px; \}/);
  assert.match(workspace, /\.borrower-accountability-workspace \.accountability-standalone-detail-body \{\s*display: grid !important;/);
  assert.match(workspace, /\.borrower-accountability-workspace \.accountability-standalone-toggle \{\s*display: none !important;/);
  assert.doesNotMatch(workspace, /<details class="accountability-standalone-details">/);
  assert.match(workspace, /class="accountability-custody-groups accountability-standalone-groups"[\s\S]*?class="ob-case-card accountability-custody-case accountability-standalone-case tone-/);
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
