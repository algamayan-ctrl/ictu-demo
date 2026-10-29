<style>
.document-viewer-actions { display: flex; justify-content: flex-end; padding: 8px 12px; border-bottom: 1px solid var(--border); }
.scanned-document-card.is-document-fullscreen[data-document-preview-card] {
    position: fixed;
    inset: 0;
    z-index: 20000;
    display: flex;
    flex-direction: column;
    width: 100vw;
    height: 100dvh;
    max-width: none;
    max-height: none;
    margin: 0;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: var(--surface-elevated);
}
.scanned-document-card.is-document-fullscreen .document-viewer-actions { flex: 0 0 auto; }
.scanned-document-card.is-document-fullscreen .scanned-document-header,
.scanned-document-card.is-document-fullscreen .scanned-document-reference { display: none; }
.scanned-document-card.is-document-fullscreen[data-preview-orientation] .scanned-pdf-stage,
.scanned-document-card.is-document-fullscreen[data-preview-orientation] .scanned-image-stage {
    flex: 1 1 auto;
    height: 0 !important;
    min-height: 0 !important;
    max-height: none !important;
}
.scanned-document-card.is-document-fullscreen .scanned-image-viewer { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
.scanned-document-card.is-document-fullscreen .scanned-image-toolbar { flex: 0 0 auto; }
@media print {
    .document-viewer-actions { display: none; }
    .scanned-document-card.is-document-fullscreen[data-document-preview-card] { position: static; width: auto; height: auto; }
}
</style>
<script>
(() => {
    const initialize = () => document.querySelectorAll('[data-document-fullscreen-toggle]').forEach((toggle) => {
        if (toggle.dataset.fullscreenReady === '1') return;
        toggle.dataset.fullscreenReady = '1';
        const card = toggle.closest('[data-document-preview-card]');
        if (!card) return;
        let expanded = false;
        let previousOverflow = '';
        let previousRole = null;
        let previousModal = null;
        let previousLabel = null;
        let inertElements = [];

        const close = () => {
            if (!expanded) return;
            expanded = false;
            card.classList.remove('is-document-fullscreen');
            toggle.textContent = 'Full Screen';
            toggle.setAttribute('aria-expanded', 'false');
            for (const [name, value] of [['role', previousRole], ['aria-modal', previousModal], ['aria-label', previousLabel]]) {
                if (value === null) card.removeAttribute(name);
                else card.setAttribute(name, value);
            }
            inertElements.forEach(([element, wasInert]) => { element.inert = wasInert; });
            inertElements = [];
            document.body.style.overflow = previousOverflow;
            if (document.fullscreenElement === card) document.exitFullscreen?.().catch(() => {});
            toggle.focus();
        };

        toggle.addEventListener('click', () => {
            if (expanded) { close(); return; }
            expanded = true;
            previousOverflow = document.body.style.overflow;
            previousRole = card.getAttribute('role');
            previousModal = card.getAttribute('aria-modal');
            previousLabel = card.getAttribute('aria-label');
            for (let element = card; element && element !== document.body; element = element.parentElement) {
                if (!element.parentElement) break;
                Array.from(element.parentElement.children).forEach((sibling) => {
                    if (sibling !== element) { inertElements.push([sibling, sibling.inert]); sibling.inert = true; }
                });
            }
            card.classList.add('is-document-fullscreen');
            card.setAttribute('role', 'dialog');
            card.setAttribute('aria-modal', 'true');
            card.setAttribute('aria-label', 'Document full screen');
            toggle.textContent = 'Exit Full Screen';
            toggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            toggle.focus();
            card.requestFullscreen?.().then(() => {
                if (!expanded && document.fullscreenElement === card) document.exitFullscreen?.().catch(() => {});
            }).catch(() => {});
        });

        document.addEventListener('fullscreenchange', () => {
            if (expanded && !document.fullscreenElement) close();
        });
        document.addEventListener('keydown', (event) => {
            if (!expanded) return;
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key !== 'Tab') return;
            const controls = Array.from(card.querySelectorAll('button:not([disabled]), a[href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])'))
                .filter((element) => element.getClientRects().length > 0);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        });
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
</script>
