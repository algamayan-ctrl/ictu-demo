(() => {
    'use strict';

    const uploadMessage = (files, limit, label) => {
        if (!(limit > 0) || !Array.from(files || []).some(file => file.size > limit)) return '';
        return `${label} must be ${limit / (1024 * 1024)} MB or smaller. Choose a smaller file.`;
    };

    if (typeof module !== 'undefined' && module.exports) module.exports = {uploadMessage};
    if (typeof document === 'undefined') return;

    // This is the supporting-document size check before a Borrower submits or saves a request.
    const initialize = () => {
        const form = document.getElementById('request-form');
        if (!form) return;
        const inputs = Array.from(form.querySelectorAll('[data-request-upload-limit]'));
        const validate = input => {
            const message = input.disabled ? '' : uploadMessage(input.files, Number(input.dataset.requestUploadLimit), input.dataset.uploadLabel || 'Supporting document');
            const error = document.getElementById(input.getAttribute('aria-describedby'));
            input.setCustomValidity(message);
            if (message) input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
            if (error) {
                error.textContent = message;
                error.hidden = !message;
            }
            return !message;
        };
        inputs.forEach(input => {
            input.addEventListener('input', () => validate(input));
            input.addEventListener('change', () => validate(input));
            validate(input);
        });
        form.addEventListener('submit', event => {
            let firstInvalid = null;
            inputs.forEach(input => { if (!validate(input)) firstInvalid ||= input; });
            if (!firstInvalid) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            firstInvalid.reportValidity();
            firstInvalid.focus();
        }, true);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
