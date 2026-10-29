const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const page = fs.readFileSync(path.join(root, 'resources/views/registrations/index.blade.php'), 'utf8');
const partial = fs.readFileSync(path.join(root, 'resources/views/registrations/partials/filter-interactions.blade.php'), 'utf8');
const script = partial.match(/<script>([\s\S]*?)<\/script>/)[1];

function mount() {
    const handlers = new Map();
    const element = (name) => ({name, value: '', addEventListener(event, fn) { handlers.set(`${name}:${event}`, fn); }});
    const fields = Object.fromEntries(['search', 'type', 'status'].map(name => [name, element(name)]));
    const form = { ...element('form'), action: 'http://localhost/registrations', elements: { namedItem: name => fields[name] } };
    const results = { attributes: {}, children: ['original'], setAttribute(name, value) { this.attributes[name] = value; }, replaceChildren(...children) { this.children = children; } };
    const timers = new Map();
    let timerId = 0;
    const requests = [];
    const assigned = [];
    const history = [];
    vm.runInNewContext(script, {
        URL, AbortController,
        document: { querySelector: () => form, getElementById: () => results },
        window: { location: { href: 'http://localhost/registrations?page=3', assign: url => assigned.push(url) }, history: { state: null, replaceState: (_state, _title, url) => history.push(url) } },
        setTimeout(fn, delay) { const id = ++timerId; timers.set(id, {fn, delay}); return id; },
        clearTimeout(id) { timers.delete(id); },
        fetch(url, options) {
            let resolve, reject;
            const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
            requests.push({url, options, resolve, reject});
            return promise;
        },
        DOMParser: class { parseFromString(text) { return {getElementById: () => text === 'login' ? null : {childNodes: [text]}}; } },
    });
    return {
        fields, form, results, timers, requests, assigned, history,
        event(name, event, payload = {}) { handlers.get(`${name}:${event}`)(payload); },
        run() { const [id, timer] = timers.entries().next().value; timers.delete(id); return timer.fn(); },
        respond(index, html, url = requests[index].url) { requests[index].resolve({ok: true, url, text: async () => html}); },
    };
}

test('Apply filters and Reset are removed while GET filtering remains', () => {
    assert.match(page, /method="get"[^>]*data-registration-filters/);
    assert.doesNotMatch(page, /Apply filters|>Reset<|ictu-users-reset|type="submit"/);
    assert.match(page, /id="registration-results" aria-live="polite"/);
});

test('dropdown changes automatically combine all filters and start at page one', async () => {
    const ui = mount();
    ui.fields.search.value = ' Applicant ';
    ui.fields.type.value = 'EXTERNAL';
    ui.fields.status.value = 'PENDING';
    ui.event('type', 'change');
    assert.equal([...ui.timers.values()][0].delay, 0);
    const task = ui.run();
    const url = new URL(ui.requests[0].url);
    assert.equal(url.searchParams.get('search'), 'Applicant');
    assert.equal(url.searchParams.get('type'), 'EXTERNAL');
    assert.equal(url.searchParams.get('status'), 'PENDING');
    assert.equal(url.searchParams.has('page'), false);
    assert.equal(ui.requests[0].options.method, 'GET');
    assert.equal(ui.requests[0].options.credentials, 'same-origin');
    ui.respond(0, 'filtered rows and pagination');
    await task;
    assert.deepEqual(ui.results.children, ['filtered rows and pagination']);
    assert.equal(ui.results.attributes['aria-busy'], 'false');
    assert.equal(ui.history.length, 1);
    assert.equal(ui.assigned.length, 0);
    assert.equal(ui.fields.search.value, ' Applicant ');
});

test('typing is debounced and clearing all filters removes old query parameters', async () => {
    const ui = mount();
    ui.fields.search.value = 'a'; ui.event('search', 'input');
    ui.fields.search.value = 'app'; ui.event('search', 'input');
    assert.equal(ui.timers.size, 1);
    assert.equal([...ui.timers.values()][0].delay, 350);
    ui.fields.type.value = 'EXTERNAL'; ui.event('type', 'change');
    ui.fields.status.value = 'PENDING'; ui.event('status', 'change');
    ui.fields.search.value = ''; ui.event('search', 'input');
    ui.fields.type.value = ''; ui.event('type', 'change');
    ui.fields.status.value = ''; ui.event('status', 'change');
    const task = ui.run();
    assert.equal(ui.requests[0].url, 'http://localhost/registrations');
    ui.respond(0, 'all records'); await task;
    assert.deepEqual(ui.results.children, ['all records']);
});

test('a slow earlier response never replaces newer results or clears their loading state', async () => {
    const ui = mount();
    ui.fields.type.value = 'EMPLOYEE'; ui.event('type', 'change');
    const oldTask = ui.run();
    ui.fields.type.value = 'EXTERNAL'; ui.event('type', 'change');
    assert.equal(ui.requests[0].options.signal.aborted, true);
    const newTask = ui.run();
    ui.respond(0, 'stale employee results'); await oldTask;
    assert.deepEqual(ui.results.children, ['original']);
    assert.equal(ui.results.attributes['aria-busy'], 'true');
    ui.respond(1, 'new external results'); await newTask;
    assert.deepEqual(ui.results.children, ['new external results']);
    assert.equal(new URL(ui.history[0]).searchParams.get('type'), 'EXTERNAL');
});

test('Enter applies immediately without a second native form submission', async () => {
    const ui = mount();
    ui.fields.search.value = 'company'; ui.event('search', 'input');
    let prevented = false;
    ui.event('form', 'submit', {preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    assert.equal(ui.timers.size, 1);
    assert.equal([...ui.timers.values()][0].delay, 0);
    const task = ui.run(); ui.respond(0, 'company results'); await task;
    assert.equal(ui.requests.length, 1);
});

test('network errors retain the normal GET fallback and expired sessions open the returned login page', async () => {
    const ui = mount();
    ui.fields.status.value = 'APPROVED'; ui.event('status', 'change');
    const failed = ui.run(); ui.requests[0].reject(new Error('offline')); await failed;
    assert.equal(new URL(ui.assigned[0]).searchParams.get('status'), 'APPROVED');
    ui.event('status', 'change');
    const expired = ui.run(); ui.respond(1, 'login', 'http://localhost/login'); await expired;
    assert.equal(ui.assigned[1], 'http://localhost/login');
    assert.deepEqual(ui.results.children, ['original']);
});
