<script>
(() => {
    const workspace = document.querySelector('[data-laundry-tabs]');
    if (!workspace) return;
    const tabs = [...workspace.querySelectorAll('[data-laundry-tab]')];
    const panels = [...workspace.querySelectorAll('[data-laundry-panel]')];
    const activate = (name, focus = false) => {
        tabs.forEach(tab => {
            const selected = tab.dataset.laundryTab === name;
            tab.classList.toggle('is-selected', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.laundryPanel !== name; });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.laundryTab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            activate(tabs[next].dataset.laundryTab, true);
        });
    });
    const url = new URL(window.location.href);
    activate(url.searchParams.has('completed_page') || url.hash === '#completed-laundry-cases' ? 'completed' : 'active');
})();
(() => {
    const browser = document.querySelector('[data-laundry-browser]');
    if (!browser || browser.dataset.laundryBrowserInitialized === '1') return;

    const list = browser.querySelector('[data-laundry-list]');
    const search = browser.querySelector('[data-laundry-search]');
    if (!list || !search) return;
    browser.dataset.laundryBrowserInitialized = '1';

    const records = [...browser.querySelectorAll('[data-laundry-record]')];
    const status = browser.querySelector('[data-laundry-status]');
    const sort = browser.querySelector('[data-laundry-sort]');
    const count = browser.querySelector('[data-laundry-count]');
    const empty = browser.querySelector('[data-laundry-filter-empty]');

    const render = () => {
        const terms = search.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        const selectedStatus = status?.value || '';
        const oldestFirst = sort?.value === 'oldest';
        const ordered = [...records].sort((left, right) => {
            const difference = Number(left.dataset.date || 0) - Number(right.dataset.date || 0);
            return oldestFirst ? difference : -difference;
        });
        let visible = 0;

        ordered.forEach((record) => {
            const haystack = (record.dataset.search || '').toLocaleLowerCase();
            const matches = terms.every((term) => haystack.includes(term))
                && (!selectedStatus || record.dataset.status === selectedStatus);
            record.hidden = !matches;
            list.appendChild(record);
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) {
            const total = count.dataset.total || records.length;
            count.textContent = count.dataset.paginated === 'true'
                ? `Showing ${visible} of ${records.length} cases on this page (${total} total)`
                : `Showing ${visible} of ${total} cases`;
        }
    };

    search.addEventListener('input', render);
    status?.addEventListener('change', render);
    sort?.addEventListener('change', render);
    browser.querySelector('[data-laundry-reset]')?.addEventListener('click', () => {
        search.value = '';
        if (status) status.value = '';
        if (sort) sort.value = 'newest';
        render();
        search.focus();
    });

    render();
})();

(() => {
    const browser = document.querySelector('[data-completed-laundry]');
    if (!browser || browser.dataset.completedLaundryInitialized === '1') return;

    const list = browser.querySelector('[data-completed-list]');
    const search = browser.querySelector('[data-completed-search]');
    const outcome = browser.querySelector('[data-completed-outcome]');
    const sort = browser.querySelector('[data-completed-sort]');
    if (!list || !search || !outcome || !sort) return;
    browser.dataset.completedLaundryInitialized = '1';

    const records = [...browser.querySelectorAll('[data-completed-record]')];
    const empty = browser.querySelector('[data-completed-empty]');
    const count = browser.querySelector('[data-completed-count]');

    const render = () => {
        const terms = search.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        const selectedOutcome = outcome.value;
        const oldestFirst = sort.value === 'oldest';
        const ordered = [...records].sort((left, right) => {
            const difference = Number(left.dataset.created || 0) - Number(right.dataset.created || 0);
            return oldestFirst ? difference : -difference;
        });
        let visible = 0;

        ordered.forEach((record) => {
            const haystack = (record.dataset.search || '').toLocaleLowerCase();
            const outcomes = (record.dataset.outcomes || '').split(' ').filter(Boolean);
            const matches = terms.every((term) => haystack.includes(term))
                && (!selectedOutcome || outcomes.includes(selectedOutcome));
            record.hidden = !matches;
            list.appendChild(record);
            if (matches) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) {
            const total = count.dataset.total || records.length;
            const unfiltered = terms.length === 0 && !selectedOutcome;
            count.textContent = unfiltered
                ? `Showing ${count.dataset.first || 0} to ${count.dataset.last || 0} of ${total} completed cases`
                : (count.dataset.paginated === 'true'
                    ? `Showing ${visible} of ${records.length} cases on this page (${total} total)`
                    : `Showing ${visible} of ${total} completed cases`);
        }
    };

    search.addEventListener('input', render);
    outcome.addEventListener('change', render);
    sort.addEventListener('change', render);
    browser.querySelector('[data-completed-reset]')?.addEventListener('click', () => {
        search.value = '';
        outcome.value = '';
        sort.value = 'newest';
        render();
        search.focus();
    });

    render();
})();
</script>
