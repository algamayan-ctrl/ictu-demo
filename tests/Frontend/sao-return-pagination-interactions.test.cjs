const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const template = fs.readFileSync(path.resolve(__dirname, '../../resources/views/custody/index.blade.php'), 'utf8');
const scripts = [...template.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
const script = scripts.at(-1);

function element(value = '') {
    const handlers = new Map();
    return {
        value, textContent: '', hidden: false, disabled: false, handlers, children: [],
        addEventListener(event, handler) { handlers.set(event, [...(handlers.get(event) || []), handler]); },
        dispatch(event) { for (const handler of handlers.get(event) || []) handler(); },
        appendChild(child) { this.children.push(child); },
        replaceChildren(...children) { this.children = children; },
        setAttribute() {},
        classList: { add() {} },
    };
}

function mount(count = 23) {
    const rows = Array.from({ length: count }, (_, index) => ({
        dataset: {
            id: String(index + 1), priority: '0', created: String(index + 1),
            search: `borrower ${index + 1} return-${index + 1}`,
            status: index % 2 ? 'OVERDUE' : 'RETURN_DUE',
        }, hidden: false,
    }));
    const list = { children: [...rows], appendChild(row) { this.children = this.children.filter((child) => child !== row); this.children.push(row); } };
    const search = element(); const status = element('all'); const sort = element('newest');
    const empty = element(); const footer = element(); const pageSize = element('10');
    const previous = element(); const next = element(); const range = element(); const pages = element();
    const byId = {
        'operational-filter-list': list, 'operational-search': search, 'operational-status': status,
        'operational-sort': sort, 'operational-filter-empty': empty,
    };
    const bySelector = {
        '[data-operational-pagination]': footer, '[data-operational-page-size]': pageSize,
        '[data-operational-previous]': previous, '[data-operational-next]': next,
        '[data-operational-range]': range, '[data-operational-pages]': pages,
    };
    vm.runInNewContext(script, { document: {
        getElementById: (id) => byId[id] || null,
        querySelectorAll: () => rows,
        querySelector: (selector) => bySelector[selector] || null,
        createElement: () => element(),
    } });
    return {
        search, status, pageSize, next, previous, empty, footer, range, pages, list,
        visible: () => list.children.filter((row) => !row.hidden).map((row) => Number(row.dataset.id)),
    };
}

test('SAO Return pagination, filtering and page-size controls work together', () => {
    const ui = mount();
    assert.deepEqual(ui.visible(), [23, 22, 21, 20, 19, 18, 17, 16, 15, 14]);
    assert.equal(ui.range.textContent, 'Showing 1–10 of 23 records');
    assert.ok(ui.pages.children.length > 0);
    assert.equal(ui.previous.disabled, true);

    ui.next.dispatch('click');
    assert.deepEqual(ui.visible(), [13, 12, 11, 10, 9, 8, 7, 6, 5, 4]);

    ui.status.value = 'OVERDUE'; ui.status.dispatch('change');
    assert.ok(ui.pages.children.length > 0);
    assert.ok(ui.visible().every((id) => id % 2 === 0));

    ui.search.value = 'return-4'; ui.search.dispatch('input');
    assert.deepEqual(ui.visible(), [4]);
    assert.equal(ui.range.textContent, 'Showing 1–1 of 1 record');

    ui.search.value = 'not found'; ui.search.dispatch('input');
    assert.equal(ui.empty.hidden, false);
    assert.equal(ui.footer.hidden, true);

    ui.search.value = ''; ui.search.dispatch('input');
    ui.pageSize.value = '30'; ui.pageSize.dispatch('change');
    assert.equal(ui.visible().length, 11);
    assert.ok(ui.pages.children.length > 0);
});
