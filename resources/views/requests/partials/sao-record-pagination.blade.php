<script>
(() => {
    const rows = Array.from(document.querySelectorAll('[data-request-record]'));
    const search = document.querySelector('[data-record-search]');
    const status = document.querySelector('[data-record-status-filter]');
    const sort = document.querySelector('[data-record-sort]');
    const empty = document.querySelector('[data-record-empty]');
    const pagination = document.querySelector('[data-sao-record-pagination]');
    if (!rows.length || !search || !status || !sort || !pagination) return;
    const previous = pagination.querySelector('[data-sao-record-previous]');
    const next = pagination.querySelector('[data-sao-record-next]');
    const range = pagination.querySelector('[data-sao-record-range]');
    const pageLabel = pagination.querySelector('[data-sao-record-page]');
    const body = rows[0].parentElement;
    const pageSize = 10;
    let page = 1;

    const render = () => {
        const term = search.value.trim().toLowerCase();
        const selectedStatus = status.value;
        const matches = rows.filter(row => {
            const matchesStatus = selectedStatus === 'all'
                || (selectedStatus === 'NEEDS_ATTENTION' ? row.dataset.attention === '1' : row.dataset.status === selectedStatus);
            return (!term || row.dataset.search.toLowerCase().includes(term)) && matchesStatus;
        }).sort((a, b) => (Number(b.dataset.created) - Number(a.dataset.created)) * (sort.value === 'newest' ? 1 : -1));
        const totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
        page = Math.min(Math.max(1, page), totalPages);
        const start = (page - 1) * pageSize;
        rows.forEach(row => { row.hidden = true; });
        matches.forEach((row, index) => { body.appendChild(row); row.hidden = index < start || index >= start + pageSize; });
        if (empty) empty.hidden = matches.length > 0;
        pagination.hidden = matches.length === 0;
        range.textContent = `${matches.length ? start + 1 : 0}–${Math.min(start + pageSize, matches.length)} of ${matches.length} requests`;
        pageLabel.textContent = `Page ${page} of ${totalPages}`;
        previous.disabled = page === 1;
        next.disabled = page === totalPages;
    };
    [search, status, sort].forEach(control => control.addEventListener(control === search ? 'input' : 'change', () => { page = 1; render(); }));
    previous.addEventListener('click', () => { if (page > 1) { page--; render(); } });
    next.addEventListener('click', () => { page++; render(); });
    render();
})();
</script>
