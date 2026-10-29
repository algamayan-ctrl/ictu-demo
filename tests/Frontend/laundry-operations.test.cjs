const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const partial = (name) => read(`resources/views/laundry/partials/${name}.blade.php`);
const page = read('resources/views/laundry/index.blade.php');
const styles = partial('operations-styles');
const interactions = partial('operations-interactions');
const detail = read('resources/views/laundry/show.blade.php');
const detailStyles = read('resources/views/laundry/partials/detail-styles.blade.php');
const returnWorkspace = read('resources/views/custody/partials/return-workspace.blade.php');
const script = interactions.match(/<script>([\s\S]*?)<\/script>/)[1];

function element(dataset = {}) {
    const handlers = new Map();
    return {
        dataset,
        value: '',
        textContent: '',
        hidden: false,
        focused: false,
        addEventListener(name, callback) {
            handlers.set(name, [...(handlers.get(name) || []), callback]);
        },
        dispatch(name, event = {}) { (handlers.get(name) || []).forEach((callback) => callback(event)); },
        focus() { this.focused = true; },
    };
}

function mount({ paginated = false, recordsPresent = true } = {}) {
    const records = recordsPresent ? [
        element({ search: 'Maria Santos BR-001 Chairs', status: 'FOR_LAUNDRY', date: '100' }),
        element({ search: 'Juan Cruz BR-002 Foundation Linen', status: 'TURNED_OVER_TO_LAUNDRY', date: '300' }),
        element({ search: 'Ana Reyes BR-003 Seminar Linen', status: 'FOR_LAUNDRY', date: '200' }),
    ] : [];
    const list = { children: [...records], appendChild(record) {
        this.children = this.children.filter((child) => child !== record);
        this.children.push(record);
    } };
    const search = element();
    const status = element();
    const sort = element();
    sort.value = 'newest';
    const count = element({ total: paginated ? '47' : String(records.length), paginated: String(paginated) });
    const empty = element();
    const reset = element();
    const controls = {
        '[data-laundry-list]': list,
        '[data-laundry-search]': search,
        '[data-laundry-status]': status,
        '[data-laundry-sort]': sort,
        '[data-laundry-count]': count,
        '[data-laundry-filter-empty]': empty,
        '[data-laundry-reset]': reset,
    };
    const browser = {
        dataset: {},
        querySelector: (selector) => controls[selector],
        querySelectorAll: () => records,
    };
    const context = { document: { querySelector: (selector) => selector === '[data-laundry-browser]' ? browser : null } };
    vm.runInNewContext(script, context);
    return { records, list, search, status, sort, count, empty, reset, context };
}

test('laundry tabs show completed pagination and support keyboard navigation without changing records', () => {
    for (const address of ['http://localhost/laundry?completed_page=2', 'http://localhost/laundry#completed-laundry-cases', 'http://localhost/laundry']) {
        const tabs = ['active', 'completed'].map((name) => {
            const tab = element({ laundryTab: name });
            tab.attributes = {};
            tab.classList = { toggle: (_, selected) => { tab.selected = selected; } };
            tab.setAttribute = (key, value) => { tab.attributes[key] = value; };
            return tab;
        });
        const panels = ['active', 'completed'].map((name) => element({ laundryPanel: name }));
        const workspace = { querySelectorAll: (selector) => selector === '[data-laundry-tab]' ? tabs : panels };
        vm.runInNewContext(script, {
            URL,
            window: { location: { href: address } },
            document: { querySelector: (selector) => selector === '[data-laundry-tabs]' ? workspace : null },
        });
        const initial = address.endsWith('/laundry') ? 0 : 1;
        assert.equal(panels[initial].hidden, false);
        assert.equal(panels[1 - initial].hidden, true);
        assert.equal(tabs[initial].attributes['aria-selected'], 'true');
        assert.equal(tabs[initial].tabIndex, 0);
        let prevented = false;
        tabs[initial].dispatch('keydown', { key: 'Home', preventDefault: () => { prevented = true; } });
        assert.equal(prevented, true);
        assert.equal(panels[0].hidden, false);
        assert.equal(tabs[0].focused, true);
        tabs[0].dispatch('keydown', { key: 'ArrowRight', preventDefault() {} });
        assert.equal(panels[1].hidden, false);
        assert.equal(tabs[1].tabIndex, 0);
        tabs[0].dispatch('click');
        assert.equal(panels[0].hidden, false);
        assert.equal(tabs[1].attributes['aria-selected'], 'false');
    }
});

/*
 * The standalone "Laundry flow" guide/tracker card was intentionally removed
 * from this page (see commit fd43ad3, which dropped its @include from both
 * laundry/index.blade.php and laundry/show.blade.php in the same redesign
 * that reworked this page's headings and empty-state copy). The orphaned
 * partial it rendered (laundry.partials.flow-guide) has been deleted as dead
 * code. This test now covers only the empty/populated branching that is
 * still current.
 */
