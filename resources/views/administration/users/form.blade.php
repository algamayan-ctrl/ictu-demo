@extends('layouts.app', ['title' => $user->exists ? 'Edit institutional account' : 'Register a CSPC account'])
@section('content')
@php
    $isSelfProtected = auth()->check() && auth()->id() === $user->id;
    $selectedUnitId = old('organizational_unit_id', $user->organizational_unit_id);
    $newUnitName = old('new_organizational_unit_name', '');
    $addingNewUnit = filled($newUnitName) || old('organizational_unit_id') === '__NEW__';
    $historicalPrimaryUnit = $historicalPrimaryUnit ?? null;
    $historicalAdditionalUnits = $historicalAdditionalUnits ?? collect();
    $selectedAdditionalUnitIds = collect(old('additional_organizational_unit_ids', $additionalUnitIds ?? []))
        ->map(fn ($id) => (int) $id)->unique()->values();
@endphp

<section class="page-heading ictu-form-page-heading">
    <div>
        <p class="eyebrow">ICTU identity administration</p>
        <h1>{{ $user->exists ? 'Edit institutional account' : 'Register a CSPC account' }}</h1>
        <p>ICTU may register eligible employees, faculty, and staff. Institutional email verification is required before sign-in, and the selected classification controls borrowing eligibility and portal access.</p>
    </div>
</section>

