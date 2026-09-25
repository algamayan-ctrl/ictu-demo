const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

const tracker = read('resources/views/components/request-progress-tracker.blade.php');
const gatePass = read('resources/views/gate-passes/show.blade.php');

test('completed-stage tooltips use the canonical, concise wording', () => {
    assert.match(tracker, /'Borrowing request created\.'/);
    assert.match(tracker, /'Borrowing request submitted to SPMU\.'/);
    assert.match(tracker, /'Request verified by the SPMU Action Officer\.'/);
    assert.match(tracker, /'Requested items released to the borrower\.'/);
    assert.match(tracker, /'Physical return inspection recorded\.'/);
    assert.match(tracker, /'Transaction completed\.'/);
});

test('SPMU Head Review reflects the actual decision instead of one generic sentence', () => {
    assert.match(tracker, /'Request approved by the SPMU Head \/ Admin\.'/);
    assert.match(tracker, /'Request rejected by the SPMU Head \/ Admin\.'/);
    assert.match(tracker, /'Request returned for revision by the SPMU Head \/ Admin\.'/);
});

test('old multi-sentence / process-explanation tooltip copy was removed', () => {
    assert.doesNotMatch(tracker, /Laundry Personnel issue linen under the printed Laundry Form/);
    assert.doesNotMatch(tracker, /wet-signed Issued by/);
    assert.doesNotMatch(tracker, /Any unresolved post-return accountability is handled in the next stage/);
});

test('Action Officer Verification and SPMU Head Review never show a borrowed timestamp before their own event has happened', () => {
    const aoStep = tracker.match(/'label' => 'Action Officer Verification',[\s\S]*?'time' => ([^,]+),/);
    assert.ok(aoStep, 'Action Officer Verification step should exist');
    assert.doesNotMatch(aoStep[1], /reviewStartedAt/);

    const headStep = tracker.match(/'label' => 'SPMU Head \/ Admin Review',[\s\S]*?'time' => ([\s\S]*?),\n/);
    assert.ok(headStep, 'SPMU Head / Admin Review step should exist');
    assert.doesNotMatch(headStep[1], /reviewStartedAt/);
});

test('Action Officer Verification only appears for off-campus / Gate Pass requests', () => {
    assert.match(
        tracker,
        /\$requiresActionOfficerVerification = \(bool\) \(\$request->currentVersion\?->off_campus\);/
    );
    assert.match(tracker, /if \(\$requiresActionOfficerVerification\) \{\s*\$steps\[\$actionOfficerStepIndex\]/);
});

test('the Gate Pass page tracker reuses the shared tooltip structure instead of showing no date/description at all', () => {
    assert.match(gatePass, /<x-workflow-tracker-interactions \/>/);
    for (const hook of ['data-workflow-step', 'data-workflow-title', 'data-workflow-meta', 'data-workflow-description']) {
        assert.ok(gatePass.includes(hook), `Missing tracker tooltip hook: ${hook}`);
    }
    assert.match(gatePass, /\$formatGatePassStepTime = static fn \(\$value\) => \$value \? \$value->format\('d M Y, g:i A'\) : 'Pending';/);
});