test('the page keeps active and completed laundry records in one workspace', () => {
    assert.match(page, /\$hasLaundryCases = \$jobs->total\(\) > 0;/);
    assert.match(page, /data-laundry-tabs/);
    assert.match(page, /data-laundry-panel="active"/);
    assert.match(page, /data-laundry-panel="completed"/);
    assert.equal((page.match(/id="laundry-search"/g) || []).length, 1, 'active filters must not be duplicated during merge');
    assert.ok(page.indexOf('aria-label="Active laundry case filters"') < page.indexOf('@if($hasLaundryCases)'), 'filters remain available above both empty and populated states');
    assert.match(page, /@else\s+<div class="laundry-inline-empty"/);
    assert.match(page, /No active laundry cases need action\./);
    assert.match(page, /Finished cases will stay visible here for record review\./);
    assert.doesNotMatch(page, /Completed Records/);
});

test('detail view uses the accomplished form as one record instead of tracking wet signatures separately', () => {
    assert.match(detail, /\$formArchived = \$job->hasVerifiedAccomplishedForm\(\);/);
    assert.match(detail, /\$serviceableQuantity = \(int\) \$job->lines->sum\('completed_quantity'\)/);
    assert.match(detail, /Received by Laundry<\/dt>/);
    assert.match(detail, /Returned in Good Condition<\/dt><dd>\{\{ \$serviceableQuantity \}\}/);
    assert.doesNotMatch(detail, /<dt>.*Issued by:|<dt>.*Received by:|Pending return/);
    assert.match(detail, /no further action is needed\./);
    assert.match(detail, /route\('custody\.return\.show', \$job->custody\)/);
    assert.match(detail, /'#return-primary' : '#return-documents'/);
    assert.doesNotMatch(detail, /<form|method="post"/);
});

