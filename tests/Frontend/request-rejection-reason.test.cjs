const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const requestDetail = fs.readFileSync(
  path.join(root, 'resources/views/requests/show.blade.php'),
  'utf8',
);

test('rejection reasons use a quiet decision note rather than an information callout', () => {
  assert.match(requestDetail, /class="content-area request-rejection-area"/);
  assert.match(requestDetail, /class="request-rejection-note" role="note"/);
  assert.match(requestDetail, /\.request-rejection-note \{[\s\S]*background: var\(--surface-elevated\);[\s\S]*border-left: 3px solid var\(--danger\);/);
  assert.doesNotMatch(
    requestDetail,
    /@if\(\$borrowingRequest->status === App\\Enums\\RequestStatus::Rejected[\s\S]*?class="callout danger"/,
  );
});
