<script>
document.addEventListener('DOMContentLoaded', () => {
<<<<<<< HEAD
    const list = document.getElementById('borrower-request-list');

    if (!list) {
        return;
    }

    const searchInput = document.getElementById('request-search');
    const statusFilter = document.getElementById('request-status-filter');
    const sortSelect = document.getElementById('request-sort');
    const cards = Array.from(list.querySelectorAll('[data-request-card]'));
    const countLabel = document.querySelector('[data-request-count]');
    const emptyState = document.getElementById('request-filter-empty');
    const resetButton = document.getElementById('request-filter-reset');





    const applyFilters = () => {
        const query = (searchInput?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || 'all';
=======
    /*
     * Search, status, and sort are applied on the server across every page.
     * This only submits the toolbar form: Status/Sort submit on change, Search
     * submits on Enter or when the field is cleared. The form never sends
     * "page", so any change starts again at page 1.
     */
    const filterForm = document.getElementById('request-filters');
>>>>>>> d84b9c82ef32f6880f6308ba6018516d3d784027

    if (filterForm) {
        const searchInput = filterForm.querySelector('#request-search');
        const initialSearch = searchInput ? searchInput.value : '';
        const submitFilters = () => filterForm.requestSubmit ? filterForm.requestSubmit() : filterForm.submit();

        // Keep the URL tidy: leave out empty/default values.
        filterForm.addEventListener('formdata', (event) => {
            const data = event.formData;

            if ((data.get('search') || '').trim() === '') data.delete('search');
            if (data.get('status') === 'all') data.delete('status');
            if (data.get('sort') === 'newest') data.delete('sort');
        });

<<<<<<< HEAD
        if (countLabel) {





            const unfiltered = !query && status === 'all';
            const shown = unfiltered ? Number(countLabel.dataset.total) : visible;
=======
        filterForm.querySelector('#request-status-filter')?.addEventListener('change', submitFilters);
        filterForm.querySelector('#request-sort')?.addEventListener('change', submitFilters);
>>>>>>> d84b9c82ef32f6880f6308ba6018516d3d784027

        // The search field's clear (x) button fires "search" with an empty value.
        searchInput?.addEventListener('search', () => {
            if (searchInput.value === '' && initialSearch !== '') {
                submitFilters();
            }
        });
    }


    const closeMenus = (except = null) => {
        document.querySelectorAll('[data-request-menu]').forEach((menu) => {
            if (menu === except) {
                return;
            }

            menu.querySelector('[data-request-menu-panel]').hidden = true;
            menu.querySelector('[data-request-menu-trigger]').setAttribute('aria-expanded', 'false');
        });
    };

    document.querySelectorAll('[data-request-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-request-menu-trigger]');
        const panel = menu.querySelector('[data-request-menu-panel]');

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();

            const willOpen = panel.hidden;

            closeMenus(menu);
            panel.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', () => closeMenus());

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    document.querySelectorAll('[data-copy-reference]').forEach((button) => {
        button.addEventListener('click', async () => {
            const reference = button.dataset.copyReference;
            const original = button.textContent;

            try {
                await navigator.clipboard.writeText(reference);
                button.textContent = 'Copied';
            } catch (error) {
                button.textContent = reference;
            }

            setTimeout(() => {
                button.textContent = original;
                closeMenus();
            }, 1100);
        });
    });
});
</script>

