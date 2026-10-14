const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const layout = fs.readFileSync(path.join(root, 'resources/views/layouts/app.blade.php'), 'utf8');

test('SPMU Admin menu keeps Configuration and User Accounts, without the ICTU technical entries', () => {
  const headMenu = layout.match(/AccessClassification::SpmuHead\s*\?\s*\[([\s\S]*?)\]\s*:\s*\[/)[1];
  const labels = [...headMenu.matchAll(/\['[^']+', '[^']+', '([^']+)', '[^']+'/g)].map((match) => match[1]);

  assert.deepEqual(labels.slice(-2), ['Configuration', 'User Accounts']);
  assert.ok(!labels.includes('System Configuration'));
  assert.ok(!labels.includes('Delivery Records'));
  assert.doesNotMatch(headMenu, /'administration\.settings\.index'|'reports\.notifications'/);
});
