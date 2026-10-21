const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const service = fs.readFileSync(
  path.join(root, 'app/Services/SidebarActionCountService.php'),
  'utf8',
);

test('Borrower sidebar badges match the unfiltered My Requests and My Borrowings lists', () => {
  const borrowerCountsStart = service.indexOf('private function borrowerCounts');
  const borrowerCountsEnd = service.indexOf('public function tones', borrowerCountsStart);
  const borrowerCounts = service.slice(borrowerCountsStart, borrowerCountsEnd);

  assert.match(borrowerCounts, /BorrowingRequest::query\(\)[\s\S]*?where\('borrower_user_id', \$userId\)[\s\S]*?->count\(\)/);
  assert.match(borrowerCounts, /CustodyTransaction::query\(\)[\s\S]*?where\('borrower_user_id', \$userId\)[\s\S]*?->count\(\)/);
  assert.doesNotMatch(borrowerCounts, /whereNotIn\('status', \['CLOSED', 'CANCELLED'\]\)/);
  assert.doesNotMatch(borrowerCounts, /RequestStatus::Draft/);
});
