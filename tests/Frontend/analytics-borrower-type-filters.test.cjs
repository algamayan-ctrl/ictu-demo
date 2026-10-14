const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const css = fs.readFileSync(path.join(root, 'public/css/app.css'), 'utf8');
const analytics = fs.readFileSync(path.join(root, 'resources/views/analytics/index.blade.php'), 'utf8');

function rule(selector) {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const matches = [...css.matchAll(new RegExp(`(?:^|\\n)\\s*${escaped}\\s*\\{([^}]*)\\}`, 'g'))];
  return matches.map((match) => match[1]).join(';');
}

test('a disabled select draws one chevron, not a tiled row of them', () => {
  /* The disabled `background` shorthand resets repeat/position/size, so the disabled rule must restore them. */
  const disabled = rule('select:disabled');
  assert.match(disabled, /background-image:\s*var\(--select-chevron\)/);
  assert.match(disabled, /background-repeat:\s*no-repeat/);
  assert.match(disabled, /background-position:\s*right 13px center/);
  assert.match(disabled, /background-size:\s*16px 16px/);

  const enabled = rule('select');
  for (const property of ['background-repeat: no-repeat', 'background-position: right 13px center', 'background-size: 16px 16px']) {
    assert.ok(enabled.includes(property), `enabled select keeps ${property}`);
  }
});

test('Analytics keeps exactly four auto-applied filters in the agreed order', () => {
  const form = analytics.match(/<form class="analytics-filters"[\s\S]*?<\/form>/)[0];
  const ids = [...form.matchAll(/<select id="([^"]+)"/g)].map((match) => match[1]);
  assert.deepEqual(ids, ['analytics-period', 'analytics-borrower-type', 'analytics-group', 'analytics-unit']);
  assert.equal((form.match(/onchange="this\.form\.submit\(\)"/g) || []).length, 4);
  assert.doesNotMatch(form, /type="submit"/);
  assert.doesNotMatch(form, /borrower-picker/);
});

test('External Borrower disables Division and Office with a single Not applicable option', () => {
  assert.match(analytics, /<select id="analytics-group"[^>]*@disabled\(\$externalOnly\)/);
  assert.match(analytics, /<select id="analytics-unit"[^>]*@disabled\(\$externalOnly\)/);
  assert.equal((analytics.match(/<option value="all" selected>Not applicable<\/option>/g) || []).length, 2);
});

const demand = fs.readFileSync(path.join(root, 'resources/views/analytics/partials/demand-usage.blade.php'), 'utf8');

test('External Borrower reuses the division donut card, retitled, with only the External row', () => {
  assert.match(demand, /\$externalDemandOnly = \(\$selectedBorrowerType \?\? 'all'\) === 'EXTERNAL';/);
  assert.match(demand, /\$divisionLegend = \$divisionLegend->where\('tone', 'external'\)->values\(\);/);
  assert.match(demand, /\$externalDemandOnly \? 'External Borrower Demand' : 'Demand by Division'/);
  assert.match(demand, /<h2>\{\{ \$divisionCardTitle \}\}<\/h2>/);
  assert.match(demand, /No External Borrower requests during this period\./);
  /* Still one donut card: no second chart or card for External demand. */
  assert.equal((demand.match(/id="demand-by-division"/g) || []).length, 1);
  assert.equal((demand.match(/class="analytics-donut"/g) || []).length, 1);
});
