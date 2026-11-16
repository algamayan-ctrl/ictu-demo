const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const fixtures = process.env.CONTROL_REPORT_HTML_DIR;
let JSDOM;
try { ({JSDOM} = require(process.env.REPORTS_TEST_JSDOM || 'jsdom')); } catch {}

// This is the interaction check using the actual rendered Reports page and updater.
test('Reports keep their own builders, filters, titles and generation controls', {skip: !fixtures || !JSDOM}, async t => {
    const html = {};
    for (const key of ['summary', 'all-transactions', 'inventory', 'utilization', 'accountability-cases', 'billing-settlement']) {
        html[key] = fs.readFileSync(path.join(fixtures, key === 'accountability-cases' ? 'cases.html' : key+'.html'), 'utf8');
    }
    const dom = new JSDOM(html.summary, {url: 'http://127.0.0.1:8001/reports', runScripts: 'outside-only', pretendToBeVisual: true});
    t.after(() => dom.window.close());
    const w = dom.window, d = w.document, requests = [];
    const delay = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
    const until = async predicate => {
        for (let retry = 0; retry < 60; retry++) { if (predicate()) return; await delay(5); }
        assert.ok(predicate(), 'The rendered builder/results did not update.');
    };
    let hold;
    w.fetch = async (value, options) => {
        const url = new URL(value, w.location.href);
        requests.push(url);
        const key = url.searchParams.get('report') || 'summary';
        if (hold?.key === key) return new Promise(resolve => { hold.resolve = () => resolve({ok: true, text: async () => html[key]}); });
        assert.ok(options.signal, 'The updater must keep its existing request cancellation.');
        return {ok: true, text: async () => html[key]};
    };
    const updater = [...d.scripts].find(script => script.textContent.includes('const builderUrl ='));
    assert.ok(updater, 'The fixture must contain the actual page updater.');
    w.eval(updater.textContent);
    w.eval(fs.readFileSync(process.env.REPORTS_TEST_SCRIPT || path.resolve(__dirname, '../../public/js/reports-compact.js'), 'utf8'));
    const form = () => d.querySelector('[data-report-compact-builder]');
    const choose = key => {
        const select = form().elements.namedItem('report');
        select.value = key;
        select.dispatchEvent(new w.Event('change', {bubbles: true}));
    };
    const title = () => d.querySelector('.report-preview-title').textContent.trim();
    const modes = [
        ['all-transactions', 'All Transactions (General Overview)', ['academic_period', 'request_status', 'division', 'unit', 'borrower', 'search'], ['summary_month', 'category']],
        ['inventory', 'Inventory Status Report', ['academic_period', 'category', 'availability_status', 'search'], ['summary_month', 'borrower', 'request_status']],
        ['utilization', 'Equipment Utilization Report', ['academic_period', 'equipment', 'division', 'unit'], ['summary_month', 'borrower', 'availability_status', 'search']],
        ['accountability-cases', 'Accountability Cases Report', ['academic_period', 'accountability_finding', 'accountability_status', 'borrower', 'search'], ['summary_month', 'category']],
        ['billing-settlement', 'Billing Settlement Report', ['academic_period', 'billing_status', 'payment_status', 'borrower', 'search'], ['summary_month', 'category', 'division']],
        ['summary', 'SUMMARY OF REQUESTS FOR EQUIPMENTS FOR THE MONTH OF NOVEMBER 2026', ['summary_month', 'summary_year', 'division', 'unit', 'borrower'], ['request_status', 'category', 'payment_status', 'search']],
    ];
    for (const [key, heading, required, absent] of modes) {
        await t.test('Switch to '+key+' updates the builder, filters, heading and print target', async () => {
            const before = requests.length;
            choose(key);
            await until(() => requests.length === before+1 && form().elements.namedItem('report').value === key && title() === heading);
            for (const name of required) assert.ok(form().elements.namedItem(name), key+' must keep '+name);
            for (const name of absent) assert.equal(form().elements.namedItem(name), null, key+' must not inherit '+name);
            assert.equal(form().dataset.summary, key === 'summary' ? '1' : '0');
            const bar = d.querySelector('.report-preview-bar');
            assert.equal(bar.querySelector('[data-open-export-options]').textContent.trim(), 'Generate');
            assert.ok(bar.querySelector('.report-preview-title'));
            assert.ok(bar.querySelector('a[href*="/reports/print/'+key+'"]'));
            assert.equal(new URL(w.location.href).searchParams.get('report'), key);
        });
    }
    await t.test('Apply reaches the updater and carries the current report search', async () => {
        choose('all-transactions');
        await until(() => title() === 'All Transactions (General Overview)' && form().elements.namedItem('search'));
        form().elements.namedItem('search').value = 'BR-SEARCH';
        const before = requests.length;
        form().dispatchEvent(new w.Event('submit', {bubbles: true, cancelable: true}));
        await until(() => requests.length === before+1);
        assert.equal(requests.at(-1).searchParams.get('report'), 'all-transactions');
        assert.equal(requests.at(-1).searchParams.get('search'), 'BR-SEARCH');
        assert.equal(requests.at(-1).searchParams.has('page'), false);
    });
    await t.test('More filters Apply refreshes once; Cancel leaves the report unchanged', async () => {
        form().querySelector('[data-report-more-trigger]').click();
        const panel = d.querySelector('body > [data-report-more-panel]');
        panel.querySelector('[data-report-more-clear]').click();
        const before = requests.length;
        panel.querySelector('[data-report-more-apply]').click();
        await until(() => requests.length === before+1);
        form().querySelector('[data-report-more-trigger]').click();
        d.querySelector('body > [data-report-more-panel] [data-report-more-cancel]').click();
        await delay(15);
        assert.equal(requests.length, before+1);
    });
    await t.test('An older response cannot replace the newest selected report', async () => {
        hold = {key: 'inventory'};
        choose('inventory');
        await until(() => typeof hold.resolve === 'function');
        choose('billing-settlement');
        await until(() => title() === 'Billing Settlement Report');
        hold.resolve();
        await delay(20);
        assert.equal(title(), 'Billing Settlement Report');
        assert.equal(form().elements.namedItem('report').value, 'billing-settlement');
    });
});

