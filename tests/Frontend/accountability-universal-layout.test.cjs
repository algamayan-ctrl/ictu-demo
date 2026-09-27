const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const appCss = fs.readFileSync(path.join(root, 'public/css/app.css'), 'utf8');
const index = fs.readFileSync(path.join(root, 'resources/views/accountability/index.blade.php'), 'utf8');
const obligations = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/obligations-workspace.blade.php'), 'utf8');
const obligationStyles = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/obligations-styles.blade.php'), 'utf8');
const oversightStyles = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/oversight-styles.blade.php'), 'utf8');
const resolvedHistory = fs.readFileSync(path.join(root, 'resources/views/accountability/partials/resolved-history.blade.php'), 'utf8');
const notifications = fs.readFileSync(path.join(root, 'resources/views/reports/notifications.blade.php'), 'utf8');
const audit = fs.readFileSync(path.join(root, 'resources/views/reports/audit.blade.php'), 'utf8');
const reportingWorkspaceStyles = fs.readFileSync(path.join(root, 'resources/views/reports/partials/workspace-styles.blade.php'), 'utf8');

// Borrower summary KPIs stay useful at zero: every card drills into its own
// focused result/empty state instead of becoming a static look-alike.
test('My Obligations keeps all four summary cards clickable even at zero or Clear', () => {
  assert.match(obligations, /\['focus' => 'active'\]/);
  assert.match(obligations, /\['focus' => 'action'\]/);
  assert.match(obligations, /\['focus' => 'payment'\]/);
  assert.match(obligations, /\['focus' => 'status'\]/);
  assert.match(obligations, /class="ob-summary-card is-\{\{ \$tone \}\} is-clickable/);
  assert.doesNotMatch(obligations, /ob-summary-card[^\n]*is-static/);
  assert.match(obligations, /\$focusMeta = match \(\$focus\)/);
  assert.match(obligations, /id="current-obligations" class="ob-focused-results"/);
  assert.match(obligationStyles, /\.app-main > \.ob-workspace\s*\{[\s\S]*?flex:\s*0 0 auto/);
  assert.match(obligationStyles, /\.ob-clear-card\s*\{[\s\S]*?min-height:\s*0/);
});

// Resolved history is a drill-in from the KPI, not permanent default-page content.
test('staff resolved history appears only after the Resolved This Period card is opened', () => {
  assert.match(index, /\$resolvedHistoryRequested = ! \$isBorrower && request\('view'\) === 'resolved'/);
  assert.match(index, /\['view' => 'resolved'\]\)\.\'#resolved-history'/);
  assert.match(index, /@if\(\$resolvedHistoryRequested\)[\s\S]*?@include\('accountability\.partials\.resolved-history'/);
  assert.match(index, /greaterThanOrEqualTo\(\$resolvedPeriodStart\)/);
});

// A disclosure must use the same compact, pressable blue-outline contract as
// the approved table actions. A page-local visual override would bring back
// the pale border or dark text that this regression protects against.
test('every inline View Details or Case Details disclosure uses the approved blue-outline contract', () => {
  assert.match(oversightStyles, /\.accountability-cases-table table\s*\{[\s\S]*?width:\s*100%/);
  assert.match(oversightStyles, /\.accountability-cases-table td\s*\{\s*font-size:\s*12\.5px/);
  assert.match(appCss, /\.button\.secondary\s*,\s*\.table-action\s*\{[\s\S]*?color:\s*var\(--interactive\)[\s\S]*?border-color:\s*var\(--interactive\)/);
  const triggerRule = appCss.match(/\.disclosure-trigger\s*\{([^}]*)\}/)?.[1] || '';
  assert.match(triggerRule, /display:\s*inline-flex/);
  assert.match(triggerRule, /align-items:\s*center/);
  assert.match(triggerRule, /gap:\s*7px/);
  assert.match(triggerRule, /min-width:\s*118px/);
  assert.match(appCss, /\.button\.secondary\.disclosure-trigger,\s*\.table-action\.disclosure-trigger\s*\{[\s\S]*?color:\s*var\(--interactive\)[\s\S]*?background:\s*var\(--surface-elevated\)[\s\S]*?border-color:\s*var\(--interactive\)/);
  assert.match(appCss, /\.button\.secondary\.disclosure-trigger:hover,[\s\S]*?color:\s*#fff[\s\S]*?background:\s*var\(--interactive\)/);
  assert.match(appCss, /\.disclosure-trigger\[aria-expanded="true"\][\s\S]*?\.disclosure-trigger__chevron[\s\S]*?rotate\(180deg\)/);
  assert.match(appCss, /details\[open\]\s*>\s*\.disclosure-trigger[\s\S]*?\.disclosure-trigger__chevron[\s\S]*?rotate\(180deg\)/);

  assert.match(resolvedHistory, /class="resolved-history-detail-toggle button secondary small ui-pressable disclosure-trigger"/);
  assert.match(resolvedHistory, /class="button secondary small ui-pressable disclosure-trigger" data-case-toggle/);
  assert.match(obligations, /<summary class="button secondary small ui-pressable disclosure-trigger">\s*<span>View Details<\/span>/);
  assert.equal(
    (index.match(/data-case-toggle/g) || []).length,
    (index.match(/class="button secondary small ui-pressable disclosure-trigger" data-case-toggle/g) || []).length,
    'every active accountability inline expander should use the approved blue-outline stack'
  );
  assert.match(index, /<summary class="button secondary small ui-pressable disclosure-trigger"><span>Case details<\/span>/);
  assert.match(notifications, /class="notification-delivery-view-button button secondary small ui-pressable disclosure-trigger"/);
  assert.match(audit, /<summary class="button secondary small ui-pressable disclosure-trigger">\s*<span>View Details<\/span>/);

  assert.doesNotMatch(oversightStyles, /\.accountability-cases-card \[data-case-toggle\]/);
  assert.doesNotMatch(oversightStyles, /\.accountability-view-detail\s*\{/);
  assert.doesNotMatch(obligationStyles, /\.ob-details > summary\s*\{/);
  assert.match(reportingWorkspaceStyles, /\.reporting-workspace \.button:not\(\.disclosure-trigger\)\s*\{/);
  assert.match(reportingWorkspaceStyles, /\.reporting-workspace \.button\.secondary:not\(\.disclosure-trigger\)\s*\{/);
});
