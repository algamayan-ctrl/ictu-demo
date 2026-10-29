const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/components/document-viewer-fullscreen.blade.php'), 'utf8');
const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];

function mount({ loading = false, rejectNative = false, native = false } = {}) {
    const events = new Map();
    const sibling = { inert: true };
    const sidebar = { inert: false };
    const body = { style: {overflow: 'auto'}, children: [] };
    const container = { parentElement: body, children: [] };
    const cardAttributes = new Map([['role', 'region'], ['aria-label', 'Original document']]);
    const buttonEvents = new Map();
    const buttonAttributes = new Map();
    const classes = new Set();
    const toggle = {
        dataset: {}, textContent: 'Full Screen',
        getClientRects: () => [1],
        addEventListener: (name, handler) => buttonEvents.set(name, handler),
        setAttribute: (name, value) => buttonAttributes.set(name, value),
        focus: () => { document.activeElement = toggle; },
    };
    const frame = {getClientRects: () => [1], focus: () => { document.activeElement = frame; }};
    const card = {
        parentElement: container,
        classList: {add: value => classes.add(value), remove: value => classes.delete(value)},
        getAttribute: name => cardAttributes.get(name) ?? null,
        setAttribute: (name, value) => cardAttributes.set(name, value),
        removeAttribute: name => cardAttributes.delete(name),
        querySelectorAll: () => [toggle, frame],
    };
    toggle.closest = () => card;
    body.children = [container, sidebar];
    container.children = [card, sibling];
    const document = {
        readyState: loading ? 'loading' : 'complete', body, activeElement: null, fullscreenElement: null,
        querySelectorAll: () => [toggle],
        addEventListener(name, handler) { events.set(name, [...(events.get(name) || []), handler]); },
        exitFullscreen: () => { document.fullscreenElement = null; return Promise.resolve(); },
    };
    if (native) card.requestFullscreen = () => {
        if (rejectNative) return Promise.reject(new Error('Fullscreen unavailable'));
        document.fullscreenElement = card;
        return Promise.resolve();
    };
    const context = {document};
    vm.runInNewContext(script, context);
    return {
        card, toggle, frame, classes, document, cardAttributes, buttonAttributes, sibling, sidebar, context,
        click: () => buttonEvents.get('click')?.(),
        emit: (name, event = {}) => (events.get(name) || []).forEach(handler => handler(event)),
        handlerCount: () => events.get('keydown')?.length || 0,
    };
}

test('full-screen fallback expands the same viewer and restores focus, attributes and existing inert state', () => {
    const ui = mount();
    ui.click();
    assert.equal(ui.classes.has('is-document-fullscreen'), true);
    assert.equal(ui.cardAttributes.get('role'), 'dialog');
    assert.equal(ui.buttonAttributes.get('aria-expanded'), 'true');
    assert.equal(ui.sidebar.inert, true);
    assert.equal(ui.document.body.style.overflow, 'hidden');
    ui.click();
    assert.equal(ui.classes.size, 0);
    assert.equal(ui.cardAttributes.get('role'), 'region');
    assert.equal(ui.cardAttributes.get('aria-label'), 'Original document');
    assert.equal(ui.cardAttributes.has('aria-modal'), false);
    assert.equal(ui.sidebar.inert, false);
    assert.equal(ui.sibling.inert, true);
    assert.equal(ui.document.body.style.overflow, 'auto');
    assert.equal(ui.document.activeElement, ui.toggle);
});

test('Escape closes the expanded preview and keyboard navigation stays inside it', () => {
    const ui = mount();
    ui.click();
    let prevented = 0;
    ui.emit('keydown', {key: 'Tab', shiftKey: true, preventDefault() { prevented++; }});
    assert.equal(ui.document.activeElement, ui.frame);
    ui.emit('keydown', {key: 'Tab', shiftKey: false, preventDefault() { prevented++; }});
    assert.equal(ui.document.activeElement, ui.toggle);
    ui.emit('keydown', {key: 'Escape', preventDefault() { prevented++; }});
    assert.equal(prevented, 3);
    assert.equal(ui.classes.size, 0);
});

test('native fullscreen exit restores the normal preview without changing the file', async () => {
    const ui = mount({native: true});
    ui.click();
    await Promise.resolve();
    assert.equal(ui.document.fullscreenElement, ui.card);
    ui.document.fullscreenElement = null;
    ui.emit('fullscreenchange');
    assert.equal(ui.toggle.textContent, 'Full Screen');
    assert.equal(ui.classes.size, 0);
    assert.equal(ui.sidebar.inert, false);
    assert.doesNotMatch(script, /fetch\(|XMLHttpRequest|\.src\s*=|innerHTML\s*=/);
});

test('a browser refusing native fullscreen retains a working expand and exit button', async () => {
    const ui = mount({native: true, rejectNative: true});
    ui.click();
    await Promise.resolve();
    await Promise.resolve();
    assert.equal(ui.classes.has('is-document-fullscreen'), true);
    ui.click();
    assert.equal(ui.classes.size, 0);
});

test('initialization waits for all document cards and does not register duplicate controls', () => {
    const ui = mount({loading: true});
    ui.click();
    assert.equal(ui.classes.size, 0);
    ui.emit('DOMContentLoaded');
    ui.emit('DOMContentLoaded');
    vm.runInNewContext(script, ui.context);
    ui.emit('DOMContentLoaded');
    assert.equal(ui.handlerCount(), 1);
    ui.click();
    assert.equal(ui.classes.has('is-document-fullscreen'), true);
});