test('detail shows one concise record without a separate offline tracker or duplicate summary', () => {
    assert.doesNotMatch(detail, /laundry-progress-tracker|Completion Summary|Linen Status|No Laundry portal login/);
    assert.equal((detail.match(/class="card /g) || []).length, 1);
    assert.match(detail, /<h2>Return Details<\/h2>/);
    assert.match(detail, /route\('laundry\.index'\)/);
    assert.doesNotMatch(detailStyles, /laundry-progress|laundry-next-action/);
});

test('return workspace keeps the linen instructions concise', () => {
    /*
     * The upload disclosure no longer carries a separate self-attestation
     * checkbox or an external "Open Laundry Processing" navigation link -
     * the inline upload form, its one-line date helper, and the recorded/
     * awaiting status text are the whole instruction set now.
     */
    assert.match(returnWorkspace, /Enter the actual RECEIVED BY date on the signed form\./);
    assert.match(returnWorkspace, /The physical receipt date, not the SPMU recording date, determines return timing\./);
    assert.match(returnWorkspace, />Record Laundry Form</);
    assert.match(returnWorkspace, /Accomplished form recorded\./);
    assert.match(returnWorkspace, /Awaiting accomplished copy\./);
    assert.doesNotMatch(returnWorkspace, /I confirm this is the accomplished Laundry Form signed by Laundry Personnel\.|Open (?:Internal )?Laundry Processing/);
});

test('Laundry uses the shared page heading and keeps responsive tables within the workspace', () => {
    assert.ok(page.indexOf('<section class="page-heading">') < page.indexOf('data-laundry-browser'));
    assert.match(page, /class="content-area laundry-operations"/);
    assert.match(page, /class="laundry-unified-workspace" data-laundry-tabs/);
    assert.doesNotMatch(styles, /laundry-flow-card|laundry-operations-heading/);
    assert.match(styles, /@media \(max-width: 900px\)/);
    assert.match(styles, /@media \(max-width: 760px\)/);
    assert.match(styles, /\.laundry-table-wrap \{[^}]*overflow-x: auto/);
});

test('case tables preserve live data, current statuses and protected navigation', () => {
    assert.doesNotMatch(page, /route\('laundry\.completed'\)/);
    assert.match(page, /route\('laundry\.show', \$job\)/);
    assert.match(page, /@foreach\(\$completedJobs as \$job\)/);
    assert.match(page, /@foreach\(\$jobs as \$job\)/);
    assert.match(page, /\{\{ \$jobs->links\('partials\.pagination'\) \}\}/);
    assert.match(page, /<x-status-badge :status="\$job->status" :label="\$statusText"/);
    for (const field of ['request_no', 'custody_no', 'full_name', 'unit_name', 'purpose_event', 'received_at', 'description_snapshot']) {
        assert.ok(page.includes(field), `Missing live field: ${field}`);
    }
    /* The hardcoded two-arm status match was replaced by the richer $laundryFilterStatus closure. */
    assert.match(page, /\$job->status === 'FOR_LAUNDRY' && \$job->hasVerifiedAccomplishedForm\(\)\s+=> \['key' => 'READY_FOR_SPMU_ENCODING', 'label' => 'For Return Recording'\]/);
    assert.match(page, /\$job->status === 'TURNED_OVER_TO_LAUNDRY'\s+=> \['key' => 'RECONCILIATION_PENDING', 'label' => 'Updating Record'\]/);
    assert.match(page, /\$jobs->getCollection\(\)->contains\('status', 'TURNED_OVER_TO_LAUNDRY'\)/);
    assert.match(page, /\$statusText = \$filterStatus\['label'\];/);
    assert.doesNotMatch(page, /FOR_SPMU_FINAL_CHECK|AWAITING_FINAL_FORM_UPLOAD|READY_FOR_SPMU_RETURN|For SPMU Acceptance/);
    assert.doesNotMatch(page, /Juan Dela Cruz|CSPC Foundation Day 2026|CUS-2026/);
});

test('filters are read-only and both active and completed queues keep local controls', () => {
    assert.match(page, /data-laundry-search/);
    assert.match(page, /data-completed-search/);
    assert.match(page, /type="button" data-laundry-reset/);
    assert.match(page, /type="button" data-completed-reset/);
    assert.match(page, /role="status" aria-live="polite"/);
    assert.doesNotMatch(page, /<form|->(?:save|update|create|delete)\(/);
    assert.doesNotMatch(interactions, /fetch\(|XMLHttpRequest|\.submit\(|requestSubmit\(|location\.(?:assign|replace)\(|location(?:\.href)?\s*=/);
});

test('newest and oldest sorting use the correct date direction', () => {
    const ui = mount();
    assert.deepEqual(ui.list.children.map((row) => row.dataset.date), ['300', '200', '100']);
    ui.sort.value = 'oldest';
    ui.sort.dispatch('change');
    assert.deepEqual(ui.list.children.map((row) => row.dataset.date), ['100', '200', '300']);
    assert.equal(ui.count.textContent, 'Showing 3 of 3 cases');
});

test('search is case-insensitive, matches multiple words and combines with status', () => {
    const ui = mount();
    ui.search.value = '  MARIA   chairs ';
    ui.search.dispatch('input');
    assert.deepEqual(ui.records.map((row) => row.hidden), [false, true, true]);
    assert.equal(ui.count.textContent, 'Showing 1 of 3 cases');
    assert.equal(ui.empty.hidden, true);
    ui.status.value = 'TURNED_OVER_TO_LAUNDRY';
    ui.status.dispatch('change');
    assert.equal(ui.empty.hidden, false);
    assert.ok(ui.records.every((row) => row.hidden));
    ui.search.value = 'linen';
    ui.search.dispatch('input');
    assert.deepEqual(ui.records.map((row) => row.hidden), [true, false, true]);
});

test('clear filters restores the cases and focus without duplicate handlers', () => {
    const ui = mount();
    vm.runInNewContext(script, ui.context);
    ui.search.value = 'no match';
    ui.status.value = 'FOR_LAUNDRY';
    ui.sort.value = 'oldest';
    ui.search.dispatch('input');
    assert.equal(ui.empty.hidden, false);
    ui.reset.dispatch('click');
    assert.equal(ui.search.value, '');
    assert.equal(ui.status.value, '');
    assert.equal(ui.sort.value, 'newest');
    assert.ok(ui.records.every((row) => !row.hidden));
    assert.equal(ui.search.focused, true);
    assert.equal(ui.empty.hidden, true);
});

test('paginated and empty results never claim that hidden cases do not exist', () => {
    const paginated = mount({ paginated: true });
    assert.equal(paginated.count.textContent, 'Showing 3 of 3 cases on this page (47 total)');
    const noRows = mount({ recordsPresent: false, paginated: true });
    assert.equal(noRows.count.textContent, 'Showing 0 of 0 cases on this page (47 total)');
    assert.equal(noRows.empty.hidden, false);
    assert.doesNotThrow(() => vm.runInNewContext(script, {
        document: { querySelector: (selector) => selector === '[data-laundry-browser]' ? ({ dataset: {}, querySelector: () => null }) : null },
    }));
});

test('the new Blade branches are balanced and use existing theme variables', () => {
    const source = page.replace(/@php[\s\S]*?@endphp/g, '');
    const stack = [];
    const pairs = { endif: 'if', endforeach: 'foreach', endsection: 'section' };
    for (const [, directive] of source.matchAll(/@(if|endif|foreach|endforeach|section|endsection)\b/g)) {
        if (Object.hasOwn(pairs, directive)) assert.equal(stack.pop(), pairs[directive]);
        else stack.push(directive);
    }
    assert.deepEqual(stack, []);
    const globalStyles = read('public/css/app.css');
    const variables = new Set([...`${globalStyles}\n${styles}`.matchAll(/(--[\w-]+)\s*:/g)].map((match) => match[1]));
    /*
     * A var() call that supplies its own fallback (e.g. var(--shadow-md, ...))
     * degrades safely even when the custom property isn't globally defined,
     * so only a parameterless var(--x) is required to resolve to a real token.
     */
    for (const [, name] of styles.matchAll(/var\((--[\w-]+)\)/g)) {
        assert.ok(variables.has(name), `Undefined CSS variable: ${name}`);
    }
});
