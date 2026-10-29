const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const partial = fs.readFileSync(path.resolve(__dirname, '../../resources/views/inventory/partials/sao-history-interactions.blade.php'), 'utf8');
const script = partial.match(/<script>([\s\S]*?)<\/script>/)[1];

function mount() {
  const handlers = new Map();
  const fields = Object.fromEntries(Object.entries({tab: 'borrowing-history', history_search: '', history_from: '2026-10-01', history_to: '2026-11-30', history_status: 'ALL', history_per_page: '10'}).map(([name, value]) => [name, {
    name, value, validation: '', addEventListener(event, fn) { handlers.set(`${name}:${event}`, fn); }, setCustomValidity(message) { this.validation = message; },
  }]));
  const form = {
    action: 'http://localhost/inventory/25', elements: {namedItem: name => fields[name]},
    querySelector: () => fields.history_search,
    addEventListener(event, fn) { handlers.set(`form:${event}`, fn); },
    reportValidity: () => !fields.history_to.validation,
  };
  const results = {
    children: ['original rows'], attributes: {},
    addEventListener(event, fn) { handlers.set(`results:${event}`, fn); },
    setAttribute(name, value) { this.attributes[name] = value; },
    replaceChildren(...nodes) { this.children = nodes; }, contains: () => true,
  };
  const panel = {hidden: false};
  const timers = new Map();
  const requests = [], assigned = [], history = [];
  let timerId = 0;
  vm.runInNewContext(script, {
    URL, AbortController,
    document: {addEventListener: (_name, fn) => fn(), getElementById: id => id.endsWith('-filters') ? form : results, querySelector: () => panel},
    window: {location: {href: 'http://localhost/inventory/25?tab=borrowing-history&history_page=3', assign: url => assigned.push(url)}, history: {state: null, replaceState: (_state, _title, url) => history.push(url)}},
    FormData: class { *[Symbol.iterator]() { for (const field of Object.values(fields)) yield [field.name, field.value]; } },
    DOMParser: class { parseFromString(html) { return {
      getElementById: () => html === 'login' ? null : {childNodes: [html]},
      querySelector: selector => ({value: selector.includes('history_from') ? '2026-10-01' : '2026-11-30'}),
    }; } },
    setTimeout(fn, delay) { const id = ++timerId; timers.set(id, {fn, delay}); return id; },
    clearTimeout: id => timers.delete(id),
    fetch(url, options) { let resolve, reject; const pending = new Promise((yes, no) => { resolve = yes; reject = no; }); requests.push({url, options, resolve, reject}); return pending; },
  });
  return {
    fields, results, panel, timers, requests, assigned, history,
    event(name, event, payload = {}) { handlers.get(`${name}:${event}`)(payload); },
    change(name) { handlers.get('form:change')({target: fields[name]}); },
    run() { const [id, timer] = timers.entries().next().value; timers.delete(id); return timer.fn(); },
    respond(index, html, url = requests[index].url) { requests[index].resolve({ok: true, url, text: async () => html}); },
  };
}

test('status changes combine date/search filters and return to page one without replacing the form', async () => {
  const ui = mount();
  ui.fields.history_search.value = '  History Borrower ';
  ui.fields.history_status.value = 'OVERDUE';
  ui.change('history_status');
  const task = ui.run();
  const url = new URL(ui.requests[0].url);
  assert.equal(url.searchParams.get('history_search'), 'History Borrower');
  assert.equal(url.searchParams.get('history_from'), '2026-10-01');
  assert.equal(url.searchParams.get('history_status'), 'OVERDUE');
  assert.equal(url.searchParams.get('tab'), 'borrowing-history');
  assert.equal(url.searchParams.has('history_page'), false);
  assert.equal(ui.requests[0].options.method, 'GET');
  assert.equal(ui.requests[0].options.credentials, 'same-origin');
  ui.respond(0, 'overdue rows and pagination'); await task;
  assert.deepEqual(ui.results.children, ['overdue rows and pagination']);
  assert.equal(ui.fields.history_search.value, '  History Borrower ');
  assert.equal(ui.assigned.length, 0);
});

