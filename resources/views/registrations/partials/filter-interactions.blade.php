<script>
(() => {
    const form = document.querySelector('[data-registration-filters]');
    const results = document.getElementById('registration-results');
    if (!form || !results) return;

    const search = form.elements.namedItem('search');
    const type = form.elements.namedItem('type');
    const status = form.elements.namedItem('status');
    let timer;
    let controller;
    let revision = 0;

    const filterUrl = () => {
        const url = new URL(form.action, window.location.href);
        for (const field of [search, type, status]) {
            if (field.value.trim()) url.searchParams.set(field.name, field.value.trim());
        }
        return url;
    };

    const loadResults = async (url, version) => {
        const request = new AbortController();
        controller = request;
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url.href, {
                method: 'GET', credentials: 'same-origin', signal: request.signal,
                headers: { 'Accept': 'text/html' },
            });
            if (version !== revision) return;
            if (!response.ok) throw new Error('Registration results unavailable');
            const html = await response.text();
            if (version !== revision) return;
            const incoming = new DOMParser().parseFromString(html, 'text/html').getElementById('registration-results');
            if (!incoming) {
                window.location.assign(response.url || url.href);
                return;
            }
            results.replaceChildren(...incoming.childNodes);
            window.history.replaceState(window.history.state, '', url.href);
        } catch (error) {
            if (version === revision && error.name !== 'AbortError') window.location.assign(url.href);
        } finally {
            if (version === revision) {
                controller = null;
                results.setAttribute('aria-busy', 'false');
            }
        }
    };

    const refresh = (delay = 0) => {
        clearTimeout(timer);
        controller?.abort();
        const version = ++revision;
        timer = setTimeout(() => loadResults(filterUrl(), version), delay);
    };

    search.addEventListener('input', () => refresh(350));
    for (const field of [type, status]) field.addEventListener('change', () => refresh());
    form.addEventListener('submit', event => {
        event.preventDefault();
        refresh();
    });
})();
</script>
