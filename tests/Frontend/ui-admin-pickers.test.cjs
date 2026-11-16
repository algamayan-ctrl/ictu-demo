const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/ui-admin-revisions.js', 'utf8');
const context = {module: {exports: {}}};
vm.runInNewContext(source, context);
const {monthDays, timeParts, timeValue, canSelectDate} = context.module.exports;

test('date grid is Sunday first and handles leap years and month boundaries', () => {
    const december = monthDays(2026, 11);
    assert.equal(december[0], '2026-11-29');
    assert.equal(december.at(-1), '2027-01-02');
    assert.equal(december.length, 35);
    assert.ok(monthDays(2028, 1).includes('2028-02-29'));
});

test('unknown and closed dates cannot be selected, including typed date limits', () => {
    assert.equal(canSelectDate('2026-12-23', {open: false}), false);
    assert.equal(canSelectDate('2026-12-23', undefined), false);
    assert.equal(canSelectDate('2026-12-22', {open: true}, '2026-12-23'), false);
    assert.equal(canSelectDate('2026-12-30', {open: true}, '', '2026-12-29'), false);
    assert.equal(canSelectDate('2026-12-22', {open: true}, '2026-12-22'), true);
    assert.equal(canSelectDate('2026-12-29', {open: true}), true);
});

test('time wheel preserves every minute, midnight, noon and 24-hour form payloads', () => {
    for (let minute = 0; minute < 24 * 60; minute++) {
        const value = `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`;
        assert.equal(timeValue(timeParts(value)), value);
    }
    for (const value of ['', '25:00', '12:60', 'bad']) assert.equal(timeParts(value), null);
});
