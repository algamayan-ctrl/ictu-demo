@extends('layouts.app', ['title' => 'Review borrower registration'])
@section('content')
<section class="page-heading"><div><h1>Review borrower registration</h1><p>Verify applicant details before recording a decision.</p></div><a class="button secondary" href="{{ route('registrations.index') }}"><x-icon name="arrow-left" size="18" /> Back to registrations</a></section>

    <article class="card p-3 mb-3">
        <div class="d-flex justify-content-between gap-3 flex-wrap"><h2 class="h5">{{ $registration->user->full_name }}</h2><x-status-badge :status="$registration->status->value" /></div>
        <dl class="row mb-2">
            <dt class="col-sm-3">Applicant type</dt><dd class="col-sm-9">{{ $registration->registration_type->label() }}</dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $registration->user->email }} — {{ $registration->user->hasVerifiedInstitutionalEmail() ? 'Verified' : 'OTP verification pending' }}</dd>
            <dt class="col-sm-3">Mobile number</dt><dd class="col-sm-9">{{ $registration->user->mobile_no }}</dd>
            @if($registration->registration_type === App\Enums\RegistrationType::Employee)
                <dt class="col-sm-3">Employee number</dt><dd class="col-sm-9">{{ $registration->user->employee_no }}</dd>
                <dt class="col-sm-3">Designation</dt><dd class="col-sm-9">{{ $registration->user->designation }}</dd>
                <dt class="col-sm-3">Division / Unit</dt><dd class="col-sm-9">{{ $registration->user->organizationalUnit?->divisionLabel() }} / {{ $registration->user->organizationalUnit?->unit_name }}</dd>
            @else
                <dt class="col-sm-3">Company / Agency / Institution</dt><dd class="col-sm-9">{{ $registration->company_name }}</dd>
                <dt class="col-sm-3">Valid ID</dt><dd class="col-sm-9">@if($registration->validIdFile)<a href="{{ route('files.preview', $registration->validIdFile) }}" target="_blank" rel="noopener">Review Valid ID</a>@else Missing document @endif</dd>
            @endif
            <dt class="col-sm-3">Submitted</dt><dd class="col-sm-9">{{ $registration->created_at->format('M j, Y g:i A') }}</dd>
        </dl>
        @if($registration->status === App\Enums\RegistrationStatus::Pending)
            <form id="registration-review-form" method="post" action="{{ route('registrations.decide', $registration) }}">
                @csrf
                <label for="registration-remarks-{{ $registration->id }}" class="form-label">Review remarks (required for rejection)</label>
                <textarea id="registration-remarks-{{ $registration->id }}" name="remarks" maxlength="1000" class="form-control mb-2">{{ old('remarks') }}</textarea>
                <div class="inline-actions"><button class="button primary" type="submit" name="decision" value="APPROVED">Approve registration</button>
                <button class="button danger" type="submit" name="decision" value="REJECTED">Reject registration</button></div>
            </form>
        @else
            <p class="mb-0">Reviewed by {{ $registration->reviewer?->full_name }} on {{ $registration->reviewed_at?->format('M j, Y g:i A') }}. {{ $registration->review_remarks }}</p>
        @endif
    </article>

<style>
#registration-review-form .button.primary { color: #fff; background: var(--primary-action); border-color: var(--primary-action); }
#registration-review-form .button.danger { color: #fff; background: var(--danger-action); border-color: var(--danger-action); }
#registration-review-form .button.primary:hover { background: var(--primary-action-hover); border-color: var(--primary-action-hover); }
#registration-review-form .button.danger:hover { background: var(--danger-action-hover); border-color: var(--danger-action-hover); }
</style>
@endsection
