const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const partial = (name) => read(`resources/views/laundry/partials/completed-${name}.blade.php`);
const page = read('resources/views/laundry/completed.blade.php');
const row = partial('case-row');
const back = partial('back-link');
const pagination = read('resources/views/components/record-table-footer.blade.php');
const filters = read('resources/views/laundry/partials/record-filters.blade.php');
const styles = partial('styles');
test('completed laundry has separate empty and populated states matching the reference', () => {
    assert.match(back, /<span>Back<\/span>/);
    assert.match(page, /<p class="eyebrow">Laundry Operations<\/p>/);
    assert.match(page, /Review completed laundry cases and their final disposition\./);
    assert.match(page, /@if\(\$jobs->total\(\) === 0\)\s+<section class="card completed-laundry-empty"/);
    assert.match(page, /No completed laundry cases yet\./);
    assert.match(page, /Completed laundry cases will appear here after processing is finalized\./);
    assert.match(page, /@include\('laundry\.partials\.completed-empty-illustration'\)/);
    assert.ok(page.indexOf("@include('laundry.partials.record-filters'") < page.indexOf('@if($jobs->total() === 0)'));
    assert.match(page, /<section class="card completed-laundry-card"/);
    assert.equal(page.split("@include('laundry.partials.completed-back-link')").length - 1, 2);
    assert.match(back, /route\('laundry\.index'\)/);
    assert.match(page, /Completed cases are archived for record keeping and inventory management\./);
    assert.doesNotMatch(page, /SCREEN 1|SCREEN 2|No completed internal Laundry/);
});

test('table shows live case identity, borrower, units, dates and the original details route', () => {
    for (const heading of ['Case ID', 'Borrower', 'Items', 'Completed Date', 'Return Result', 'Actions']) {
        assert.ok(page.includes(`<th scope="col">${heading}</th>`));
    }
    assert.match(page, /@foreach\(\$jobs as \$job\)/);
    assert.match(row, /str_pad\(\(string\) \$job->id, 5, '0', STR_PAD_LEFT\)/);
    assert.match(row, /\$job->created_at->format\('Y'\)/);
    assert.match(row, /\$job->custody\?->borrower\?->full_name/);
    assert.match(row, /unit_snapshot/);
    assert.match(row, /\$hasRecordedQuantities\s+\? \$job->lines->groupBy/);
    assert.match(row, /\$job->completed_at \?: \$job->worker_completed_at/);
    assert.match(row, /toIso8601String\(\)/);
    assert.match(row, /route\('laundry\.show', \$job\)/);
    assert.doesNotMatch(row, /LND-2026-00012|Juan Dela Cruz|now\(\)/);
});

test('outcomes reflect recorded quantities, including mixed and unknown results', () => {
    /*
     * The outcome taxonomy is now available / accountability / completed
     * (fallback), reading the same return-line condition codes the return
     * inspection system uses instead of a separate laundry-only issue_type/
     * affected_quantity field, and a late-return check instead of a distinct
     * "unrecorded" state.
     */
    assert.match(row, /\$serviceableQuantity = \(int\) \$job->lines->sum\('completed_quantity'\)/);
    assert.match(row, /flatMap\(fn \(\$line\) => \$line->custodyLine\?->returnLines \?\? collect\(\)\)/);
    assert.match(row, /filter\(fn \(\$condition\) => \$condition !== '' && \$condition !== 'FINE'\)/);
    assert.match(row, /every\(fn \(\$line\) => \$line->received_quantity !== null\)/);
    assert.match(row, /\$hasAccountability = \$hasAdverseFinding \|\| \$isLateReturn;/);
    assert.match(row, /@if\(\$serviceableQuantity > 0\)[\s\S]*?@endif\s+@if\(\$hasAccountability\)/);
    assert.match(row, /\$serviceableQuantity > 0 \? 'available' : null/);
    assert.match(row, /\$hasAccountability \? 'accountability' : null/);
    assert.match(row, /if \(! \$caseOutcomes\) \{\s+\$caseOutcomes = \['completed'\];\s+\}/);
    assert.match(row, /Recorded return findings:/);
    assert.match(filters, /With Return Findings/);
    assert.doesNotMatch(page, /<option value="completed">/);
    assert.doesNotMatch(row, /status === 'LAUNDRY_COMPLETED'[^;]*'available'/);
});

test('numbered pagination preserves server links and disabled boundaries', () => {
    assert.match(page, /<x-record-table-footer :records="\$jobs"/);
    assert.match(pagination, /\$records->previousPageUrl\(\)/);
    assert.match(pagination, /\$records->nextPageUrl\(\)/);
    assert.match(pagination, /aria-current="page"/);
    assert.match(pagination, /aria-disabled="true"/);
    assert.doesNotMatch(pagination, /<a[^>]+aria-disabled|href="#"/);
});

test('completed UI contains no processing forms or workflow mutations', () => {
    assert.doesNotMatch(page + row + back + pagination, /<form|->(?:save|update|delete|create)\(|DB::|FOR_SPMU_FINAL_CHECK|AWAITING_FINAL_FORM_UPLOAD/);
    assert.match(filters, /<form method="get"/);
    assert.doesNotMatch(filters, /method="post"|@csrf|@method/);
    assert.match(filters, />Clear<\/a>/);
    assert.match(page, /role="status" aria-live="polite"/);
});

test('responsive styles stay scoped and use valid theme variables', () => {
    assert.match(styles, /\.completed-laundry \[hidden\] \{ display: none !important; \}/);
    assert.match(styles, /\.completed-laundry-table-wrap \{[^}]*overflow-x: auto/);
    assert.match(styles, /@media \(max-width: 700px\)/);
    assert.match(styles, /@media \(max-width: 430px\)/);
    const theme = read('public/css/app.css');
    // Secondary/view buttons on this page rely on the shared global
    // .button.secondary, .table-action hover contract (public/css/app.css) -
    // this page must not redefine its own local override, and motion
    // reduction is handled once, globally, for every .button/.interactive
    // element rather than per page.
    assert.doesNotMatch(styles, /\.completed-laundry \.button(?:\.ui-pressable)?\.secondary[^\{]*(?::hover|:focus-visible)[^\{]*\{/);
    assert.match(theme, /prefers-reduced-motion:\s*reduce\)\s*\{[\s\S]*?\*,\s*\*::before,\s*\*::after[\s\S]*?transition-duration/);
    const variables = new Set([...`${theme}\n${styles}`.matchAll(/(--[\w-]+)\s*:/g)].map((match) => match[1]));
    for (const [, variable] of styles.matchAll(/var\((--[\w-]+)/g)) {
        assert.ok(variables.has(variable), `Undefined CSS variable: ${variable}`);
    }
});

test('the completed Blade branches remain balanced', () => {
    for (const source of [page, row, pagination]) {
        const markup = source.replace(/@php[\s\S]*?@endphp/g, '');
        const stack = [];
        const pairs = { endif: 'if', endforeach: 'foreach', endsection: 'section' };
        for (const [, directive] of markup.matchAll(/@(if|endif|foreach|endforeach|section|endsection)\b/g)) {
            if (Object.hasOwn(pairs, directive)) assert.equal(stack.pop(), pairs[directive]);
            else stack.push(directive);
        }
        assert.deepEqual(stack, []);
    }
});
