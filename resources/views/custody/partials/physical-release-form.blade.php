<form method="post" action="{{ route('custody.release', $custody) }}" class="form-grid release-handover-form" @if(!$preparationComplete || !$pickupWindowOpen) aria-describedby="physical-release-availability" @endif>
    @csrf

    <label class="checkbox">
        <input
            id="physical-signatures-confirmed"
            type="checkbox"
            name="physical_signatures_confirmed"
            value="1"
            required
            autocomplete="off"
            @disabled(!$preparationComplete || !$pickupWindowOpen)
        >
        <span>I confirm that the borrower has received the items and the required forms have been signed.</span>
    </label>

    @error('signature')
        <p class="form-error">{{ $message }}</p>
    @enderror

    <div class="release-handover-footer">
        <label>
            Remarks (Optional)
            <textarea
                name="remarks"
                placeholder="Add a short note, if needed"
                @disabled(!$preparationComplete || !$pickupWindowOpen)
            ></textarea>
        </label>

        <button
            id="confirm-physical-release-button"
            class="button primary ui-pressable release-primary"
            type="submit"
            disabled
        >
            Confirm Release
        </button>
    </div>
</form>

<script>
(() => {
    const confirmation = document.getElementById('physical-signatures-confirmed');
    const releaseButton = document.getElementById('confirm-physical-release-button');

    if (!confirmation || !releaseButton) return;

    const refreshReleaseConfirmation = () => {
        releaseButton.disabled = confirmation.disabled || !confirmation.checked;
    };






    const resetReleaseConfirmation = () => {
        confirmation.checked = false;
        refreshReleaseConfirmation();
    };

    confirmation.addEventListener('change', refreshReleaseConfirmation);
    window.addEventListener('pageshow', resetReleaseConfirmation);

    resetReleaseConfirmation();
})();
</script>
