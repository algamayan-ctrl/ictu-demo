@extends('layouts.app', ['title' => 'Borrower registration'])
@section('content')
@php
    $google = $googleRegistration ?? null;
    $selectedType = $google['registration_type'] ?? old('registration_type', request('type', 'EMPLOYEE'));
    $external = $selectedType === 'EXTERNAL';
@endphp
@include('auth.partials.registration-styles')
<section class="registration-page" aria-labelledby="registration-heading">
    <header class="registration-heading"><p class="eyebrow">Account registration</p><h1 id="registration-heading">Create a new account</h1><p>{{ $google ? 'Complete your registration using your verified Google email.' : 'Choose your account type and complete the form.' }}</p></header>
    <form method="post" action="{{ route('register.store') }}" enctype="multipart/form-data" id="borrower-registration-form" class="registration-card">
        @csrf
        <input type="hidden" name="registration_source" value="{{ $google ? 'google' : 'manual' }}">
        <input type="hidden" id="registration-type" name="registration_type" value="{{ $selectedType }}">
        @if(!$google)
        <p class="registration-type-label">Register as</p>
        <div class="registration-account-options" aria-label="Choose account type">
            <button type="button" data-registration-type="EMPLOYEE" aria-pressed="{{ $external ? 'false' : 'true' }}"><span class="registration-type-icon"><x-icon name="inventory" size="24" /></span><span><strong>CSPC Employee</strong><small>For CSPC personnel, faculty and staff members</small></span><x-icon name="chevron-right" size="18" /></button>
            <button type="button" data-registration-type="EXTERNAL" aria-pressed="{{ $external ? 'true' : 'false' }}"><span class="registration-type-icon"><x-icon name="users" size="24" /></span><span><strong>External Borrower</strong><small>For authorized external individuals or offices</small></span><x-icon name="chevron-right" size="18" /></button>
        </div>
        @endif
        <div class="registration-form-heading"><span class="registration-type-icon"><x-icon name="id-badge" size="24" /></span><div><h2 id="registration-form-title">{{ $external ? 'External Borrower Registration' : 'CSPC Employee Registration' }}</h2>@if($google)<p>Google email verified. <a href="{{ route('auth.google.redirect') }}">Use another Google account</a></p>@endif</div></div>
        <div class="registration-fields">
            <fieldset data-registration-kind="EMPLOYEE" @disabled($external) @if($external) hidden @endif>
                <legend class="visually-hidden">CSPC employee identity</legend>
                <x-registration-field id="registration-employee-no" name="employee_no" label="Employee number" icon="id-badge" :value="old('employee_no')" maxlength="80" />
            </fieldset>
            <x-registration-field id="registration-name" name="full_name" label="Full name" :value="old('full_name', $google['name'] ?? '')" autocomplete="name" />
            <fieldset id="employee-registration-fields" data-registration-kind="EMPLOYEE" @disabled($external) @if($external) hidden @endif>
                <legend class="visually-hidden">CSPC employee details</legend>
                <x-registration-field id="registration-designation" name="designation" label="Designation" icon="id-badge" :value="old('designation')" />
            </fieldset>
            <x-registration-field id="registration-email" name="email" label="Email" icon="email" type="email" :value="$google['email'] ?? old('email')" :readonly="(bool) $google" autocomplete="email" :help="$google ? 'This email comes from your verified Google account and cannot be edited.' : 'Use your CSPC institutional email.'" />
            <fieldset data-registration-kind="EMPLOYEE" @disabled($external) @if($external) hidden @endif>
                <legend class="visually-hidden">CSPC organizational assignment</legend>
                <x-registration-field id="registration-division" name="division_code" label="Division" icon="delegation"><select id="registration-division" name="division_code" class="form-select" required><option value="">Select division</option>@foreach($divisions as $code => $label)<option value="{{ $code }}" @selected(old('division_code') === $code)>{{ $label }}</option>@endforeach</select></x-registration-field>
                <x-registration-field id="registration-unit" name="organizational_unit_id" label="Office / College / Unit" icon="inventory"><select id="registration-unit" name="organizational_unit_id" class="form-select" required><option value="">Select office / college / unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}" data-division="{{ $unit->divisionCode() }}" @selected((string) old('organizational_unit_id') === (string) $unit->id)>{{ $unit->unit_name }}</option>@endforeach</select></x-registration-field>
            </fieldset>
            <x-registration-field id="registration-mobile" name="mobile_no" label="Mobile number" icon="phone" type="tel" :value="old('mobile_no')" maxlength="30" autocomplete="tel" />
            <fieldset id="external-registration-fields" data-registration-kind="EXTERNAL" @disabled(!$external) @if(!$external) hidden @endif>
                <legend class="visually-hidden">External borrower details</legend>
                <x-registration-field id="registration-company" name="company_name" label="Company / Agency / Institution" icon="inventory" :value="old('company_name')" />
                <x-registration-field id="registration-valid-id" name="valid_id" label="Valid ID upload" icon="upload" :help="'Upload a readable PDF, JPG or PNG up to '.$uploadLimitMb.' MB. Stored privately for SAO review. Select the file again after correcting form errors.'"><input id="registration-valid-id" name="valid_id" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="registration-valid-id-help" required></x-registration-field>
            </fieldset>
            <x-registration-field id="registration-password" name="password" :label="$google ? 'System password' : 'Password'" icon="lock" type="password" autocomplete="new-password" minlength="12" :help="$google ? 'Create a separate system password for email/password sign-in. Use at least 12 characters with letters, numbers and symbols.' : 'Use at least 12 characters with letters, numbers and symbols.'" />
            <x-registration-field id="registration-confirm-password" name="password_confirmation" :label="$google ? 'Confirm system password' : 'Confirm password'" icon="lock" type="password" autocomplete="new-password" minlength="12" />
        </div>
        <div class="registration-status-note"><x-icon name="clock" size="24" /><div><strong>{{ $google ? 'SAO approval required' : 'Verify email before SAO review' }}</strong><p>{{ $google ? 'Submit this form for SAO review. Borrowing access starts after approval.' : 'After submission, enter your emailed OTP to send your registration to SAO.' }}</p></div></div>
        <button class="button primary registration-submit" type="submit">Submit registration <x-icon name="arrow-right" size="18" /></button>
        <p class="registration-signin">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
    </form>
