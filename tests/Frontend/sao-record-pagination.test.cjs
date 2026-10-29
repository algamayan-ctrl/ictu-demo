const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = fs.readFileSync(path.join(__dirname, '../../resources/views/requests/partials/sao-record-pagination.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function mount(count = 25) {
    const element = (dataset = {}) => ({dataset, value: '', hidden: false, textContent: '', handlers: {},
        addEventListener(name, handler) { this.handlers[name] = handler; },
        dispatch(name) { this.handlers[name]?.(); },
    });
    const body = {children: [], appendChild(row) { this.children = this.children.filter(child => child !== row); this.children.push(row); }};
    const rows = Array.from({length: count}, (_, i) => ({...element({search: `request ${i} borrower ${i % 2 ? 'ana' : 'ben'}`, created: String(i), status: i % 2 ? 'COMPLETED' : 'UNDER_REVIEW', attention: i < 3 ? '1' : '0'}), parentElement: body}));
    body.children = [...rows];
    const search = element();
    const status = element(); status.value = 'all';
    const sort = element(); sort.value = 'newest';
    const empty = element(); const previous = element(); const next = element(); const range = element(); const pageLabel = element();
    const pagination = {...element(), querySelector: selector => ({'[data-sao-record-previous]': previous, '[data-sao-record-next]': next, '[data-sao-record-range]': range, '[data-sao-record-page]': pageLabel})[selector]};
    vm.runInNewContext(script, {document: {querySelectorAll: () => rows, querySelector: selector => ({'[data-record-search]': search, '[data-record-status-filter]': status, '[data-record-sort]': sort, '[data-record-empty]': empty, '[data-sao-record-pagination]': pagination})[selector]}});
    return {rows, search, status, sort, empty, previous, next, range, pageLabel, pagination, visible: () => body.children.filter(row => !row.hidden).map(row => row.dataset.created)};
}

test('SAO records paginate all matching records and preserve the final page', () => {
    const ui = mount();
    assert.deepEqual(ui.visible(), ['24','23','22','21','20','19','18','17','16','15']);
    assert.equal(ui.range.textContent, '1–10 of 25 requests');
    assert.equal(ui.previous.disabled, true);
    ui.next.dispatch('click'); ui.next.dispatch('click');
    assert.deepEqual(ui.visible(), ['4','3','2','1','0']);
    assert.equal(ui.range.textContent, '21–25 of 25 requests');
    assert.equal(ui.next.disabled, true);
    ui.next.dispatch('click');
    assert.equal(ui.pageLabel.textContent, 'Page 3 of 3');
});

test('search, attention and status filter the full set before pagination and reset to page one', () => {
    const ui = mount();
    ui.next.dispatch('click');
    ui.search.value = ' ANA '; ui.search.dispatch('input');
    assert.equal(ui.range.textContent, '1–10 of 12 requests');
    assert.equal(ui.pageLabel.textContent, 'Page 1 of 2');
    ui.status.value = 'NEEDS_ATTENTION'; ui.status.dispatch('change');
    assert.deepEqual(ui.visible(), ['1']);
    assert.equal(ui.pagination.hidden, false);
    ui.status.value = 'UNDER_REVIEW'; ui.status.dispatch('change');
    assert.deepEqual(ui.visible(), []);
    assert.equal(ui.empty.hidden, false);
});

test('sort resets pagination and clearing filters restores every original record', () => {
    const ui = mount();
    ui.search.value = 'missing'; ui.search.dispatch('input');
    assert.equal(ui.range.textContent, '0–0 of 0 requests');
    ui.search.value = ''; ui.search.dispatch('input');
    ui.next.dispatch('click');
    ui.sort.value = 'oldest'; ui.sort.dispatch('change');
    assert.deepEqual(ui.visible(), ['0','1','2','3','4','5','6','7','8','9']);
    assert.equal(ui.range.textContent, '1–10 of 25 requests');
    assert.equal(ui.rows.length, 25);
});

test('a small record set shows its page and count with both navigation buttons disabled', () => {
    const ui = mount(2);
    assert.equal(ui.pagination.hidden, false);
    assert.equal(ui.range.textContent, '1–2 of 2 requests');
    assert.equal(ui.pageLabel.textContent, 'Page 1 of 1');
    assert.equal(ui.previous.disabled, true);
    assert.equal(ui.next.disabled, true);
    assert.deepEqual(ui.visible(), ['1','0']);
    assert.doesNotMatch(script, /fetch\(|\.submit\(|requestSubmit\(|->(?:save|update|delete)\(/);
});