test('search waits for typing to stop and a newer response cannot be overwritten by an old request', async () => {
  const ui = mount();
  ui.fields.history_search.value = 'a'; ui.event('history_search', 'input');
  ui.fields.history_search.value = 'alice'; ui.event('history_search', 'input');
  assert.equal(ui.timers.size, 1);
  assert.equal([...ui.timers.values()][0].delay, 500);
  const oldTask = ui.run();
  ui.fields.history_search.value = 'bob'; ui.event('history_search', 'input');
  assert.equal(ui.requests[0].options.signal.aborted, true);
  const newTask = ui.run();
  ui.respond(1, 'bob rows'); await newTask;
  ui.respond(0, 'stale alice rows'); await oldTask;
  assert.deepEqual(ui.results.children, ['bob rows']);
  assert.equal(new URL(ui.history[0]).searchParams.get('history_search'), 'bob');
  assert.equal(ui.results.attributes['aria-busy'], 'false');
});

test('invalid date ranges do not request results and correcting the dates applies automatically', async () => {
  const ui = mount();
  ui.fields.history_from.value = '2026-12-01'; ui.change('history_from');
  assert.equal(ui.timers.size, 0);
  assert.equal(ui.requests.length, 0);
  assert.equal(ui.fields.history_to.validation, 'End date must be on or after start date.');
  ui.fields.history_to.value = '2026-12-31'; ui.change('history_to');
  assert.equal([...ui.timers.values()][0].delay, 350);
  const task = ui.run(); ui.respond(0, 'December records'); await task;
  assert.equal(new URL(ui.requests[0].url).searchParams.get('history_to'), '2026-12-31');
  assert.equal(ui.fields.history_to.validation, '');
});

test('pagination loads the selected page and changing page size starts again at the first page', async () => {
  const ui = mount();
  let prevented = false;
  const link = {href: 'http://localhost/inventory/25?tab=borrowing-history&history_status=ALL&history_page=2'};
  ui.event('results', 'click', {target: {closest: () => link}, button: 0, preventDefault() { prevented = true; }});
  assert.equal(prevented, true);
  const second = ui.run(); ui.respond(0, 'second page'); await second;
  assert.equal(new URL(ui.history[0]).searchParams.get('history_page'), '2');
  ui.fields.history_per_page.value = '30'; ui.change('history_per_page');
  const larger = ui.run(); ui.respond(1, 'thirty records'); await larger;
  assert.equal(new URL(ui.requests[1].url).searchParams.get('history_per_page'), '30');
  assert.equal(new URL(ui.requests[1].url).searchParams.has('history_page'), false);
});

test('clearing dates restores the existing server defaults instead of showing an empty, misleading range', async () => {
  const ui = mount();
  ui.fields.history_from.value = ''; ui.fields.history_to.value = '';
  ui.change('history_to');
  const task = ui.run();
  assert.equal(new URL(ui.requests[0].url).searchParams.has('history_from'), false);
  ui.respond(0, 'default period records'); await task;
  assert.equal(ui.fields.history_from.value, '2026-10-01');
  assert.equal(ui.fields.history_to.value, '2026-11-30');
});

test('details links and modified pagination clicks retain normal browser navigation', () => {
  const ui = mount();
  let prevented = false;
  ui.event('results', 'click', {target: {closest: () => null}, preventDefault() { prevented = true; }});
  ui.event('results', 'click', {target: {closest: () => ({href: 'http://localhost/inventory/25?history_page=2'})}, ctrlKey: true, preventDefault() { prevented = true; }});
  assert.equal(prevented, false);
  assert.equal(ui.requests.length, 0);
});

test('errors fall back to the regular page and expired sessions open sign-in', async () => {
  const ui = mount(); ui.change('history_status');
  const offline = ui.run(); ui.requests[0].reject(new Error('offline')); await offline;
  assert.equal(new URL(ui.assigned[0]).pathname, '/inventory/25');
  ui.change('history_status');
  const expired = ui.run(); ui.respond(1, 'login', 'http://localhost/login'); await expired;
  assert.equal(ui.assigned[1], 'http://localhost/login');
  assert.deepEqual(ui.results.children, ['original rows']);
});

test('finishing a request after switching tabs does not change the selected tab URL', async () => {
  const ui = mount(); ui.change('history_status');
  const task = ui.run(); ui.panel.hidden = true;
  ui.respond(0, 'updated hidden history'); await task;
  assert.deepEqual(ui.results.children, ['updated hidden history']);
  assert.equal(ui.history.length, 0);
});
