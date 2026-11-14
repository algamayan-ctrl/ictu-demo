const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const requestDetail = fs.readFileSync(path.join(root, 'resources/views/requests/show.blade.php'), 'utf8');

test('borrower request detail uses status-specific helper text', () => {
  assert.match(requestDetail, /\$borrowerHeadingHelper = match \(true\)/);
  assert.match(requestDetail, /@unless\(\$isBorrower\)/);
  assert.match(requestDetail, /Purpose of borrowing:/);
  assert.match(requestDetail, /class="borrower-request-heading-purpose"/);
  assert.match(requestDetail, /class="meta borrower-request-heading-helper"/);
  assert.match(requestDetail, /\$detailStatus === 'BORROWED' => null,/);
  assert.match(requestDetail, /Update the request based on the review remarks\./);
  assert.match(requestDetail, /This request was not approved\. Review the reason below\./);
  assert.match(requestDetail, /Your request is under review\. You will be notified once there is an update\./);
  assert.doesNotMatch(requestDetail, /Review the request, documents, and current progress\./);
  assert.match(requestDetail, /@if\(\$borrowerHeadingHelper\)/);
  assert.match(requestDetail, /<p class="meta borrower-request-heading-helper">\{\{ \$borrowerHeadingHelper \}\}<\/p>/);
});