<section class="content-area ictu-user-form-area">
    <form method="post" action="{{ $user->exists ? route('administration.users.update', $user) : route('administration.users.store') }}" class="ictu-user-form">
        @csrf
        @if($user->exists) @method('PUT') @endif

        <section class="ictu-form-card" aria-labelledby="ictu-identity-heading">
            <header class="ictu-form-section-header">
                <h2 id="ictu-identity-heading">Account Identity</h2>
                <p>{{ $user->exists ? 'Saved official account details are loaded automatically.' : 'Enter the employee\'s official CSPC account details.' }}</p>
            </header>
            <div class="ictu-field-grid ictu-field-grid-two">
                <label class="ictu-field" for="ictu-employee-number">
                    <span>Employee number</span>
                    <input id="ictu-employee-number" name="employee_no" value="{{ old('employee_no', $user->employee_no) }}" placeholder="Enter employee number" autocomplete="off" required>
                    @error('employee_no') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <label class="ictu-field" for="ictu-full-name">
                    <span>Full name</span>
                    <input id="ictu-full-name" name="full_name" value="{{ old('full_name', $user->full_name) }}" placeholder="Enter full name" autocomplete="name" required>
                    @error('full_name') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <label class="ictu-field" for="ictu-designation">
                    <span>Designation</span>
                    <input id="ictu-designation" name="designation" value="{{ old('designation', $user->designation) }}" placeholder="Enter official designation / position" autocomplete="organization-title" required>
                    @error('designation') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <div class="ictu-field ictu-email-field">
                    <div class="ictu-email-field-heading">
                        <label for="ictu-official-email">Official CSPC email</label>
                        @if($user->exists && !$user->hasVerifiedInstitutionalEmail())
                            <button type="submit" form="ictu-resend-email-verification-form" class="ictu-email-verification-link" title="Resend a verification email to the currently saved email address: {{ $user->email }}">
                                <span>Resend verification email</span>
                                <x-icon name="chevron-right" size="14" />
                            </button>
                        @endif
                    </div>
                    <input id="ictu-official-email" type="email" name="email" value="{{ old('email', $user->exists ? $user->email : '') }}"
                           placeholder="Enter official CSPC email" autocomplete="off" autocapitalize="none" spellcheck="false"
                           data-lpignore="true" data-1p-ignore="true"
                           @if(!$user->exists) readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" @endif required>
                    @error('email') <small class="field-error">{{ $message }}</small> @enderror
                    @if(!$user->exists)
                        <small class="field-note">A verification link is sent automatically after saving. Sign-in remains blocked until verification.</small>
                    @elseif($user->hasVerifiedInstitutionalEmail())
                        <small class="field-note">Email verified {{ $user->email_verified_at?->format('M j, Y g:i A') }}. Changing the email requires verification again.</small>
                    @endif
                </div>
            </div>
        </section>

        <section class="ictu-form-card" aria-labelledby="ictu-organization-heading">
            <header class="ictu-form-section-header">
                <h2 id="ictu-organization-heading">Organizational Assignment</h2>
            </header>
            <div class="ictu-field-grid ictu-field-grid-two">
                <label class="ictu-field" for="ictu-division">
                    <span>Division</span>
                    <select name="division_code" id="ictu-division" @required(!$historicalPrimaryUnit)>
                        <option value="">Select Division</option>
                        @foreach($divisionOptions as $code => $label)
                            <option value="{{ $code }}" @selected(old('division_code', $selectedDivisionCode) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('division_code') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="ictu-field" for="ictu-organizational-unit">
                    <span>Office / College / Unit</span>
                    <select name="organizational_unit_id" id="ictu-organizational-unit" required>
                        <option value="">Select Office / College / Unit</option>
                        @if($historicalPrimaryUnit)
                            <option value="{{ $historicalPrimaryUnit->id }}" data-division="{{ $historicalPrimaryUnit->divisionCode() }}"
                                    @selected(!$addingNewUnit && (string) $selectedUnitId === (string) $historicalPrimaryUnit->id)>
                                {{ $historicalPrimaryUnit->unit_name }} — Historical / inactive assignment
                            </option>
                        @endif
                        @foreach($units as $unit)
                            @php $unitDivision = $unit->divisionCode(); @endphp
                            @if(!$unitDivision) @continue @endif
                            <option value="{{ $unit->id }}" data-division="{{ $unitDivision }}"
                                    @selected(!$addingNewUnit && (string) $selectedUnitId === (string) $unit->id)>{{ $unit->unit_name }}</option>
                        @endforeach
                        <option value="__NEW__" data-new-unit @selected($addingNewUnit)>Other / Not listed</option>
                    </select>
                    @error('organizational_unit_id') <small class="field-error">{{ $message }}</small> @enderror
                    @if($historicalPrimaryUnit)
                        <small class="field-note">Historical / inactive assignment retained. Select an active unit to intentionally reassign this account.</small>
                    @endif
                </label>

                <div class="ictu-field ictu-new-unit-field" id="ictu-new-unit-field" @if(!$addingNewUnit) hidden @endif>
                    <label for="ictu-new-unit-name">Add Office / College / Unit</label>
                    <input type="text" name="new_organizational_unit_name" id="ictu-new-unit-name" value="{{ $newUnitName }}" maxlength="255" placeholder="Enter the official Office / College / Unit name">
                    <small class="field-note">New units are created under the selected Division (Borrower classification only).</small>
                    @error('new_organizational_unit_name') <small class="field-error">{{ $message }}</small> @enderror
                </div>

                <label class="ictu-field" for="ictu-personnel-type">
                    <span>Personnel Type</span>
                    <select name="employment_type" id="ictu-personnel-type" required>
                        <option value="">Select personnel type</option>
                        @foreach($employmentTypes as $type)
                            @php
                                $personnelTypeLabel = match ($type->value) {
                                    'FACULTY' => 'Faculty', 'EMPLOYEE' => 'Employee', 'STAFF' => 'Staff', default => $type->value,
                                };
                            @endphp
                            <option value="{{ $type->value }}" @selected(old('employment_type', $user->employment_type?->value) === $type->value)>{{ $personnelTypeLabel }}</option>
                        @endforeach
                    </select>
                    @error('employment_type') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <label class="ictu-field" for="ictu-employment-status">
                    <span>Employment Status</span>
                    <select name="employment_status" id="ictu-employment-status" required>
                        <option value="">Select employment status</option>
                        <option value="FULL_TIME" @selected(old('employment_status', $user->employment_status) === 'FULL_TIME')>Full-time</option>
                        <option value="PART_TIME" @selected(old('employment_status', $user->employment_status) === 'PART_TIME')>Part-time</option>
                    </select>
                    @error('employment_status') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>
        </section>

        <section class="ictu-form-card admin-additional-assignment" id="ictu-additional-assignment"
                 aria-labelledby="ictu-additional-heading"
                 @if(old('access_classification', $user->access_classification?->value ?? 'BORROWER_ONLY') !== 'BORROWER_ONLY') hidden @endif>
            <header class="ictu-form-section-header">
                <h2 id="ictu-additional-heading">Additional Authorized Requesting Unit <span class="ictu-optional-badge">Optional</span></h2>
                <p>Specify other units this borrower is expressly authorized to represent.</p>
            </header>
            {{-- Independent, matched Division + Unit selectors. The backend still receives only authorized unit IDs. --}}
            <div class="ictu-field-grid ictu-field-grid-two ictu-additional-selection-grid">
                <label class="ictu-field" for="ictu-additional-division">
                    <span>Division</span>
                    <select id="ictu-additional-division">
                        <option value="">Select Division</option>
                        @foreach($divisionOptions as $additionalDivisionCode => $additionalDivisionLabel)
                            <option value="{{ $additionalDivisionCode }}">{{ $additionalDivisionLabel }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ictu-field" for="ictu-additional-unit-picker">
                    <span>Office / College / Unit</span>
                    <select id="ictu-additional-unit-picker" disabled>
                        <option value="">Select Office / College / Unit</option>
                        @foreach($units as $additionalUnit)
                            @php $additionalDivisionCode = $additionalUnit->divisionCode(); @endphp
                            @if(!$additionalDivisionCode) @continue @endif
                            <option value="{{ $additionalUnit->id }}"
                                data-division="{{ $additionalDivisionCode }}"
                                data-unit-name="{{ $additionalUnit->unit_name }}"
                                data-division-label="{{ $additionalUnit->divisionLabel() }}">
                                {{ $additionalUnit->unit_name }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="ictu-additional-picker-actions">
                <button class="button secondary ictu-add-unit-button" type="button" id="ictu-add-additional-unit" disabled>
                    <x-icon name="plus" size="16" /> Add Unit
                </button>
            </div>
            <div class="admin-additional-unit-list" id="ictu-additional-unit-list" aria-live="polite">
                @foreach($units as $additionalUnit)
                    @if($selectedAdditionalUnitIds->contains((int) $additionalUnit->id))
                        <div class="admin-additional-unit-item" data-additional-unit-item data-unit-id="{{ $additionalUnit->id }}">
                            <div><strong>{{ $additionalUnit->unit_name }}</strong><small>{{ $additionalUnit->divisionLabel() }}</small></div>
                            <button type="button" class="button secondary" data-remove-additional-unit aria-label="Remove {{ $additionalUnit->unit_name }}">Remove</button>
                            <input type="hidden" name="additional_organizational_unit_ids[]" value="{{ $additionalUnit->id }}">
                        </div>
                    @endif
                @endforeach
                @foreach($historicalAdditionalUnits as $historicalAdditionalUnit)
                    <div class="admin-additional-unit-item" data-additional-unit-item data-unit-id="{{ $historicalAdditionalUnit->id }}">
                        <div><strong>{{ $historicalAdditionalUnit->unit_name }}</strong><small>{{ $historicalAdditionalUnit->divisionLabel() }} — Historical / inactive assignment</small></div>
                        <button type="button" class="button secondary" data-remove-additional-unit aria-label="Remove {{ $historicalAdditionalUnit->unit_name }}">Remove</button>
                        <input type="hidden" name="additional_organizational_unit_ids[]" value="{{ $historicalAdditionalUnit->id }}">
                    </div>
                @endforeach
            </div>
            @error('additional_organizational_unit_ids') <small class="field-error">{{ $message }}</small> @enderror
            @error('additional_organizational_unit_ids.*') <small class="field-error">{{ $message }}</small> @enderror
        </section>

        <section class="ictu-form-card" aria-labelledby="ictu-access-heading">
            <header class="ictu-form-section-header"><h2 id="ictu-access-heading">Classification, Status, and Contact</h2></header>
            <div class="ictu-field-grid ictu-field-grid-three">
                <div class="ictu-field">
                    <label for="ictu-access-classification">Access classification</label>
                    <select name="access_classification" id="ictu-access-classification" required @disabled($isSelfProtected)>
                        @foreach($classifications as $classification)
                            <option value="{{ $classification->value }}" @selected(old('access_classification', $user->access_classification?->value ?? 'BORROWER_ONLY') === $classification->value)>{{ $classification->label() }}</option>
                        @endforeach
                    </select>
                    @if($isSelfProtected)
                        <input type="hidden" name="access_classification" value="{{ $user->access_classification?->value }}">
                        <small class="field-note">You cannot change your own access classification.</small>
                    @endif
                    @error('access_classification') <small class="field-error">{{ $message }}</small> @enderror
                </div>
                <div class="ictu-field">
                    <label for="ictu-account-status">Account status</label>
                    <select name="account_status" id="ictu-account-status" required @disabled($isSelfProtected)>
                        @foreach($accountStatuses as $status)
                            @php
                                $accountStatusLabel = match ($status->value) {
                                    'ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'SUSPENDED' => 'Suspended', default => $status->value,
                                };
                            @endphp
                            <option value="{{ $status->value }}" @selected(old('account_status', $user->account_status?->value ?? 'ACTIVE') === $status->value)>{{ $accountStatusLabel }}</option>
                        @endforeach
                    </select>
                    @if($isSelfProtected)
                        <input type="hidden" name="account_status" value="{{ $user->account_status?->value }}">
                        <small class="field-note">You cannot change your own account status.</small>
                    @endif
                    @error('account_status') <small class="field-error">{{ $message }}</small> @enderror
                </div>
                <div class="ictu-field">
                    <label for="ictu-mobile">Mobile number</label>
                    <input type="tel" name="mobile_no" id="ictu-mobile" value="{{ old('mobile_no', $user->mobile_no) }}" placeholder="Enter mobile number" autocomplete="tel">
                    @error('mobile_no') <small class="field-error">{{ $message }}</small> @enderror
                </div>
            </div>
        </section>

        <section class="ictu-form-card" aria-labelledby="ictu-administrative-heading">
            <header class="ictu-form-section-header"><h2 id="ictu-administrative-heading">Administrative Actions</h2></header>
            <div class="ictu-field-grid ictu-field-grid-two">
                <label class="ictu-field" for="ictu-new-password">
                    <span>{{ $user->exists ? 'New password (leave blank to retain)' : 'Password' }}</span>
                    <input type="password" name="password" id="ictu-new-password" autocomplete="new-password" @required(!$user->exists)>
                    @error('password') <small class="field-error">{{ $message }}</small> @enderror
                </label>
                <label class="ictu-field" for="ictu-confirm-password">
                    <span>Confirm password</span>
                    <input type="password" name="password_confirmation" id="ictu-confirm-password" autocomplete="new-password" @required(!$user->exists)>
                    @error('password_confirmation') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>
        </section>

        <div class="ictu-form-actions">
            <div class="ictu-form-action-left">
                @if($user->exists && !$user->hasVerifiedInstitutionalEmail() && !$isSelfProtected && !$user->last_login_at)
                    <button type="submit" form="ictu-delete-pending-registration-form"
                            class="button secondary ictu-delete-account-button"
                            id="ictu-delete-pending-registration-button"
                            aria-label="Delete unverified account for {{ $user->full_name }}">
                        <x-icon name="trash" size="16" /> Delete Account
                    </button>
                    <small class="field-note">For mistaken, unused registrations only. Verified accounts cannot be deleted here.</small>
                @endif
            </div>
            <div class="ictu-form-action-right">
                <button class="button primary ui-pressable" type="submit"><x-icon name="save" size="17" /> {{ $user->exists ? 'Save Changes' : 'Create Account' }}</button>
                <a class="button secondary ui-pressable" href="{{ route('administration.users.index') }}">Cancel</a>
            </div>
        </div>
    </form>

    @if($user->exists && !$user->hasVerifiedInstitutionalEmail() && !$isSelfProtected && !$user->last_login_at)
        {{-- Separate form: Delete must never submit the unsaved changes in the Edit User form. --}}
        <form id="ictu-delete-pending-registration-form" method="post"
              action="{{ route('administration.users.delete-pending', $user) }}"
              data-preserve-submit-label="true" hidden>
            @csrf
            @method('DELETE')
        </form>
    @endif

    @if($user->exists && !$user->hasVerifiedInstitutionalEmail())
        <form id="ictu-resend-email-verification-form" method="post" action="{{ route('administration.users.email-verification.resend', $user) }}" data-preserve-submit-label="true" hidden>@csrf</form>
    @endif
</section>

@include('administration.users.partials.ui-styles')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const division = document.getElementById('ictu-division');
    const unit = document.getElementById('ictu-organizational-unit');
    const newUnitField = document.getElementById('ictu-new-unit-field');
    const newUnitName = document.getElementById('ictu-new-unit-name');
    const classification = document.getElementById('ictu-access-classification');
    const additionalAssignment = document.getElementById('ictu-additional-assignment');
    const additionalDivision = document.getElementById('ictu-additional-division');
    const additionalPicker = document.getElementById('ictu-additional-unit-picker');
    const additionalList = document.getElementById('ictu-additional-unit-list');
    const addAdditionalButton = document.getElementById('ictu-add-additional-unit');
    if (!division || !unit) return;

    const selectedAdditionalIds = () => Array.from(additionalList?.querySelectorAll('[data-additional-unit-item]') || [])
        .map(item => String(item.dataset.unitId || ''));

    const syncNewUnitField = () => {
        const adding = unit.value === '__NEW__';
        if (newUnitField) newUnitField.hidden = !adding;
        if (newUnitName) newUnitName.required = adding;
    };
    const syncAdditionalUnitOptions = () => {
        if (!additionalPicker) return;
        const primaryId = /^\d+$/.test(unit.value) ? String(unit.value) : '';
        const selectedIds = selectedAdditionalIds();
        const selectedDivision = additionalDivision?.value || '';

        Array.from(additionalPicker.options).forEach(option => {
            if (!option.value) return;
            const matches = Boolean(selectedDivision) && option.dataset.division === selectedDivision;
            option.hidden = !matches;
            option.disabled = !matches || option.value === primaryId || selectedIds.includes(option.value);
        });
        additionalPicker.disabled = !selectedDivision || additionalAssignment?.hidden === true;
        if (additionalPicker.selectedOptions?.[0]?.disabled || additionalPicker.disabled) additionalPicker.value = '';
        if (addAdditionalButton) addAdditionalButton.disabled = additionalPicker.disabled || !additionalPicker.value;
    };
    const syncAdditionalAssignments = () => {
        if (!additionalAssignment) return;
        const isBorrower = !classification || classification.value === 'BORROWER_ONLY';
        additionalAssignment.hidden = !isBorrower;
        // The server accepts additional requesting units for borrowers only.
        const newUnitOption = unit.querySelector('[data-new-unit]');
        if (newUnitOption) {
            newUnitOption.hidden = !isBorrower;
            newUnitOption.disabled = !isBorrower;
            if (!isBorrower && unit.value === '__NEW__') {
                unit.value = '';
                if (newUnitName) newUnitName.value = '';
                syncNewUnitField();
            }
        }
        const primaryId = /^\d+$/.test(unit.value) ? String(unit.value) : '';
        Array.from(additionalList?.querySelectorAll('[data-additional-unit-item]') || []).forEach(item => {
            if (String(item.dataset.unitId || '') === primaryId) item.remove();
        });
        syncAdditionalUnitOptions();
    };
    const syncUnits = () => {
        const selectedDivision = division.value;
        const currentValue = unit.value;
        Array.from(unit.options).forEach(option => {
            if (!option.value || option.dataset.newUnit !== undefined) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            const matches = option.dataset.division === selectedDivision;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        if (unit.selectedOptions?.[0]?.disabled) unit.value = '';
        else if (currentValue && unit.value === '') {
            const match = Array.from(unit.options).find(option => option.value === currentValue && !option.disabled);
            if (match) unit.value = currentValue;
        }
        syncNewUnitField();
        syncAdditionalAssignments();
    };
    const removeAdditionalUnit = item => { item?.remove(); syncAdditionalAssignments(); };
    const addAdditionalUnit = () => {
        if (!additionalPicker?.value || !additionalList) return;
        const selected = additionalPicker.selectedOptions?.[0];
        if (!selected || selected.disabled) return;
        const unitId = String(selected.value);
        if (selectedAdditionalIds().includes(unitId)) return;
        const unitName = selected.dataset.unitName || selected.textContent?.trim() || '';
        const divisionLabel = selected.dataset.divisionLabel || selected.parentElement?.label || '';
        const item = document.createElement('div');
        item.className = 'admin-additional-unit-item';
        item.dataset.additionalUnitItem = '';
        item.dataset.unitId = unitId;
        const copy = document.createElement('div');
        const strong = document.createElement('strong');
        const small = document.createElement('small');
        strong.textContent = unitName;
        small.textContent = divisionLabel;
        copy.append(strong, small);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'button secondary';
        remove.dataset.removeAdditionalUnit = '';
        remove.textContent = 'Remove';
        remove.setAttribute('aria-label', `Remove ${unitName}`);
        remove.addEventListener('click', () => removeAdditionalUnit(item));
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'additional_organizational_unit_ids[]';
        hidden.value = unitId;
        item.append(copy, remove, hidden);
        additionalList.appendChild(item);
        additionalPicker.value = '';
        syncAdditionalAssignments();
    };
    additionalList?.querySelectorAll('[data-remove-additional-unit]').forEach(button => {
        button.addEventListener('click', () => removeAdditionalUnit(button.closest('[data-additional-unit-item]')));
    });
    division.addEventListener('change', syncUnits);
    unit.addEventListener('change', () => { syncNewUnitField(); syncAdditionalAssignments(); });
    classification?.addEventListener('change', syncAdditionalAssignments);
    additionalDivision?.addEventListener('change', () => {
        if (additionalPicker) additionalPicker.value = '';
        syncAdditionalUnitOptions();
    });
    additionalPicker?.addEventListener('change', syncAdditionalUnitOptions);
    addAdditionalButton?.addEventListener('click', addAdditionalUnit);
    syncUnits();

    const deleteButton = document.getElementById('ictu-delete-pending-registration-button');
    deleteButton?.addEventListener('click', event => {
        if (!window.confirm('Delete this unverified, unused account? This cannot be undone. If the mailbox is valid but mistyped, editing the email and resending verification is safer.')) {
            event.preventDefault();
        }
    });
});
</script>
@endsection
