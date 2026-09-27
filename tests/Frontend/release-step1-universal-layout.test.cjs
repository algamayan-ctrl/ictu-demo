const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '../..');
const pickup = fs.readFileSync(path.join(root, 'resources/views/custody/partials/pickup-schedule-form.blade.php'), 'utf8');
const process = fs.readFileSync(path.join(root, 'resources/views/custody/partials/release-process.blade.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'resources/views/custody/partials/release-process-styles.blade.php'), 'utf8');
const heading = fs.readFileSync(path.join(root, 'resources/views/custody/partials/release-heading.blade.php'), 'utf8');

const tests = [
  ['normal expanded Step 1 does not repeat the pickup schedule card', () => {
    assert(!pickup.includes('release-schedule-suggestion'));
    assert(!pickup.includes('<strong>Pickup &amp; Issuance Schedule</strong>'));
    assert(!pickup.includes('<strong>System Pickup &amp; Issuance Schedule</strong>'));
  }],
  ['borrower notification uses a neutral detail row instead of a colored success panel', () => {
    assert(pickup.includes('release-schedule-detail-label">Borrower notification'));
    assert(!pickup.includes('notice success compact'));
    assert(!pickup.includes('✓ Borrower notified'));
  }],
  ['schedule exceptions use the same neutral section treatment', () => {
    assert(pickup.includes('release-schedule-exception'));
    assert(!pickup.includes('notice warning compact release-pickup-exception-actions'));
  }],
  ['the top status card remains the single place showing the routine pickup date and time', () => {
    // The numbered "Pickup & Issuance Schedule" step (a duplicate of this
    // same chip) was removed; the release page now only repeats the
    // schedule when it genuinely needs the AO's attention.
    assert(heading.includes("scheduled_release_at?->format('M j, Y')"));
    assert(!process.includes('release-step-schedule'));
    assert(!process.includes("scheduled_release_at->format('M j, Y')"));
  }],
  ['no collapsible step-toggle chrome remains on the release page', () => {
    assert(!process.includes('data-release-panel-toggle'));
    assert(!process.includes('release-step-toggle'));
  }],
  ['neutral schedule styles contain no info or success backgrounds', () => {
    assert(styles.includes('.release-schedule-detail-row'));
    assert(styles.includes('.release-schedule-exception'));
    assert(!styles.includes('.release-schedule-suggestion {'));
  }],
];

let passed = 0;
for (const [name, fn] of tests) {
  try { fn(); passed++; console.log(`PASS ${name}`); }
  catch (err) { console.error(`FAIL ${name}`); console.error(err.message); process.exitCode = 1; }
}
console.log(`${passed}/${tests.length} passed`);
