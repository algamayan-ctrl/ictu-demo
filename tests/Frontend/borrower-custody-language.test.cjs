const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const custodyDetail = fs.readFileSync(path.join(root, 'resources/views/custody/show.blade.php'), 'utf8');
const custodyModel = fs.readFileSync(path.join(root, 'app/Models/CustodyTransaction.php'), 'utf8');

test('borrower custody pages use direct return language', () => {
  assert.match(custodyDetail, /'Return the linen'/);
  assert.match(custodyDetail, /'Bring the linen and the printed Laundry Form to the Laundry Area by the return date\.'/);
  assert.match(custodyDetail, /\$borrowerCustodyLabel = \$custody->status === 'OVERDUE' \? 'Overdue' : 'On Custody';/);
  assert.match(custodyDetail, /'Your borrowed items'/);
  assert.match(custodyDetail, /borrower-items-table \{\{ \$borrowerReleased \? 'is-custody-summary' : '' \}\}/);
  assert.match(custodyDetail, />Quantity summary<\/th>/);
  assert.match(custodyDetail, />Released<\/small>/);
  assert.match(custodyDetail, />Returned<\/small>/);
  assert.match(custodyDetail, /\$borrowerAccountabilityIndicator = \$custody->activeAccountabilityIndicator\(\);/);
  assert.match(custodyDetail, /@if\(\$borrowerAccountabilitySummary\)/);
  assert.match(custodyDetail, /View My Obligations/);
  assert.doesNotMatch(custodyDetail, />Given to you<\/th>/);
  assert.match(custodyDetail, /Borrowing Documents/);
  assert.match(custodyDetail, /Forms and records for this borrowing/);
  assert.doesNotMatch(custodyDetail, /<p class="eyebrow">Your borrowing<\/p>/);
  assert.match(custodyModel, /return \$this->workflowStatus\(\);/);
});
