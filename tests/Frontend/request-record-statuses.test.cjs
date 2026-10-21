const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const index = fs.readFileSync(path.join(root, 'resources/views/requests/index.blade.php'), 'utf8');
const borrowerRow = fs.readFileSync(path.join(root, 'resources/views/requests/partials/my-requests-row.blade.php'), 'utf8');
const borrowerToolbar = fs.readFileSync(path.join(root, 'resources/views/requests/partials/my-requests-toolbar.blade.php'), 'utf8');
const borrowerScripts = fs.readFileSync(path.join(root, 'resources/views/requests/partials/my-requests-scripts.blade.php'), 'utf8');
const supportedStart = index.indexOf('$supportedStatusLabels = [');
const supportedEnd = index.indexOf('];', supportedStart);
const supportedStatuses = index.slice(supportedStart, supportedEnd);

test('Request Records uses the requested short operational labels', () => {
  assert.match(index, /'BORROWED' => \[\s*'key' => 'ON_CUSTODY',\s*'label' => 'On Custody'/);
  assert.match(index, /'PICKUP_EXPIRED' => \['key' => 'MISSED_PICKUP', 'label' => 'Missed Pickup'/);
  assert.match(index, /'PICKUP_SCHEDULING', 'ITEM_PREPARATION', 'PREPARING_RELEASE' => \[\s*'key' => 'FOR_ISSUANCE',\s*'label' => 'For Issuance'/);
  assert.match(index, /'REJECTED' => \['key' => 'REJECTED', 'label' => 'Rejected'/);
});

test('Request Records status filters stay role-specific and omit duplicate or legacy labels', () => {
  for (const label of [
    "'DRAFT' => 'Draft'",
    "'FOR_REVIEW' => 'For Review'",
    "'FOR_APPROVAL' => 'For Approval'",
    "'FOR_VERIFICATION' => 'For Verification'",
    "'FOR_ISSUANCE' => 'For Issuance'",
    "'MISSED_PICKUP' => 'Missed Pickup'",
    "'RETURNED_LATE' => 'Returned Late'",
    "'SLDDP' => 'SLDDP'",
    "'ACCOUNTABILITY_REVIEW' => 'Accountability Review'",
  ]) {
    assert.match(supportedStatuses, new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }

  for (const removed of ['Submitted', 'Return Processing', 'Borrower Cleared', 'Accountability Pending', 'Legacy']) {
    assert.doesNotMatch(supportedStatuses, new RegExp(removed));
  }
});

test('Borrower Request Records use the same short status set and filter by the displayed status', () => {
  for (const label of ['Draft', 'For Review', 'Needs Revision', 'For Issuance', 'Missed Pickup', 'On Custody', 'Rejected']) {
    assert.match(borrowerToolbar, new RegExp(`>${label}<`));
  }

  assert.match(borrowerRow, /'BORROWED' => \['ON_CUSTODY', 'On Custody'\]/);
  assert.match(borrowerRow, /'PICKUP_EXPIRED' => \['MISSED_PICKUP', 'Missed Pickup'\]/);
  assert.match(borrowerRow, /data-status="\{\{ \$statusKey \}\}"/);
  assert.match(borrowerScripts, /card\.dataset\.status === status/);
  assert.doesNotMatch(borrowerToolbar, /Submitted|Return Processing|Borrower Cleared|Legacy/);
});
