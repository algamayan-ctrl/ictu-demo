
<fieldset class="request-premises-panel" id="request-premises-panel">
    <legend>Premises</legend>

    <div class="request-premises-options">
        <label class="request-premises-option">
            <input
                id="request-on-campus-toggle"
                type="radio"
                name="request_premises"
                value="ON_CAMPUS"
                @checked(!$requestUsesOffCampus)
            >
            <span>On-campus</span>
        </label>

        <label class="request-premises-option">
            <input
                id="request-off-campus-toggle"
                type="radio"
                name="request_premises"
                value="OFF_CAMPUS"
                @checked($requestUsesOffCampus)
                aria-describedby="request-premises-help"
            >
            <span>Off-campus</span>
        </label>
    </div>

    <p id="request-premises-help">
        Off-campus borrowing is limited to Barricade and requires a Gate Pass.
    </p>
</fieldset>

<style>
    {{-- /* Hide helper when On-campus is selected. */ --}}
    #request-premises-panel #request-premises-help {
        display: none;
        margin-top: 8px;
    }

    {{-- /* Show helper only when Off-campus is selected. */ --}}
    #request-premises-panel:has(#request-off-campus-toggle:checked)
    #request-premises-help {
        display: block;
    }
</style>
