{{-- This is the E-signature confirmation dialog for SPMU Head accountability actions. --}}
<style>
    .accountability-signature-dialog {
        box-sizing: border-box;
        width: min(460px, calc(100vw - 32px));
        max-height: calc(100dvh - 32px);
        overflow: auto;
        padding: 20px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--surface-elevated);
        color: var(--text);
        box-shadow: var(--shadow);
    }
    .accountability-signature-dialog::backdrop { background: rgba(7, 27, 53, .48); }
    .accountability-signature-dialog h2 { margin: 0 0 14px; font-size: 20px; }
    .accountability-signature-dialog dl { display: grid; gap: 8px; margin: 0 0 16px; }
    .accountability-signature-dialog dl > div { display: grid; grid-template-columns: 85px minmax(0, 1fr); gap: 10px; }
    .accountability-signature-dialog [hidden] { display: none !important; }
    .accountability-signature-dialog dt { color: var(--text-muted); }
    .accountability-signature-dialog dd { margin: 0; overflow-wrap: anywhere; }
    .accountability-signature-dialog .accountability-signature-consent {
        display: flex; align-items: flex-start; gap: 10px; margin: 0; font-weight: 400; line-height: 1.5;
    }
    .accountability-signature-consent input { flex: 0 0 auto; width: 18px; height: 18px; margin: 2px 0 0; }
    .accountability-signature-dialog .accountability-signature-help { margin: 10px 0 16px; color: var(--text-muted); font-size: 12px; }
    .accountability-signature-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; }
    .accountability-signature-actions .button { white-space: normal; }
    @media (max-width: 480px) {
        .accountability-signature-dialog { padding: 16px; }
        .accountability-signature-actions { display: grid; grid-template-columns: 1fr; }
    }
</style>
<dialog class="accountability-signature-dialog" data-accountability-signature-dialog aria-labelledby="accountability-signature-title" aria-describedby="accountability-signature-help">
    <h2 id="accountability-signature-title">Confirm E-signature</h2>
    <dl>
        <div data-signature-document-row><dt>Document</dt><dd>Late Return Billing Statement</dd></div>
        <div data-signature-action-row hidden><dt>Action</dt><dd>Record accountability decision</dd></div>
        <div><dt>Case</dt><dd data-signature-case></dd></div>
        <div data-signature-decision-row hidden><dt>Decision</dt><dd data-signature-decision></dd></div>
        <div><dt>Signatory</dt><dd>{{ auth()->user()->full_name }}</dd></div>
        <div data-signature-amount-row><dt>Amount</dt><dd data-signature-amount></dd></div>
    </dl>
    <label class="accountability-signature-consent">
        <input type="checkbox" data-signature-consent autofocus>
        <span data-signature-consent-copy></span>
    </label>
    <p class="accountability-signature-help" id="accountability-signature-help">Nothing is saved or signed until you confirm.</p>
    <div class="accountability-signature-actions">
        <button type="button" class="button secondary small" data-signature-cancel>Cancel</button>
        <button type="button" class="button primary small" data-signature-confirm disabled>Confirm E-signature</button>
    </div>
</dialog>
<p class="field-error" data-signature-unavailable role="alert" hidden>E-signature confirmation could not open. Please reload this page and try again. Nothing has been saved or signed.</p>
<script src="{{ \App\Support\StaticAsset::url('js/accountability-signature-confirmation.js') }}?v={{ filemtime(public_path('js/accountability-signature-confirmation.js')) }}" defer></script>
