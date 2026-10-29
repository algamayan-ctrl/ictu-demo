<div data-pickup-confirmation>
    @error('confirm_pickup')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
    @error('borrower_present')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
    <form method="post" action="{{ route('custody.reschedule-pickup', $custody) }}" class="release-schedule-review-form" data-pickup-confirmation-form>
        @csrf
        <input type="hidden" name="confirm_pickup" value="0">
        <input type="hidden" name="borrower_present" value="1">
        <button class="button primary ui-pressable release-primary" type="button" data-pickup-confirmation-open>Confirm Pickup</button>
    </form>
    <dialog class="release-pickup-dialog" aria-labelledby="pickup-confirmation-title" data-pickup-confirmation-dialog>
        <h2 id="pickup-confirmation-title">Confirm Pickup</h2>
        <p>The borrower is present and will proceed with pickup. Confirm?</p>
        <div class="release-pickup-dialog-actions">
            <button class="button secondary small" type="button" data-pickup-confirmation-back>Go Back</button>
            <button class="button primary small" type="button" data-pickup-confirmation-save>Confirm Pickup</button>
        </div>
    </dialog>
</div>
<script>
(() => {
    const workspace = document.querySelector('[data-pickup-confirmation]');
    if (!workspace || workspace.dataset.initialized === '1') return;
    workspace.dataset.initialized = '1';
    const form = workspace.querySelector('[data-pickup-confirmation-form]');
    const dialog = workspace.querySelector('[data-pickup-confirmation-dialog]');
    const open = workspace.querySelector('[data-pickup-confirmation-open]');
    const back = workspace.querySelector('[data-pickup-confirmation-back]');
    const save = workspace.querySelector('[data-pickup-confirmation-save]');
    const confirmation = form.elements.namedItem('confirm_pickup');
    let submitting = false;
    const reset = () => { if (!submitting) { confirmation.value = '0'; open.focus(); } };
    const show = () => { confirmation.value = '0'; dialog.showModal(); back.focus(); };
    open.addEventListener('click', show);
    back.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', reset);
    form.addEventListener('submit', event => {
        if (confirmation.value !== '1') { event.preventDefault(); show(); }
    });
    save.addEventListener('click', () => {
        if (submitting) return;
        submitting = true;
        confirmation.value = '1';
        save.disabled = true;
        open.disabled = true;
        dialog.close();
        form.requestSubmit();
    });
    window.addEventListener('pageshow', () => {
        submitting = false; confirmation.value = '0'; save.disabled = false; open.disabled = false;
        if (dialog.open) dialog.close();
    });
})();
</script>
