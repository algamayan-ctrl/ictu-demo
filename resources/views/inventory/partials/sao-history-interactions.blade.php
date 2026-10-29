<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('sao-borrowing-history-filters');
    const results = document.getElementById('sao-borrowing-history-results');
    if (!form || !results) return;
    const from = form.elements.namedItem('history_from');
    const to = form.elements.namedItem('history_to');
    let timer;
    let controller;
    let revision = 0;
    const filterUrl = () => {
        const url = new URL(form.action, window.location.href);
        for (const [name, value] of new FormData(form)) {
            if (String(value).trim()) url.searchParams.set(name, String(value).trim());
        }
        return url;
    };
    const load = async (url, version) => {
        const request = new AbortController();
        controller = request;
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url.href, {
                method: 'GET', credentials: 'same-origin', signal: request.signal, headers: { Accept: 'text/html' },
            });
            if (version !== revision) return;
            if (!response.ok) throw new Error('Borrowing history unavailable');
            const html = await response.text();
            if (version !== revision) return;
            const incomingDocument = new DOMParser().parseFromString(html, 'text/html');
            const incoming = incomingDocument.getElementById('sao-borrowing-history-results');
            if (!incoming) {
                window.location.assign(response.url || url.href);
                return;
            }
            results.replaceChildren(...incoming.childNodes);
            for (const field of [from, to]) {
                if (!field.value && !url.searchParams.has(field.name)) {
                    const defaultField = incomingDocument.querySelector(`#sao-borrowing-history-filters [name="${field.name}"]`);
                    if (defaultField) field.value = defaultField.value;
                }
            }
            to.min = from.value;
            const panel = document.querySelector('[data-inventory-panel="borrowing-history"]');
            if (!panel || !panel.hidden) window.history.replaceState(window.history.state, '', url.href);
        } catch (error) {
            if (version === revision && error.name !== 'AbortError') window.location.assign(url.href);
        } finally {
            if (version === revision) {
                controller = null;
                results.setAttribute('aria-busy', 'false');
            }
        }
    };
    const refresh = (delay = 0, url = filterUrl()) => {
        clearTimeout(timer);
        controller?.abort();
        const version = ++revision;
        to.min = from.value;
        to.setCustomValidity(from.value && to.value && to.value < from.value
            ? 'End date must be on or after start date.' : '');
        if (!form.reportValidity()) {
            results.setAttribute('aria-busy', 'false');
            return;
        }
        timer = setTimeout(() => load(url, version), delay);
    };
    form.querySelector('input[type="search"]').addEventListener('input', () => refresh(500));
    form.addEventListener('change', event => {
        if (event.target.name === 'history_status' || event.target.name === 'history_per_page') refresh();
        if (event.target.name === 'history_from' || event.target.name === 'history_to') refresh(350);
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        refresh();
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('nav[aria-label="Borrowing history pages"] a');
        if (!link || !results.contains(link) || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || (event.button ?? 0) !== 0) return;
        event.preventDefault();
        refresh(0, new URL(link.href, window.location.href));
    });
});
</script>