</section>
<script>
(() => {
    const type = document.getElementById('registration-type');
    const title = document.getElementById('registration-form-title');
    const options = document.querySelectorAll('[data-registration-type]');
    const division = document.getElementById('registration-division');
    const unit = document.getElementById('registration-unit');
    const syncType = () => {
        document.querySelectorAll('[data-registration-kind]').forEach(fieldset => {
            fieldset.hidden = fieldset.disabled = fieldset.dataset.registrationKind !== type.value;
        });
        options.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.registrationType === type.value)));
        title.textContent = type.value === 'EXTERNAL' ? 'External Borrower Registration' : 'CSPC Employee Registration';
        const emailHelp = document.getElementById('registration-email-help');
        if (emailHelp && !@json((bool) $google)) emailHelp.hidden = type.value === 'EXTERNAL';
    };
    const syncUnits = () => {
        for (const option of unit.options) {
            if (option.value) option.hidden = option.disabled = option.dataset.division !== division.value;
        }
        if (unit.selectedOptions[0]?.disabled) unit.value = '';
    };
    options.forEach(button => button.addEventListener('click', () => { type.value = button.dataset.registrationType; syncType(); }));
    document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', (show ? 'Hide' : 'Show') + ' ' + input.labels[0].textContent.toLowerCase());
    }));
    type.addEventListener('change', syncType);
    division.addEventListener('change', syncUnits);
    syncType(); syncUnits();
})();
</script>
@endsection
