<script>
(() => {
    const browser = document.querySelector('[data-sao-release-browser]');
    if (!browser || browser.dataset.initialized === '1') return;
    browser.dataset.initialized = '1';
    const rows = Array.from(browser.querySelectorAll('[data-operational-record]'));
    const list = browser.querySelector('#operational-filter-list');
    const search = browser.querySelector('#operational-search');
    const status = browser.querySelector('#operational-status');
    const sort = browser.querySelector('#operational-sort');
    const table = browser.querySelector('[data-release-table-wrap]');
    const empty = browser.querySelector('#operational-filter-empty');
    const footer = browser.querySelector('[data-release-pagination]');
    const size = browser.querySelector('[data-release-page-size]');
    const previous = browser.querySelector('[data-release-previous]');
    const next = browser.querySelector('[data-release-next]');
    const range = browser.querySelector('[data-release-range]');
    const pageLabel = browser.querySelector('[data-release-page]');
    if (!list || !search || !status || !sort || !table || !empty || !footer || !size || !previous || !next || !range || !pageLabel) return;
    let page = 1;
    const render = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const matches = rows.filter(row => (!query || row.dataset.search.toLocaleLowerCase().includes(query))
            && (status.value === 'all' || row.dataset.status === status.value));
        matches.sort((a, b) => ((Number(b.dataset.created) - Number(a.dataset.created)) || (Number(b.dataset.id) - Number(a.dataset.id))) * (sort.value === 'oldest' ? -1 : 1));
        const perPage = [10, 30, 50, 100].includes(Number(size.value)) ? Number(size.value) : 10;
        const totalPages = Math.max(1, Math.ceil(matches.length / perPage));
        page = Math.min(Math.max(1, page), totalPages);
        const start = (page - 1) * perPage;
        rows.forEach(row => { row.hidden = true; });
        matches.forEach((row, index) => { list.appendChild(row); row.hidden = index < start || index >= start + perPage; });
        table.hidden = matches.length === 0;
        empty.hidden = matches.length > 0;
        footer.hidden = matches.length === 0;
        if (matches.length) range.textContent = `Showing ${start + 1}–${Math.min(start + perPage, matches.length)} of ${matches.length} ${matches.length === 1 ? 'record' : 'records'}`;
        pageLabel.textContent = `Page ${page} of ${totalPages}`;
        previous.disabled = page === 1;
        next.disabled = page === totalPages;
    };
    for (const control of [search, status, sort, size]) {
        control.addEventListener(control === search ? 'input' : 'change', () => { page = 1; render(); });
    }
    previous.addEventListener('click', () => { if (page > 1) { page--; render(); } });
    next.addEventListener('click', () => { page++; render(); });
    render();
})();
</script>
