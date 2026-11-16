const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/requests/partials/my-requests-scripts.blade.php'), 'utf8');
const source = view.match(/<script>([\s\S]*?)<\/script>/)[1];

function element(properties = {}) {
    const listeners = new Map();
    return {
        listeners,
        addEventListener: (name, callback) => listeners.set(name, callback),
        ...properties,
    };
}

function boot({ initialSearch = '', hasForm = true, menus = [], copyButtons = [] } = {}) {
    const search = element({ value: initialSearch });
    const status = element();
    const sort = element();
    let submissions = 0;
    const form = element({
        querySelector: selector => ({ '#request-search': search, '#request-status-filter': status, '#request-sort': sort })[selector] ?? null,
        requestSubmit: () => submissions++,
    });
    const document = element({
        getElementById: id => id === 'request-filters' && hasForm ? form : null,
        querySelectorAll: selector => ({ '[data-request-menu]': menus, '[data-copy-reference]': copyButtons })[selector] ?? [],
    });
    const copied = [];
    const timers = [];
    vm.runInNewContext(source, {
        document,
        navigator: { clipboard: { writeText: async value => copied.push(value) } },
        setTimeout: callback => timers.push(callback),
    });
    document.listeners.get('DOMContentLoaded')();
    return { search, status, sort, form, document, copied, timers, submissions: () => submissions };
}

test('Borrower status, sort, and clearing a current search submit the existing server filter form', () => {
    const ui = boot({ initialSearch: 'projector' });
    ui.status.listeners.get('change')();
    ui.sort.listeners.get('change')();
    ui.search.value = '';
    ui.search.listeners.get('search')();
    assert.equal(ui.submissions(), 3);

    const empty = boot();
    empty.search.listeners.get('search')();
    assert.equal(empty.submissions(), 0);
});

test('Borrower filter submission removes default values and retains explicit status and sort selections', () => {
    const ui = boot();
    const defaults = new Map([['search', '  '], ['status', 'all'], ['sort', 'newest']]);
    ui.form.listeners.get('formdata')({ formData: defaults });
    assert.equal(defaults.size, 0);

    const selected = new Map([['search', 'projector'], ['status', 'DRAFT'], ['sort', 'oldest']]);
    ui.form.listeners.get('formdata')({ formData: selected });
    assert.deepEqual([...selected], [['search', 'projector'], ['status', 'DRAFT'], ['sort', 'oldest']]);
});

test('Request menus still open exclusively and close with Escape when the filter toolbar is absent', () => {
    const makeMenu = () => {
        const trigger = element({ setAttribute: (name, value) => { trigger[name] = value; } });
        const panel = { hidden: true };
        return { trigger, panel, querySelector: selector => selector === '[data-request-menu-trigger]' ? trigger : panel };
    };
    const first = makeMenu();
    const second = makeMenu();
    const ui = boot({ hasForm: false, menus: [first, second] });
    const click = { stopPropagation() {} };
    first.trigger.listeners.get('click')(click);
    assert.equal(first.panel.hidden, false);
    second.trigger.listeners.get('click')(click);
    assert.equal(first.panel.hidden, true);
    assert.equal(second.panel.hidden, false);
    ui.document.listeners.get('keydown')({ key: 'Escape' });
    assert.equal(second.panel.hidden, true);
    assert.equal(second.trigger['aria-expanded'], 'false');
});

test('Copy reference retains clipboard feedback and restores the original label', async () => {
    const button = element({ dataset: { copyReference: 'BR-2026-001' }, textContent: 'Copy reference' });
    const ui = boot({ copyButtons: [button] });
    await button.listeners.get('click')();
    assert.deepEqual(ui.copied, ['BR-2026-001']);
    assert.equal(button.textContent, 'Copied');
    ui.timers[0]();
    assert.equal(button.textContent, 'Copy reference');
});