// This is the interaction check for Summary monthly and custom date selection.
test('Summary custom dates survive submission and mode changes keep only the active date controls', {skip: !fixtures || !JSDOM}, async t => {
    const html = fs.readFileSync(path.join(fixtures, 'summary-custom.html'), 'utf8');
    const dom = new JSDOM(html, {url:'http://127.0.0.1:8001/reports?report=summary&academic_period=custom', runScripts:'outside-only', pretendToBeVisual:true});
    t.after(() => dom.window.close());
    const w = dom.window, d = w.document, requests = [];
    const updater = [...d.scripts].find(script => script.textContent.includes('const builderUrl ='));
    w.fetch = async value => {
        const url = new URL(value, w.location.href);
        requests.push(url);
        return {ok:true, text:async () => url.searchParams.get('report') === 'all-transactions'
            ? fs.readFileSync(path.join(fixtures, 'transactions-custom.html'), 'utf8') : html};
    };
    w.eval(updater.textContent);
    w.eval(fs.readFileSync(process.env.REPORTS_TEST_SCRIPT || path.resolve(__dirname, '../../public/js/reports-compact.js'), 'utf8'));
    const form = () => d.querySelector('[data-report-compact-builder]');
    const field = name => form().elements.namedItem(name);
    const change = (name, value) => { field(name).value = value; field(name).dispatchEvent(new w.Event('change', {bubbles:true})); };
    const applied = async count => {
        for (let retry = 0; retry < 80; retry++) {
            if (requests.length === count && d.querySelector('[data-report-results]').getAttribute('aria-busy') !== 'true') return;
            await new Promise(resolve => setTimeout(resolve, 5));
        }
        assert.fail('The report refresh did not finish.');
    };

    assert.equal(field('academic_period').value, 'custom');
    assert.equal(field('summary_month').disabled, true);
    assert.equal(field('summary_year').disabled, true);
    assert.equal(field('start_date').disabled, false);
    change('start_date', '2026-11-01');
    change('end_date', '2026-12-15');
    form().dispatchEvent(new w.Event('submit', {bubbles:true, cancelable:true}));
    await applied(1);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].searchParams.get('start_date'), '2026-11-01');
    assert.equal(requests[0].searchParams.get('end_date'), '2026-12-15');
    assert.equal(requests[0].searchParams.has('summary_month'), false);
    assert.equal(requests[0].searchParams.has('summary_year'), false);

    change('start_date', '2026-12-20');
    change('end_date', '2026-11-01');
    assert.equal(field('end_date').min, '2026-12-20');
    assert.equal(field('end_date').validity.valid, false);
    form().dispatchEvent(new w.Event('submit', {bubbles:true, cancelable:true}));
    assert.equal(requests.length, 1, 'Invalid ranges must not reach the updater.');

    change('academic_period', 'month');
    change('summary_year', '2028');
    change('summary_month', '2');
    assert.equal(field('start_date').disabled, true);
    assert.equal(field('end_date').disabled, true);
    assert.equal(field('summary_month').disabled, false);
    assert.equal(field('start_date').value, '2028-02-01');
    assert.equal(field('end_date').value, '2028-02-29');
    assert.equal(field('end_date').validationMessage, '');
    assert.equal(form().querySelector('[data-report-custom-range]').hidden, true);
    form().dispatchEvent(new w.Event('submit', {bubbles:true, cancelable:true}));
    await applied(2);
    assert.equal(requests.length, 2);
    assert.equal(requests[1].searchParams.get('academic_period'), 'month');
    assert.equal(requests[1].searchParams.get('summary_month'), '2');
    assert.equal(requests[1].searchParams.get('summary_year'), '2028');
    assert.equal(requests[1].searchParams.has('start_date'), false);
    assert.equal(requests[1].searchParams.has('end_date'), false);

    change('academic_period', 'custom');
    change('start_date', '2026-11-01');
    change('end_date', '2026-12-15');
    change('report', 'all-transactions');
    await applied(3);
    assert.equal(requests[2].searchParams.get('academic_period'), 'custom');
    assert.equal(requests[2].searchParams.get('start_date'), '2026-11-01');
    assert.equal(requests[2].searchParams.get('end_date'), '2026-12-15');
    assert.equal(field('report').value, 'all-transactions');
    assert.equal(field('summary_month'), null);
});
