<script>

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-case-toggle]');
    if (!button) return;

    const row = button.closest('tr');
    const detail = row?.nextElementSibling;
    if (!detail?.classList.contains('accountability-case-detail-row')) return;

    const wasExpanded = detail.dataset.expanded === 'true';
    const table = row.closest('table');

    if (!wasExpanded && table) {
        table.querySelectorAll('[data-case-toggle][aria-expanded="true"]').forEach((otherButton) => {
            if (otherButton === button) return;

            const otherRow = otherButton.closest('tr');
            const otherDetail = otherRow?.nextElementSibling;
            if (otherDetail?.classList.contains('accountability-case-detail-row')) {
                otherDetail.dataset.expanded = 'false';
                otherDetail.hidden = true;
            }

            otherButton.setAttribute('aria-expanded', 'false');
            const otherLabel = otherButton.querySelector('span');
            if (otherLabel) otherLabel.textContent = 'View Details';
        });
    }

    const willExpand = !wasExpanded;
    detail.dataset.expanded = String(willExpand);
    detail.hidden = !willExpand;
    button.setAttribute('aria-expanded', String(willExpand));

    const label = button.querySelector('span');
    if (label) label.textContent = willExpand ? 'Hide Details' : 'View Details';
});

(() => {
    const body = document.getElementById('accountability-cases-body');
    const empty = document.getElementById('accountability-cases-none');
    if (!body || !empty) return;

    const rows = [...body.querySelectorAll('[data-case]')];
    if (rows.length === 0) return;

    const search = document.getElementById('accountability-case-search');
    const typeSelect = document.getElementById('accountability-case-type');
    const statusSelect = document.getElementById('accountability-case-status');
    const count = document.getElementById('accountability-case-count');
    const focus = new URLSearchParams(location.search).get('focus');

    const rowMatchesType = (row, type) => {
        if (!type || type === 'all') return true;
        if (type === 'RESTRICTION') return row.dataset.hasRestriction === '1';
        return row.dataset.caseType === type;
    };



    function syncStatusOptions() {
        if (!statusSelect) return;

        const type = typeSelect?.value || 'all';
        const eligibleRows = rows.filter(row => rowMatchesType(row, type));

        [...statusSelect.options].forEach(option => {
            if (option.value === 'all') {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const available = eligibleRows.some(row => row.dataset.status === option.value);
            option.hidden = !available;
            option.disabled = !available;
        });

        const selected = statusSelect.selectedOptions[0];
        if (selected?.disabled || selected?.hidden) {
            statusSelect.value = 'all';
        }
    }

    function filterCases() {
        const query = search?.value.trim().toLocaleLowerCase() || '';
        const type = typeSelect?.value || 'all';
        const status = statusSelect?.value || 'all';
        let visible = 0;

        rows.forEach(row => {
            const matchesSearch = !query || (row.dataset.search || '').toLocaleLowerCase().includes(query);
            const matchesType = rowMatchesType(row, type);
            const matchesStatus = status === 'all' || row.dataset.status === status;
            const matchesFocus = focus !== 'balance' || row.dataset.hasBalance === '1';
            const matches = matchesSearch && matchesType && matchesStatus && matchesFocus;

            row.hidden = !matches;

            const detail = row.nextElementSibling;
            if (detail?.classList.contains('accountability-case-detail-row')) {
                detail.hidden = !matches || detail.dataset.expanded !== 'true';
            }

            if (matches) visible++;
        });

        empty.hidden = visible > 0;
        if (count) count.textContent = String(visible);
    }



    if (focus === 'overdue') {
        if (typeSelect && [...typeSelect.options].some(option => option.value === 'LATE_RETURN')) {
            typeSelect.value = 'LATE_RETURN';
        }
        syncStatusOptions();
        if (statusSelect && [...statusSelect.options].some(option => option.value === 'OVERDUE' && !option.disabled)) {
            statusSelect.value = 'OVERDUE';
        }
    } else if (focus === 'late') {
        if (typeSelect && [...typeSelect.options].some(option => option.value === 'LATE_RETURN')) {
            typeSelect.value = 'LATE_RETURN';
        }
        if (statusSelect) statusSelect.value = 'all';
        syncStatusOptions();
    } else if (focus === 'restrictions') {
        if (typeSelect && [...typeSelect.options].some(option => option.value === 'RESTRICTION')) {
            typeSelect.value = 'RESTRICTION';
        }
        if (statusSelect) statusSelect.value = 'all';
        syncStatusOptions();
    } else {
        syncStatusOptions();
    }

    search?.addEventListener('input', filterCases);
    typeSelect?.addEventListener('change', () => {
        syncStatusOptions();
        filterCases();
    });
    statusSelect?.addEventListener('change', filterCases);

    filterCases();
})();
</script>
