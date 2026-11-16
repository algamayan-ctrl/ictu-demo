/* This is the E-signature confirmation for SPMU Head billing and accountability decisions. */
(() => {
    const dialog = document.querySelector('[data-accountability-signature-dialog]');
    if (!dialog || dialog.dataset.initialized === 'true') return;
    dialog.dataset.initialized = 'true';

    const consent = dialog.querySelector('[data-signature-consent]');
    const confirm = dialog.querySelector('[data-signature-confirm]');
    const cancel = dialog.querySelector('[data-signature-cancel]');
    const unavailable = document.querySelector('[data-signature-unavailable]');
    const forms = [...document.querySelectorAll('form[data-accountability-signature]')];
    const submitting = new WeakSet();
    let pending = null;
    let authorizedForm = null;

    const confirmationInput = form => form.elements.namedItem('confirm_e_signature');
    const formState = form => `${form.action}|${new URLSearchParams(new FormData(form))}`;

    function reset() {
        const previous = pending;
        pending = null;
        consent.checked = false;
        confirm.disabled = true;
        if (previous) {
            confirmationInput(previous.form).value = '';
            if (previous.trigger?.isConnected) previous.trigger.focus();
        }
    }

    function close() {
        reset();
        if (dialog.open) dialog.close();
    }

    function open(form, submitter) {
        close();
        if (unavailable) unavailable.hidden = true;
        const billing = form.dataset.accountabilitySignature === 'billing';
        dialog.querySelector('[data-signature-document-row]').hidden = !billing;
        dialog.querySelector('[data-signature-action-row]').hidden = billing;
        dialog.querySelector('[data-signature-decision-row]').hidden = billing;
        dialog.querySelector('[data-signature-amount-row]').hidden = !billing;
        dialog.querySelector('[data-signature-case]').textContent = form.dataset.signatureCase;
        dialog.querySelector('[data-signature-amount]').textContent = form.dataset.signatureAmount || '';
        dialog.querySelector('[data-signature-decision]').textContent =
            form.elements.namedItem('decision')?.value === 'CLEAR' ? 'Clear Finding' : 'Confirm Accountability';
        dialog.querySelector('[data-signature-consent-copy]').textContent = billing
            ? 'I authorize use of my registered E-signature for this Billing Statement.'
            : 'I authorize use of my registered E-signature for this accountability decision.';
        confirm.textContent = billing ? 'Confirm E-signature & Issue' : 'Confirm E-signature & Record';
        pending = { form, submitter, trigger: submitter || document.activeElement, state: formState(form) };
        try {
            dialog.showModal();
            consent.focus();
        } catch {
            close();
            if (unavailable) unavailable.hidden = false;
        }
    }

    // This is the consent check before the shared form submission lock runs.
    window.addEventListener('submit', event => {
        const form = event.target;
        if (!forms.includes(form)) return;
        if (submitting.has(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        if (authorizedForm === form && confirmationInput(form).value === '1') {
            authorizedForm = null;
            submitting.add(form);
            window.setTimeout(() => {
                submitting.delete(form);
                confirmationInput(form).value = '';
            }, 20000);
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        confirmationInput(form).value = '';
        if (form.reportValidity()) open(form, event.submitter);
    }, true);
    forms.forEach(form => {
        confirmationInput(form).value = '';
        ['input', 'change', 'reset'].forEach(type => form.addEventListener(type, () => {
            if (pending?.form === form) close();
            confirmationInput(form).value = '';
        }));
    });

    consent.addEventListener('change', () => { confirm.disabled = !consent.checked; });
    cancel.addEventListener('click', close);
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('close', () => { if (!dialog.open) reset(); });
    dialog.addEventListener('click', event => { if (event.target === dialog) close(); });
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [consent, cancel, confirm].filter(control => !control.disabled);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault(); first.focus();
        }
    });
    confirm.addEventListener('click', () => {
        if (!pending || !consent.checked || confirm.disabled) return;
        const { form, submitter, state } = pending;
        if (state !== formState(form)) {
            open(form, submitter);
            return;
        }
        close();
        if (!form.reportValidity()) return;
        confirmationInput(form).value = '1';
        authorizedForm = form;
        try {
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
        } finally {
            authorizedForm = null;
            if (!submitting.has(form)) confirmationInput(form).value = '';
        }
    });
    window.addEventListener('pageshow', () => {
        close();
        forms.forEach(form => {
            submitting.delete(form);
            confirmationInput(form).value = '';
        });
    });
})();
