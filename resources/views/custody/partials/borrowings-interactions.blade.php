<script>
(() => {
    /*
     * Search, status, and sort are applied on the server. This script only
     * submits the toolbar form: Status/Sort submit on change, Search submits
     * on Enter or when the field is cleared. The form never sends "page", so
     * any change starts again at page 1.
     */
    const initializeMyBorrowings = () => {
        const form = document.querySelector('[data-my-borrowings] #borrowings-filters');

        if (!form) {
            return;
        }

        const search = form.querySelector('#borrowings-search');
        const status = form.querySelector('#borrowings-status');
        const sort = form.querySelector('#borrowings-sort');
        const initialSearch = search ? search.value : '';

        // Keep the URL tidy: leave out empty/default values.
        form.addEventListener('formdata', (event) => {
            const data = event.formData;

            if ((data.get('search') || '').trim() === '') data.delete('search');
            if (data.get('status') === 'all') data.delete('status');
            if (data.get('sort') === 'newest') data.delete('sort');
        });

        const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();

        status?.addEventListener('change', submit);
        sort?.addEventListener('change', submit);

        // The search field's clear (x) button fires "search" with an empty value.
        search?.addEventListener('search', () => {
            if (search.value === '' && initialSearch !== '') {
                submit();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMyBorrowings, { once: true });
    } else {
        initializeMyBorrowings();
    }
})();
</script>
