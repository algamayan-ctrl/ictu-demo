@extends('layouts.app', ['title' => 'Review borrower registration'])
@section('content')
@php
    $isPending = $registration->status === App\Enums\RegistrationStatus::Pending;
    $isEmployee = $registration->registration_type === App\Enums\RegistrationType::Employee;
    $emailVerified = $registration->user->hasVerifiedInstitutionalEmail();
    $submittedAt = $registration->submitted_for_review_at ?? $registration->created_at;
    $applicantInitial = str($registration->user->full_name ?: 'B')->trim()->substr(0, 1)->upper();
@endphp

<div class="registration-review-page">
    <section class="page-heading registration-review-heading">
        <div>
            <p class="eyebrow">Account verification</p>
            <h1>Review registration</h1>
            <p>Check the applicant details before recording a decision.</p>
        </div>

        <a class="button secondary ui-pressable" href="{{ route('registrations.index') }}">
            <x-icon name="arrow-left" size="18" />
            <span>Back to registrations</span>
        </a>
    </section>

    <article class="registration-review-card" aria-labelledby="registration-applicant-name">
        <header class="registration-review-card__header">
            <div class="registration-review-identity">
                <span class="registration-review-avatar" aria-hidden="true">{{ $applicantInitial }}</span>
                <div>
                    <p class="eyebrow">Applicant</p>
                    <h2 id="registration-applicant-name">{{ $registration->user->full_name }}</h2>
                    <p>{{ $registration->registration_type->label() }}</p>
                </div>
            </div>

            <div class="registration-review-status">
                <span>Registration status</span>
                <x-status-badge :status="$registration->status->value" />
            </div>
        </header>

        <div class="registration-review-details">
            <section class="registration-review-section" aria-labelledby="registration-contact-heading">
                <h3 id="registration-contact-heading">Contact details</h3>
                <dl class="registration-data-list">
                    <div>
                        <dt>Email</dt>
                        <dd>
                            <span>{{ $registration->user->email }}</span>
                            <span class="registration-email-status {{ $emailVerified ? 'is-verified' : 'is-pending' }}">{{ $emailVerified ? 'Verified' : 'Verification pending' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt>Mobile number</dt>
                        <dd>{{ $registration->user->mobile_no ?: 'Not provided' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="registration-review-section" aria-labelledby="registration-profile-heading">
                <h3 id="registration-profile-heading">Registration details</h3>
                <dl class="registration-data-list">
                    @if($isEmployee)
                        <div><dt>Employee number</dt><dd>{{ $registration->user->employee_no ?: 'Not provided' }}</dd></div>
                        <div><dt>Designation</dt><dd>{{ $registration->user->designation ?: 'Not provided' }}</dd></div>
                        <div><dt>Division / Unit</dt><dd>{{ $registration->user->organizationalUnit?->divisionLabel() ?: 'Not provided' }}@if($registration->user->organizationalUnit?->unit_name) · {{ $registration->user->organizationalUnit->unit_name }}@endif</dd></div>
                    @else
                        <div><dt>Company / Agency / Institution</dt><dd>{{ $registration->company_name ?: 'Not provided' }}</dd></div>
                    @endif
                    <div><dt>Submitted</dt><dd>{{ $submittedAt?->format('M j, Y · g:i A') ?: 'Not recorded' }}</dd></div>
                </dl>
            </section>

            @unless($isEmployee)
                <section class="registration-document-card" aria-labelledby="registration-id-heading">
                    <div>
                        <h3 id="registration-id-heading">Valid ID</h3>
                        <p>{{ $registration->validIdFile ? 'Submitted by the applicant.' : 'No valid ID was submitted.' }}</p>
                    </div>
                    @if($registration->validIdFile)
                        <a class="button secondary small ui-pressable" href="{{ route('files.preview', $registration->validIdFile) }}" target="_blank" rel="noopener">
                            <x-icon name="file-search" size="17" />
                            <span>Open Valid ID</span>
                        </a>
                    @else
                        <span class="registration-document-missing">Missing document</span>
                    @endif
                </section>
            @endunless
        </div>
    </article>

    @if($isPending)
        <section class="registration-decision-card" aria-labelledby="registration-decision-heading">
            <div class="registration-decision-card__heading">
                <p class="eyebrow">SAO decision</p>
                <h2 id="registration-decision-heading">Review decision</h2>
                <p>Approve the registration when the details are complete and valid.</p>
            </div>

            <form id="registration-review-form" method="post" action="{{ route('registrations.decide', $registration) }}">
                @csrf
                <label for="registration-remarks-{{ $registration->id }}">Review remarks</label>
                <textarea id="registration-remarks-{{ $registration->id }}" name="remarks" maxlength="1000" rows="3" placeholder="Required only when rejecting the registration.">{{ old('remarks') }}</textarea>
                @error('remarks')
                    <p class="field-error" role="alert">{{ $message }}</p>
                @enderror
                <div class="registration-decision-actions">
                    <button class="button primary ui-pressable" type="submit" name="decision" value="APPROVED">Approve registration</button>
                    <button class="button danger ui-pressable" type="submit" name="decision" value="REJECTED">Reject registration</button>
                </div>
            </form>
        </section>
    @else
        <section class="registration-completed-card" aria-label="Recorded decision">
            <div class="registration-completed-card__icon" aria-hidden="true"><x-icon name="clipboard-check" size="19" /></div>
            <div>
                <p class="eyebrow">Decision recorded</p>
                <p>Reviewed by {{ $registration->reviewer?->full_name ?: 'SPMU Action Officer' }} @if($registration->reviewed_at)on {{ $registration->reviewed_at->format('M j, Y · g:i A') }}@endif.</p>
                @if($registration->review_remarks)<p class="registration-completed-card__remarks">{{ $registration->review_remarks }}</p>@endif
            </div>
        </section>
    @endif
</div>

<style>
.registration-review-page { display: grid; gap: 18px; min-width: 0; }
.registration-review-heading { margin-bottom: 0; }
.registration-review-card,
.registration-decision-card,
.registration-completed-card { overflow: hidden; border: 1px solid var(--border); border-radius: 12px; background: var(--surface-elevated); box-shadow: var(--shadow-sm); }
.registration-review-card__header { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 22px 24px; border-bottom: 1px solid var(--border); background: linear-gradient(120deg, color-mix(in srgb, var(--primary-action) 6%, var(--surface-elevated)), var(--surface-elevated) 55%); }
.registration-review-identity { display: flex; align-items: center; min-width: 0; gap: 13px; }
.registration-review-avatar { display: grid; width: 44px; height: 44px; flex: 0 0 44px; place-items: center; border: 1px solid color-mix(in srgb, var(--primary-action) 24%, var(--border)); border-radius: 50%; background: color-mix(in srgb, var(--primary-action) 10%, var(--surface-elevated)); color: var(--primary-action); font-size: 17px; font-weight: 800; }
.registration-review-identity .eyebrow,
.registration-review-status > span,
.registration-decision-card .eyebrow,
.registration-completed-card .eyebrow { margin: 0 0 4px; color: var(--text-muted); font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
.registration-review-identity h2 { margin: 0; color: var(--heading); font-size: 22px; line-height: 1.25; }
.registration-review-identity h2 + p { margin: 4px 0 0; color: var(--text-secondary); font-size: 13px; }
.registration-review-status { display: grid; justify-items: end; gap: 5px; flex: 0 0 auto; }
.registration-review-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding: 20px 24px 24px; }
.registration-review-section { min-width: 0; }
.registration-review-section h3,
.registration-document-card h3 { margin: 0 0 10px; color: var(--heading); font-size: 14px; }
.registration-data-list { display: grid; margin: 0; border-top: 1px solid var(--border); }
.registration-data-list > div { display: grid; grid-template-columns: minmax(122px, .75fr) minmax(0, 1.25fr); gap: 14px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.registration-data-list dt { color: var(--text-secondary); font-size: 12px; font-weight: 700; }
.registration-data-list dd { display: flex; min-width: 0; align-items: baseline; flex-wrap: wrap; gap: 7px; margin: 0; color: var(--heading); font-size: 13px; overflow-wrap: anywhere; }
.registration-email-status { display: inline-flex; flex: 0 0 auto; padding: 2px 7px; border: 1px solid; border-radius: 999px; font-size: 10px; font-weight: 750; white-space: nowrap; }
.registration-email-status.is-verified { border-color: #a7dfbc; background: #edf9f0; color: #087443; }
.registration-email-status.is-pending { border-color: #f2d193; background: #fff8e7; color: #9b6100; }
.registration-document-card { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 15px 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-subtle); }
.registration-document-card h3 { margin-bottom: 3px; }
.registration-document-card p { margin: 0; color: var(--text-secondary); font-size: 12px; }
.registration-document-missing { color: var(--danger); font-size: 12px; font-weight: 750; }
.registration-decision-card { padding: 22px 24px 24px; }
.registration-decision-card__heading { margin-bottom: 16px; }
.registration-decision-card__heading h2 { margin: 0; color: var(--heading); font-size: 18px; }
.registration-decision-card__heading h2 + p { margin: 5px 0 0; color: var(--text-secondary); font-size: 13px; }
#registration-review-form { display: grid; gap: 8px; }
#registration-review-form label { color: var(--heading); font-size: 13px; font-weight: 750; }
#registration-review-form textarea { width: 100%; min-height: 88px; padding: 10px 12px; box-sizing: border-box; resize: vertical; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface-elevated); color: var(--heading); font: inherit; font-size: 13px; line-height: 1.5; }
#registration-review-form textarea:focus { outline: none; border-color: var(--primary-action); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-action) 14%, transparent); }
#registration-review-form .field-error { margin: 0; color: var(--danger); font-size: 12px; font-weight: 650; }
.registration-decision-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; margin-top: 8px; padding-top: 16px; border-top: 1px solid var(--border); }
.registration-decision-actions .button { min-height: 40px; }
.registration-completed-card { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px; }
.registration-completed-card__icon { display: grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border-radius: 50%; background: color-mix(in srgb, var(--primary-action) 10%, var(--surface-elevated)); color: var(--primary-action); }
.registration-completed-card > div:last-child { min-width: 0; }
.registration-completed-card p { margin: 0; color: var(--text-secondary); font-size: 13px; line-height: 1.5; }
.registration-completed-card .eyebrow { margin-bottom: 3px; }
.registration-completed-card__remarks { margin-top: 7px !important; color: var(--heading) !important; }
@media (max-width: 760px) {
    .registration-review-card__header { align-items: flex-start; flex-direction: column; padding: 18px; }
    .registration-review-status { justify-items: start; }
    .registration-review-details { grid-template-columns: minmax(0, 1fr); padding: 18px; }
    .registration-document-card { grid-column: auto; align-items: flex-start; flex-direction: column; }
    .registration-decision-card { padding: 18px; }
    .registration-decision-actions .button { flex: 1 1 190px; }
}
@media (max-width: 480px) {
    .registration-data-list > div { grid-template-columns: minmax(0, 1fr); gap: 4px; }
    .registration-review-identity h2 { font-size: 19px; }
    .registration-decision-actions { flex-direction: column; }
}
</style>
@endsection
