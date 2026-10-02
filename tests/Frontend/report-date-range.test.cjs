const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Render ReportDateRangeTest with REPORT_BUILDER_HTML set first. This optional
// browser suite uses an installed Playwright via PLAYWRIGHT_MODULE or require().
const fixture = process.env.REPORT_BUILDER_HTML;
test('Report Builder date ranges in a real browser', { skip: !fixture }, async (t) => {
    const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
    const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL });
    t.after(() => browser.close());
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Los_Angeles' });
    page.setDefaultTimeout(5000);
    const errors = [];
    const requests = [];
    page.on('pageerror', error => errors.push(error.message));
    const publicRoot = path.resolve(__dirname, '../../public');
    const html = fs.readFileSync(fixture, 'utf8');
    await page.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.searchParams.has('borrower_options')) {
            requests.push(url);
            return route.fulfill({ json: { options: [], count: 0, shown: 0, has_more: false } });
        }
        if (url.pathname === '/reports') {
            requests.push(url);
            return route.fulfill({ contentType: 'text/html', body: html });
        }
        const asset = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
        if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) {
            return route.fulfill({ path: asset });
        }
        return route.fulfill({ status: 204, body: '' });
    });
    const load = () => page.goto('http://report-range.test/reports');
    const period = page.locator('#reports-academic-period');
    const trigger = page.locator('[data-report-date-trigger]');
    const picker = page.locator('#report-date-picker');
    const label = page.locator('[data-applied-date-label]');
    const day = value => page.locator(`[data-date="${value}"]`);
    const values = () => page.locator('#report-builder').evaluate(form => {
        const data = new FormData(form);
        return [data.get('start_date'), data.get('end_date')];
    });
    await load();

    await t.test('A–D: presets update immediately and keep Date Range non-interactive', async () => {
        for (const [value, expected] of [
            ['month', '01 Oct 2026 – 31 Oct 2026'],
            ['last_month', '01 Sep 2026 – 30 Sep 2026'],
            ['quarter', '01 Oct 2026 – 31 Dec 2026'],
            ['year', '01 Jan 2026 – 31 Dec 2026'],
        ]) {
            await period.selectOption(value);
            assert.equal(await label.textContent(), expected);
            assert.equal(await trigger.getAttribute('aria-disabled'), 'true');
            await trigger.dispatchEvent('click');
            assert.equal(await picker.isVisible(), false);
        }
    });

    await t.test('E/G: drafts, highlights, same-day support, and blocked reversed dates', async () => {
        await period.selectOption('month');
        const before = await trigger.boundingBox();
        await period.selectOption('custom');
        await trigger.click();
        assert.deepEqual(await trigger.boundingBox(), before);
        assert.equal(await page.locator('.report-calendar-month:visible').count(), 2);
        await day('2026-10-05').click();
        assert.equal(await day('2026-10-04').isDisabled(), true);
        assert.equal(await page.locator('[data-date-apply]').isDisabled(), true);
        await day('2026-10-20').click();
        assert.deepEqual(await values(), ['2026-10-01', '2026-10-31']);
        assert.equal(await label.textContent(), '01 Oct 2026 – 31 Oct 2026');
        assert.equal(await page.locator('.report-calendar-day.is-endpoint').count(), 2);
        assert.equal(await page.locator('.report-calendar-day.is-between').count(), 14);
        const colors = await page.evaluate(() => ['2026-10-05', '2026-10-10', '2026-10-25'].map(value =>
            getComputedStyle(document.querySelector(`[data-date="${value}"]`)).backgroundColor));
        assert.equal(new Set(colors).size, 3);
        const actionColors = await page.evaluate(() => ['[data-date-apply]', '[data-date-cancel]'].map(selector =>
            getComputedStyle(document.querySelector(selector)).backgroundColor));
        assert.notEqual(actionColors[0], actionColors[1]);
        await page.screenshot({ path: path.join(path.dirname(fixture), 'custom-desktop.png'), fullPage: true });
        await page.locator('[data-date-apply]').click();
        assert.deepEqual(await values(), ['2026-10-05', '2026-10-20']);
        assert.equal(await label.textContent(), '05 Oct 2026 – 20 Oct 2026');
    });

    await t.test('F: Cancel and Escape discard drafts, and navigation crosses months', async () => {
        await trigger.click();
        await day('2026-10-25').click();
        await page.locator('[data-date-month-step="1"]').click();
        await day('2026-11-03').click();
        await page.locator('[data-date-cancel]').click();
        assert.deepEqual(await values(), ['2026-10-05', '2026-10-20']);
        await trigger.focus();
        await page.keyboard.press('Enter');
        await day('2026-10-15').click();
        await page.keyboard.press('Escape');
        assert.equal(await picker.isVisible(), false);
        assert.equal(await trigger.evaluate(element => element === document.activeElement), true);
        assert.deepEqual(await values(), ['2026-10-05', '2026-10-20']);
    });

    await t.test('H: Preview and borrower cascade receive applied dates only', async () => {
        await trigger.click();
        await day('2026-10-09').click();
        await day('2026-10-12').click();
        await Promise.all([
            page.waitForURL(url => url.searchParams.get('generated') === '1'),
            page.locator('.report-generate-button').click(),
        ]);
        const query = new URL(page.url()).searchParams;
        assert.equal(query.get('start_date'), '2026-10-05');
        assert.equal(query.get('end_date'), '2026-10-20');
        const cascades = requests.filter(url => url.searchParams.has('borrower_options'));
        assert.ok(cascades.some(url => url.searchParams.get('start_date') === '2026-10-05' && url.searchParams.get('end_date') === '2026-10-20'));
        assert.ok(!cascades.some(url => url.searchParams.get('start_date') === '2026-10-09'));
    });

    await t.test('I: switching to a preset replaces custom dates and closes the picker', async () => {
        await period.selectOption('custom');
        await trigger.click();
        await day('2026-10-05').click();
        await day('2026-10-05').click();
        await page.locator('[data-date-apply]').click();
        assert.deepEqual(await values(), ['2026-10-05', '2026-10-05']);
        await trigger.click();
        await period.selectOption('month');
        assert.deepEqual(await values(), ['2026-10-01', '2026-10-31']);
        assert.equal(await picker.isVisible(), false);
        assert.equal(await trigger.getAttribute('tabindex'), '-1');
    });

    await t.test('mobile picker fits the viewport and preserves the existing field dimensions', async () => {
        await page.setViewportSize({ width: 390, height: 844 });
        await period.selectOption('custom');
        await trigger.scrollIntoViewIfNeeded();
        const before = await trigger.boundingBox();
        await trigger.click();
        assert.deepEqual(await trigger.boundingBox(), before);
        assert.equal(await page.locator('.report-calendar-month:visible').count(), 1);
        const box = await picker.boundingBox();
        assert.ok(box.x >= 0 && box.x + box.width <= 390);
        await page.screenshot({ path: path.join(path.dirname(fixture), 'custom-mobile.png'), fullPage: true });
        await page.locator('[data-date-cancel]').click();
    });

    await t.test('invalid applied dates block Preview without locking the button', async () => {
        await page.locator('[data-applied-start-date]').evaluate(input => { input.value = '2026-02-30'; });
        await page.locator('.report-generate-button').click();
        assert.equal(await page.locator('[data-report-date-error]').isVisible(), true);
        assert.equal(await page.locator('#report-builder').getAttribute('data-preview-submitting'), null);
    });
    await t.test('changing Report Type carries the applied custom range without generating a preview', async () => {
        await load();
        await period.selectOption('custom');
        await trigger.click();
        await day('2026-10-05').click();
        await day('2026-10-20').click();
        await page.locator('[data-date-apply]').click();
        await Promise.all([
            page.waitForURL(url => url.searchParams.get('report') === 'approval'),
            page.locator('[data-report-type]').selectOption('approval'),
        ]);
        const query = new URL(page.url()).searchParams;
        assert.equal(query.get('academic_period'), 'custom');
        assert.equal(query.get('start_date'), '2026-10-05');
        assert.equal(query.get('end_date'), '2026-10-20');
        assert.equal(query.has('generated'), false);
    });
    assert.deepEqual(errors, []);
});
